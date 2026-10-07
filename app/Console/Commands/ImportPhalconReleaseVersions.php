<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PDO;
use Throwable;

class ImportPhalconReleaseVersions extends Command
{
    protected $signature = 'managect:import-phalcon-releases {--dry-run : Validar e informar sem gravar dados}';

    protected $description = 'Importa o histórico de versões publicadas do banco Phalcon';

    public function handle(): int
    {
        $sourceConfig = config('phalcon.source');
        $password = (string) $sourceConfig['password'];
        if ($password === '') {
            $this->error('Defina PHALCON_SOURCE_PASSWORD no ambiente antes de iniciar a importação.');

            return self::FAILURE;
        }

        try {
            $source = new PDO(sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $sourceConfig['host'], $sourceConfig['port'], $sourceConfig['database']), $sourceConfig['username'], $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            $records = $source->query('SELECT branch_name, commit_sha, commit_message, implemented_notes, fixed_notes, updated_notes, executed_by, released_at, created_at FROM release_versions ORDER BY released_at, id')->fetchAll();

            if ($this->option('dry-run')) {
                $this->info(sprintf('Validação concluída: %d versão(ões) prontas para importação.', count($records)));

                return self::SUCCESS;
            }

            foreach (array_chunk($records, 250) as $chunk) {
                DB::table('release_versions')->upsert($chunk, ['branch_name', 'commit_sha'], [
                    'commit_message', 'implemented_notes', 'fixed_notes', 'updated_notes', 'executed_by', 'released_at',
                ]);
            }

            $this->info(sprintf('Importação concluída: %d versão(ões) processadas.', count($records)));

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Importação cancelada. Consulte o log para detalhes técnicos.');

            return self::FAILURE;
        }
    }
}
