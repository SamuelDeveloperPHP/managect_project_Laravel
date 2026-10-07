<?php

namespace App\Console\Commands;

use App\Support\Backup\SqlRestorer;
use Illuminate\Console\Command;

class BackupRestore extends Command
{
    protected $signature = 'backup:restore {file : Caminho do arquivo db-*.sql.gz} {--force : Confirma que o banco atual será SOBRESCRITO}';

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

        $executed = $restorer->restore($connection, $file);
        $this->info("Restauração concluída: {$executed} instrução(ões) executada(s) em \"{$target}\".");

        return self::SUCCESS;
    }
}
