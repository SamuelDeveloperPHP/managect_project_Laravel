<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use App\Support\Backup\BackupCrypto;
use App\Support\Backup\BackupSet;
use App\Support\Backup\DatabaseDumper;
use App\Support\Backup\SqlRestorer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Tests\TestCase;

class BackupAndMailReadinessTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = storage_path('framework/testing/backup-'.uniqid());
        config(['backup.path' => $this->dir, 'backup.copy_disk' => null, 'backup.file_dirs' => []]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        parent::tearDown();
    }

    public function test_dump_and_restore_round_trip_keeps_tricky_text_intact(): void
    {
        // A restauração recria tabelas (DDL), o que em MySQL confirma a transação do teste e deixaria dados para trás.
        // Em MySQL essa prova é feita de ponta a ponta pelo job "Backup e restauração (MySQL)" do CI.
        $this->skipUnlessSqlite();

        $names = ["O'Brien & Filhos; DROP TABLE x;", "Linha 1\nLinha 2 -- não é comentário", 'Barra \\ invertida "aspas" ção 日本'];
        foreach ($names as $i => $name) {
            Company::factory()->create(['name' => $name, 'slug' => 'empresa-'.$i]);
        }

        File::ensureDirectoryExists($this->dir);
        $file = $this->dir.'/db-test.sql.gz';
        $stats = (new DatabaseDumper)->dump(DB::connection(), $file);
        $this->assertGreaterThan(0, $stats['tables']);
        $this->assertGreaterThanOrEqual(3, $stats['rows']);

        config(['database.connections.restore_target' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false]]);
        $target = DB::connection('restore_target');
        $this->assertGreaterThan(0, (new SqlRestorer)->restore($target, $file));

        $restored = $target->table('companies')->orderBy('id')->pluck('name')->all();
        $this->assertSame($names, $restored);
        $this->assertSame(
            DB::table('companies')->count(),
            $target->table('companies')->count(),
        );
    }

    private function skipUnlessSqlite(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            $this->markTestSkipped('Teste destrutivo: roda apenas em SQLite (em MySQL, ver o job de CI de backup).');
        }
    }

    public function test_backup_command_writes_files_marker_and_prunes_old_sets(): void
    {
        config(['backup.keep_days' => 7, 'backup.keep_min' => 2]);
        File::ensureDirectoryExists($this->dir);
        foreach (['20200101-010101', '20200102-010101', '20200103-010101', '20200104-010101'] as $stamp) {
            File::put($this->dir."/db-{$stamp}.sql.gz", 'old');
        }

        $this->artisan('backup:run', ['--no-files' => true])->assertSuccessful();

        $this->assertNotEmpty(glob($this->dir.'/db-'.now()->format('Ymd').'-*.sql.gz'));
        $this->assertFileExists($this->dir.'/last-success.json');
        $this->assertFileExists($this->dir.'/.htaccess');
        // Mantém o conjunto novo e o mais recente dos antigos (keep_min=2); remove os demais.
        $this->assertFileExists($this->dir.'/db-20200104-010101.sql.gz');
        $this->assertFileDoesNotExist($this->dir.'/db-20200101-010101.sql.gz');
        $this->assertFileDoesNotExist($this->dir.'/db-20200102-010101.sql.gz');
    }

    public function test_restore_command_requires_force(): void
    {
        File::ensureDirectoryExists($this->dir);
        $file = $this->dir.'/db-test.sql.gz';
        (new DatabaseDumper)->dump(DB::connection(), $file);

        $this->artisan('backup:restore', ['file' => $file])->assertFailed();
    }

    public function test_preflight_fails_in_production_with_development_settings_and_passes_when_ready(): void
    {
        $this->app['env'] = 'production';
        config([
            'app.debug' => true, 'app.url' => 'http://localhost', 'mail.default' => 'log', 'mail.from.address' => 'hello@example.com',
            'session.secure' => false, 'session.encrypt' => false,
        ]);
        $this->artisan('app:preflight')->assertFailed();

        File::ensureDirectoryExists($this->dir);
        File::put($this->dir.'/last-success.json', json_encode(['finished_at' => now()->toIso8601String()]));
        config([
            'app.debug' => false, 'app.url' => 'https://trilha.example.org', 'mail.default' => 'smtp', 'mail.from.address' => 'no-reply@trilha.example.org',
            'session.secure' => true, 'session.encrypt' => true, 'security.two_factor_required' => true,
        ]);
        $this->artisan('app:preflight')->assertSuccessful();
    }

    public function test_preflight_reports_a_stale_backup_as_critical_in_production(): void
    {
        $this->app['env'] = 'production';
        config([
            'app.debug' => false, 'app.url' => 'https://trilha.example.org', 'mail.default' => 'smtp', 'mail.from.address' => 'no-reply@trilha.example.org',
            'session.secure' => true, 'session.encrypt' => true, 'security.two_factor_required' => true,
        ]);
        File::ensureDirectoryExists($this->dir);
        File::put($this->dir.'/last-success.json', json_encode(['finished_at' => now()->subDays(3)->toIso8601String()]));

        $this->artisan('app:preflight')->assertFailed();
    }

    public function test_mail_test_command_sends_a_message_and_rejects_bad_addresses(): void
    {
        config(['mail.default' => 'array']);

        $this->artisan('mail:test', ['to' => 'pessoa@example.org'])->assertSuccessful();
        $this->artisan('mail:test', ['to' => 'isto-nao-e-email'])->assertFailed();
    }

    public function test_smtp_failure_during_password_recovery_does_not_break_the_page_or_leak_account_existence(): void
    {
        $user = User::factory()->create();
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => '127.0.0.1',
            'mail.mailers.smtp.port' => 1,
            'mail.mailers.smtp.scheme' => null,
            'mail.mailers.smtp.timeout' => 2,
        ]);

        $existing = $this->post('/forgot-password', ['email' => $user->email]);
        $unknown = $this->post('/forgot-password', ['email' => 'ninguem@example.org']);

        $existing->assertSessionHasNoErrors()->assertSessionHas('status');
        $this->assertSame(session('status'), $unknown->getSession()->get('status'));
        $unknown->assertSessionHasNoErrors();
    }

    public function test_analyze_counts_rows_even_when_text_contains_parentheses_and_quotes(): void
    {
        foreach (['valor ),( enganoso', "aspa ' simples (", 'normal'] as $i => $name) {
            Company::factory()->create(['name' => $name, 'slug' => 'c-'.$i]);
        }
        File::ensureDirectoryExists($this->dir);
        $file = $this->dir.'/db-test.sql.gz';
        $stats = (new DatabaseDumper)->dump(DB::connection(), $file);

        $analysis = (new SqlRestorer)->analyze($file, DB::connection()->getDriverName() !== 'sqlite');

        $this->assertSame($stats['tables']['companies'], $analysis['companies']);
        $this->assertSame(3, $analysis['companies']);
    }

    public function test_encryption_round_trips_and_rejects_tampering_truncation_and_wrong_keys(): void
    {
        File::ensureDirectoryExists($this->dir);
        $plain = $this->dir.'/plain.bin';
        File::put($plain, random_bytes(200_000)); // mais de um bloco de 64 KB
        $key = sodium_crypto_secretstream_xchacha20poly1305_keygen();
        $crypto = new BackupCrypto;

        $crypto->encryptFile($plain, $this->dir.'/c.enc', $key);
        $crypto->decryptFile($this->dir.'/c.enc', $this->dir.'/out.bin', $key);
        $this->assertSame(hash_file('sha256', $plain), hash_file('sha256', $this->dir.'/out.bin'));
        $this->assertNotSame(File::get($plain), File::get($this->dir.'/c.enc'));

        $bytes = File::get($this->dir.'/c.enc');
        File::put($this->dir.'/tampered.enc', substr_replace($bytes, chr(ord($bytes[100]) ^ 1), 100, 1));
        File::put($this->dir.'/truncated.enc', substr($bytes, 0, strlen($bytes) - 70_000));

        foreach ([['tampered.enc', $key], ['truncated.enc', $key], ['c.enc', sodium_crypto_secretstream_xchacha20poly1305_keygen()]] as [$name, $useKey]) {
            try {
                $crypto->decryptFile($this->dir.'/'.$name, $this->dir.'/never.bin', $useKey);
                $this->fail("{$name} deveria ser rejeitado");
            } catch (RuntimeException $exception) {
                $this->assertNotSame('', $exception->getMessage());
            }
        }
    }

    public function test_encrypted_backup_is_verified_and_a_corrupted_one_is_caught(): void
    {
        Company::factory()->create(['name' => 'Empresa Cifrada', 'slug' => 'cifrada']);
        config(['backup.encryption_key' => BackupCrypto::generateKey()]);

        $this->artisan('backup:run', ['--no-files' => true])->assertSuccessful();

        $this->assertEmpty(glob($this->dir.'/db-*.sql.gz'), 'o dump em texto aberto não pode sobrar');
        $encrypted = glob($this->dir.'/db-*.sql.gz.enc');
        $this->assertCount(1, $encrypted);
        $this->artisan('backup:verify')->assertSuccessful();
        $this->assertFileExists($this->dir.'/last-verify.json');

        $bytes = File::get($encrypted[0]);
        File::put($encrypted[0], substr_replace($bytes, chr(ord($bytes[50]) ^ 1), 50, 1));
        $this->artisan('backup:verify')->assertFailed();
    }

    public function test_verify_fails_when_the_dump_no_longer_matches_the_manifest(): void
    {
        Company::factory()->create();
        $this->artisan('backup:run', ['--no-files' => true])->assertSuccessful();
        $this->artisan('backup:verify')->assertSuccessful();

        $set = BackupSet::latest($this->dir);
        $manifest = $set->manifest;
        $manifest['tables']['companies'] += 5; // o manifesto promete mais linhas do que o dump tem
        $path = BackupSet::manifestPath($this->dir, $set->stamp);
        File::put($path, json_encode($manifest));

        $this->artisan('backup:verify')->assertFailed();
    }

    public function test_restore_refuses_a_file_whose_checksum_does_not_match_the_manifest(): void
    {
        Company::factory()->create();
        $this->artisan('backup:run', ['--no-files' => true])->assertSuccessful();
        $file = glob($this->dir.'/db-*.sql.gz')[0];
        File::append($file, 'lixo');

        $this->artisan('backup:restore', ['file' => $file, '--force' => true])->assertFailed();
    }
}
