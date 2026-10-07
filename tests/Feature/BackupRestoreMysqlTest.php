<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\GanttTask;
use App\Models\Project;
use App\Models\ProjectBacklog;
use App\Models\User;
use App\Support\Backup\BackupCrypto;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Prova de ponta a ponta no MySQL real: migra um banco, grava dados, roda backup:run (criptografado), backup:verify,
 * restaura em OUTRO banco com backup:restore e compara todas as tabelas linha a linha.
 *
 * É destrutivo (recria os dois bancos), por isso só roda quando BACKUP_SMOKE_MYSQL=1 e os bancos descartáveis
 * BACKUP_SMOKE_DB_SOURCE / BACKUP_SMOKE_DB_TARGET existem. O CI faz isso no job "Backup e restauração (MySQL)".
 */
class BackupRestoreMysqlTest extends TestCase
{
    private string $dir;

    private string $originalDefault;

    protected function setUp(): void
    {
        parent::setUp();

        if (! env('BACKUP_SMOKE_MYSQL')) {
            $this->markTestSkipped('Defina BACKUP_SMOKE_MYSQL=1 e os bancos BACKUP_SMOKE_DB_SOURCE/BACKUP_SMOKE_DB_TARGET (descartáveis).');
        }

        $this->originalDefault = (string) config('database.default');
        foreach (['source' => 'BACKUP_SMOKE_DB_SOURCE', 'target' => 'BACKUP_SMOKE_DB_TARGET'] as $role => $variable) {
            config(["database.connections.smoke_{$role}" => array_merge(config('database.connections.mysql'), [
                'host' => env('DB_HOST', '127.0.0.1'),
                'port' => env('DB_PORT', '3306'),
                'username' => env('DB_USERNAME', 'root'),
                'password' => env('DB_PASSWORD', ''),
                'database' => env($variable),
            ])]);
        }

        $this->dir = storage_path('framework/testing/smoke-'.uniqid());
        config([
            'backup.path' => $this->dir,
            'backup.copy_disk' => null,
            'backup.file_dirs' => [],
            'backup.encryption_key' => BackupCrypto::generateKey(),
        ]);
    }

    protected function tearDown(): void
    {
        if (isset($this->dir)) {
            File::deleteDirectory($this->dir);
        }
        if (isset($this->originalDefault)) {
            config(['database.default' => $this->originalDefault]);
            DB::purge('smoke_source');
            DB::purge('smoke_target');
        }
        parent::tearDown();
    }

    private function use(string $connection): void
    {
        config(['database.default' => $connection]);
        DB::setDefaultConnection($connection);
        DB::purge($connection);
    }

    public function test_encrypted_backup_of_a_real_mysql_database_restores_identically_into_another_database(): void
    {
        $this->use('smoke_source');
        Artisan::call('migrate:fresh', ['--database' => 'smoke_source', '--force' => true]);

        $company = Company::factory()->create(['name' => "O'Brien & Filhos; teste\nlinha 2 \\ barra 日本", 'slug' => 'obrien']);
        User::factory()->create(['company_id' => $company->id, 'role' => 'admin', 'email' => 'ana@example.org']);
        app(TenantContext::class)->setCompanyId($company->id);
        $project = Project::forceCreate(['company_id' => $company->id, 'name' => 'Portal', 'code' => 'PORTAL', 'status' => 'active', 'budget' => 1234.5]);
        $backlog = ProjectBacklog::forceCreate(['company_id' => $company->id, 'project_id' => $project->id, 'code' => 'BL-01', 'name' => 'Backlog', 'status' => 'active']);
        for ($i = 0; $i < 250; $i++) {
            GanttTask::forceCreate([
                'company_id' => $company->id, 'project_id' => $project->id, 'project_backlog_id' => $backlog->id, 'code' => "T$i",
                'name' => "Tarefa $i \\ 'q' \"d\" (x),(y);", 'level' => $i % 3, 'status' => 'STATUS_ACTIVE', 'progress' => $i % 100,
                'start_at' => '2026-09-01 00:00:00', 'end_at' => '2026-09-02 23:59:59', 'duration' => 2, 'depends' => '', 'sort_order' => $i,
            ]);
        }

        $this->assertSame(0, Artisan::call('backup:run', ['--no-files' => true]), Artisan::output());
        $this->assertSame(0, Artisan::call('backup:verify'), Artisan::output());

        $encrypted = glob($this->dir.'/db-*.sql.gz.enc');
        $this->assertCount(1, $encrypted, 'o backup deve estar criptografado e o dump em texto aberto removido');
        $this->assertEmpty(glob($this->dir.'/db-*.sql.gz'));

        // Restaura em OUTRO banco (migrado e vazio de dados) e compara tudo.
        $this->use('smoke_target');
        Artisan::call('migrate:fresh', ['--database' => 'smoke_target', '--force' => true]);
        $this->assertSame(0, Artisan::call('backup:restore', ['file' => $encrypted[0], '--force' => true]), Artisan::output());

        $skipped = (array) config('backup.skip_data');
        $tables = array_map(fn ($row) => (string) array_values((array) $row)[0], DB::connection('smoke_source')->select('show tables'));
        $this->assertContains('gantt_tasks', $tables);
        foreach ($tables as $table) {
            if (in_array($table, $skipped, true)) {
                continue;
            }
            $this->assertSame(
                $this->dump('smoke_source', $table),
                $this->dump('smoke_target', $table),
                "A tabela {$table} restaurada difere da original",
            );
        }

        $this->assertSame(250, DB::connection('smoke_target')->table('gantt_tasks')->count());
    }

    /** @return list<array<string, mixed>> */
    private function dump(string $connection, string $table): array
    {
        return DB::connection($connection)->table($table)->orderByRaw('1')->get()
            ->map(fn ($row) => (array) $row)->all();
    }
}
