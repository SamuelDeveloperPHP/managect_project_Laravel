<?php

namespace App\Console\Commands;

use App\Support\Backup\BackupCrypto;
use App\Support\Backup\BackupSet;
use App\Support\Backup\SqlRestorer;
use Illuminate\Console\Command;

class BackupRestore extends Command
{
    protected $signature = 'backup:restore {file : Caminho do arquivo db-*.sql.gz (ou .sql.gz.enc)} {--force : Confirma que o banco atual será SOBRESCRITO}';

    protected $description = 'Restaura um backup do banco, SOBRESCREVENDO as tabelas existentes. Use em um banco de teste primeiro.';

    public function handle(SqlRestorer $restorer): int
    {
        $file = (string) $this->argument('file');
        if (! is_file($file)) {
            $this->error("Arquivo não encontrado: {$file}");

            return self::FAILURE;
        }

        $connection = app('db')->connection();
        $target = $connection->getDatabaseName();

        if (! $this->option('force')) {
            $this->warn("Isto SOBRESCREVE as tabelas do banco \"{$target}\". Rode de novo com --force para confirmar.");

            return self::FAILURE;
        }

        $problem = $this->checksumProblem($file);
        if ($problem !== null) {
            $this->error($problem);

            return self::FAILURE;
        }

        $temporary = null;
        try {
            $path = $file;
            if (str_ends_with($file, '.enc')) {
                $key = BackupCrypto::keyFromConfig();
                if ($key === null) {
                    $this->error('O backup é criptografado: configure BACKUP_ENCRYPTION_KEY com a chave usada na criação.');

                    return self::FAILURE;
                }
                $temporary = $file.'.restore.tmp.gz';
                (new BackupCrypto)->decryptFile($file, $temporary, $key);
                $path = $temporary;
            }

            $executed = $restorer->restore($connection, $path);
        } catch (\Throwable $exception) {
            $this->error('Restauração falhou: '.$exception->getMessage());

            return self::FAILURE;
        } finally {
            if ($temporary !== null) {
                @unlink($temporary);
            }
        }

        $this->info("Restauração concluída: {$executed} instrução(ões) executada(s) em \"{$target}\".");

        return self::SUCCESS;
    }

    /** Se existir um manifesto ao lado do arquivo, o checksum precisa bater antes de tocar no banco. */
    private function checksumProblem(string $file): ?string
    {
        if (! preg_match('/db-(\d{8}-\d{6})\./', basename($file), $m)) {
            return null;
        }

        $manifest = BackupSet::manifestPath(dirname($file), $m[1]);
        if (! is_file($manifest)) {
            $this->warn('Manifesto não encontrado ao lado do arquivo: a integridade não foi conferida.');

            return null;
        }

        $expected = BackupSet::fromManifest($manifest)->files()[basename($file)]['sha256'] ?? null;
        if ($expected !== null && ! hash_equals($expected, hash_file('sha256', $file))) {
            return 'O checksum do arquivo não confere com o manifesto: o backup foi alterado ou está corrompido. Nada foi restaurado.';
        }

        return null;
    }
}
