<?php

namespace App\Support\Backup;

use Illuminate\Database\Connection;
use RuntimeException;

/** Lê um dump gerado por {@see DatabaseDumper} e o executa, instrução por instrução. */
class SqlRestorer
{
    /** @return int número de instruções executadas */
    public function restore(Connection $connection, string $gzPath): int
    {
        $handle = gzopen($gzPath, 'rb');
        if ($handle === false) {
            throw new RuntimeException("Não foi possível abrir o backup {$gzPath}.");
        }

        $pdo = $connection->getPdo();
        // MySQL trata a barra invertida como escape dentro de strings; SQLite não.
        $backslashEscapes = $connection->getDriverName() !== 'sqlite';
        $statement = '';
        $inQuote = false;
        $executed = 0;

        try {
            while (! gzeof($handle)) {
                $chunk = gzread($handle, 65536);
                if ($chunk === false) {
                    break;
                }

                $length = strlen($chunk);
                for ($i = 0; $i < $length; $i++) {
                    $char = $chunk[$i];
                    $statement .= $char;

                    if ($inQuote) {
                        if ($backslashEscapes && $char === '\\' && $i + 1 < $length) {
                            $statement .= $chunk[++$i];
                        } elseif ($char === "'") {
                            $inQuote = false;
                        }
                        continue;
                    }

                    if ($char === "'") {
                        $inQuote = true;
                    } elseif ($char === ';') {
                        $executed += $this->run($pdo, $statement);
                        $statement = '';
                    }
                }
            }

            $executed += $this->run($pdo, $statement);
        } finally {
            gzclose($handle);
        }

        return $executed;
    }

    private function run(\PDO $pdo, string $statement): int
    {
        $statement = trim($statement);
        // Ignora comentários de cabeçalho e linhas vazias.
        $statement = preg_replace('/^(--[^\n]*\n)+/', '', $statement) ?? $statement;
        $statement = trim($statement);

        if ($statement === '' || $statement === ';') {
            return 0;
        }

        $pdo->exec($statement);

        return 1;
    }
}
