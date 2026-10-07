<?php

namespace App\Console\Commands;

use App\Support\Backup\BackupCrypto;
use App\Support\Backup\BackupSet;
use App\Support\Backup\SqlRestorer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class BackupVerify extends Command
{
    protected $signature = 'backup:verify {manifest? : Caminho do manifest-*.json (padrão: o backup mais recente)}';

    protected $description = 'Prova que o backup é utilizável: confere checksums, descriptografa, descompacta e compara as linhas de cada tabela com o manifesto.';

    public function handle(SqlRestorer $restorer): int
    {
        $directory = (string) config('backup.path');
        $manifestArgument = $this->argument('manifest');
        $set = $manifestArgument ? BackupSet::fromManifest((string) $manifestArgument) : BackupSet::latest($directory);

        if ($set === null) {
            $this->error('Nenhum backup encontrado. Rode php artisan backup:run.');

            return self::FAILURE;
        }

        $this->line("Conjunto {$set->stamp} (criado em {$set->manifest['created_at']}).");
        $problems = $set->checksumProblems();

        $dbFile = $set->databaseFile();
        if ($dbFile === null) {
            $problems[] = 'O manifesto não lista o arquivo do banco.';
        } elseif ($problems === []) {
            $problems = [...$problems, ...$this->contentProblems($set, $dbFile, $restorer)];
        }

        if ($problems !== []) {
            foreach ($problems as $problem) {
                $this->error($problem);
            }
            Log::error('backup:verify reprovou o conjunto '.$set->stamp, ['problemas' => $problems]);

            return self::FAILURE;
        }

        $this->info(sprintf('Backup íntegro: %d tabelas e %d linhas conferidas com o manifesto.', count($set->manifest['tables']), $set->manifest['rows']));
        File::put(rtrim($directory, '/\\').DIRECTORY_SEPARATOR.'last-verify.json', json_encode(['verified_at' => now()->toIso8601String(), 'stamp' => $set->stamp]));

        return self::SUCCESS;
    }

    /** @return list<string> */
    private function contentProblems(BackupSet $set, string $dbFile, SqlRestorer $restorer): array
    {
        $path = $set->path($dbFile);
        $temporary = null;

        try {
            if (str_ends_with($dbFile, '.enc')) {
                $key = BackupCrypto::keyFromConfig();
                if ($key === null) {
                    return ['O backup é criptografado e BACKUP_ENCRYPTION_KEY não está configurada neste ambiente.'];
                }
                $temporary = $path.'.verify.tmp.gz';
                (new BackupCrypto())->decryptFile($path, $temporary, $key);
                $path = $temporary;
            }

            $actual = $restorer->analyze($path, ($set->manifest['driver'] ?? 'mysql') !== 'sqlite');
        } catch (\Throwable $exception) {
            return ['Não foi possível ler o dump: '.$exception->getMessage()];
        } finally {
            if ($temporary !== null) {
                @unlink($temporary);
            }
        }

        $problems = [];
        foreach ((array) $set->manifest['tables'] as $table => $rows) {
            if (! array_key_exists($table, $actual)) {
                $problems[] = "Tabela ausente no dump: {$table}";
            } elseif ($actual[$table] !== $rows) {
                $problems[] = "Tabela {$table}: {$actual[$table]} linhas no dump, {$rows} no manifesto.";
            }
        }

        return $problems;
    }
}
