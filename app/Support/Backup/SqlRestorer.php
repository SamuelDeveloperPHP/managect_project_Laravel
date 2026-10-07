<?php

namespace App\Support\Backup;

use Illuminate\Database\Connection;
use RuntimeException;

/** Lê um dump gerado por {@see DatabaseDumper}: executa (restore) ou apenas confere o conteúdo (analyze). */
class SqlRestorer
{
    /** @return int número de instruções executadas */
    public function restore(Connection $connection, string $gzPath): int
    {
        $pdo = $connection->getPdo();
        $executed = 0;

        $this->walk($gzPath, $connection->getDriverName() !== 'sqlite', function (string $statement) use ($pdo, &$executed): void {
            $pdo->exec($statement);
            $executed++;
        });

        return $executed;
    }

    /**
     * Percorre o dump SEM executar nada e conta as tabelas e as linhas inseridas por tabela.
     * Serve para provar que o arquivo está completo e legível (compare com o manifesto do backup).
     *
     * @return array<string, int> linhas por tabela (tabelas só com estrutura aparecem com 0)
     */
    public function analyze(string $gzPath, bool $backslashEscapes = true): array
    {
        $tables = [];

        $this->walk($gzPath, $backslashEscapes, function (string $statement) use (&$tables, $backslashEscapes): void {
            if (preg_match('/^CREATE TABLE\s+(?:IF NOT EXISTS\s+)?(`(?:[^`]|``)+`|"(?:[^"]|"")+"|\w+)/i', $statement, $m)) {
                $tables[$this->unquote($m[1])] ??= 0;
            } elseif (preg_match('/^INSERT INTO\s+(`(?:[^`]|``)+`|"(?:[^"]|"")+"|\w+)/i', $statement, $m)) {
                $name = $this->unquote($m[1]);
                $tables[$name] = ($tables[$name] ?? 0) + $this->countTuples($statement, $backslashEscapes);
            }
        });

        return $tables;
    }

    private function unquote(string $identifier): string
    {
        $quote = $identifier[0];
        if ($quote === '`' || $quote === '"') {
            return str_replace($quote.$quote, $quote, substr($identifier, 1, -1));
        }

        return $identifier;
    }

    /** @param callable(string): void $onStatement */
    private function walk(string $gzPath, bool $backslashEscapes, callable $onStatement): void
    {
        $handle = gzopen($gzPath, 'rb');
        if ($handle === false) {
            throw new RuntimeException("Não foi possível abrir o backup {$gzPath}.");
        }

        $statement = '';
        $inQuote = false;

        try {
            while (! gzeof($handle)) {
                $chunk = gzread($handle, 65536);
                if ($chunk === false) {
                    throw new RuntimeException('Backup corrompido: falha ao descompactar.');
                }

                $length = strlen($chunk);
                for ($i = 0; $i < $length; $i++) {
                    $char = $chunk[$i];
                    $statement .= $char;

                    if ($inQuote) {
                        if ($backslashEscapes && $char === '\\') {
                            if ($i + 1 < $length) {
                                $statement .= $chunk[++$i];
                            } else {
                                // a barra ficou no fim do bloco: o caractere escapado está no próximo
                                $next = gzread($handle, 1);
                                $statement .= $next === false ? '' : $next;
                            }
                        } elseif ($char === "'") {
                            $inQuote = false;
                        }

                        continue;
                    }

                    if ($char === "'") {
                        $inQuote = true;
                    } elseif ($char === ';') {
                        $this->emit($statement, $onStatement);
                        $statement = '';
                    }
                }
            }
        } finally {
            gzclose($handle);
        }

        if ($inQuote) {
            throw new RuntimeException('Backup truncado: texto aberto sem fechamento.');
        }
        $this->emit($statement, $onStatement);
    }

    private function emit(string $statement, callable $onStatement): void
    {
        $statement = trim($statement);
        // Ignora comentários de cabeçalho e linhas vazias.
        $statement = trim(preg_replace('/^(--[^\n]*\n)+/', '', $statement) ?? $statement);

        if ($statement !== '' && $statement !== ';') {
            $onStatement($statement);
        }
    }

    /** Conta as tuplas "(...)" de um INSERT ... VALUES, ignorando parênteses dentro de textos. */
    private function countTuples(string $statement, bool $backslashEscapes): int
    {
        $start = stripos($statement, ' VALUES');
        if ($start === false) {
            return 0;
        }

        $count = 0;
        $depth = 0;
        $inQuote = false;
        $length = strlen($statement);

        for ($i = $start + 7; $i < $length; $i++) {
            $char = $statement[$i];

            if ($inQuote) {
                if ($backslashEscapes && $char === '\\') {
                    $i++;
                } elseif ($char === "'") {
                    $inQuote = false;
                }

                continue;
            }

            if ($char === "'") {
                $inQuote = true;
            } elseif ($char === '(') {
                if ($depth === 0) {
                    $count++;
                }
                $depth++;
            } elseif ($char === ')') {
                $depth--;
            }
        }

        return $count;
    }
}
