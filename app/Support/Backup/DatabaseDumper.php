<?php

namespace App\Support\Backup;

use Illuminate\Database\Connection;
use PDO;
use RuntimeException;

/**
 * Dump SQL em PHP puro (sem depender de mysqldump, que costuma não existir em hospedagem compartilhada).
 * Suporta MySQL/MariaDB (InnoDB) e SQLite. O arquivo gerado é lido de volta por {@see SqlRestorer}.
 *
 * Consistência: o dump inteiro roda dentro de uma leitura transacional ("retrato" do banco), então uma
 * gravação feita por um usuário durante o backup não deixa o arquivo com tabelas de momentos diferentes.
 * Memória: no MySQL as linhas são lidas sem buffer, uma a uma, e a memória não cresce com o tamanho da tabela.
 */
class DatabaseDumper
{
    private const ROWS_PER_INSERT = 100;

    /**
     * @param  list<string>  $skipData  tabelas cujos dados não entram no dump
     * @return array{tables: array<string, int>, rows: int, bytes: int} linhas gravadas por tabela
     */
    public function dump(Connection $connection, string $gzPath, array $skipData = []): array
    {
        $driver = $connection->getDriverName();
        if (! in_array($driver, ['mysql', 'mariadb', 'sqlite'], true)) {
            throw new RuntimeException("Driver de banco sem suporte para backup: {$driver}.");
        }

        $handle = gzopen($gzPath, 'wb9');
        if ($handle === false) {
            throw new RuntimeException("Não foi possível criar o arquivo de backup em {$gzPath}.");
        }

        $pdo = $connection->getPdo();
        $snapshot = $this->beginSnapshot($pdo, $driver);
        $tables = [];
        $total = 0;

        try {
            gzwrite($handle, '-- Trilha+ backup '.now()->toIso8601String()."\n");
            foreach ($this->header($driver) as $line) {
                gzwrite($handle, $line."\n");
            }

            foreach ($this->tables($connection, $driver) as $table) {
                $tables[$table] = 0;
                foreach ($this->createStatements($connection, $driver, $table) as $statement) {
                    gzwrite($handle, $statement.";\n");
                }

                if (in_array($table, $skipData, true)) {
                    continue;
                }

                $buffer = [];
                $columns = null;
                foreach ($this->rows($pdo, $driver, $table) as $record) {
                    $columns ??= implode(', ', array_map(fn ($c) => $this->identifier((string) $c), array_keys($record)));
                    $buffer[] = '('.implode(', ', array_map(fn ($v) => $this->literal($pdo, $v), array_values($record))).')';
                    $tables[$table]++;
                    $total++;

                    if (count($buffer) >= self::ROWS_PER_INSERT) {
                        gzwrite($handle, $this->insert($table, $columns, $buffer));
                        $buffer = [];
                    }
                }
                if ($buffer !== []) {
                    gzwrite($handle, $this->insert($table, $columns, $buffer));
                }
            }

            foreach ($this->footer($driver) as $line) {
                gzwrite($handle, $line."\n");
            }
        } finally {
            $this->endSnapshot($pdo, $snapshot);
            gzclose($handle);
        }

        $bytes = (int) filesize($gzPath);
        if ($bytes === 0) {
            throw new RuntimeException('O dump gerado está vazio.');
        }

        return ['tables' => $tables, 'rows' => $total, 'bytes' => $bytes];
    }

    /** Abre a leitura consistente. Retorna true se esta chamada abriu a transação (e deve fechá-la). */
    private function beginSnapshot(PDO $pdo, string $driver): bool
    {
        if ($pdo->inTransaction()) {
            return false; // já dentro de uma transação do chamador (ex.: testes): ela já fornece o isolamento
        }

        $pdo->exec($driver === 'sqlite' ? 'BEGIN' : 'START TRANSACTION WITH CONSISTENT SNAPSHOT');

        return true;
    }

    private function endSnapshot(PDO $pdo, bool $opened): void
    {
        if ($opened && $pdo->inTransaction()) {
            $pdo->exec('COMMIT');
        }
    }

    /** @return \Generator<int, array<string, mixed>> */
    private function rows(PDO $pdo, string $driver, string $table): \Generator
    {
        $unbuffered = $driver !== 'sqlite';
        if ($unbuffered) {
            $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
        }

        try {
            $statement = $pdo->query('SELECT * FROM '.$this->identifier($table));
            while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
                yield $row;
            }
            $statement->closeCursor();
        } finally {
            if ($unbuffered) {
                $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
            }
        }
    }

    /** @return list<string> */
    private function header(string $driver): array
    {
        return $driver === 'sqlite'
            ? ['PRAGMA foreign_keys=OFF;']
            : ['SET NAMES utf8mb4;', 'SET FOREIGN_KEY_CHECKS=0;', "SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';"];
    }

    /** @return list<string> */
    private function footer(string $driver): array
    {
        return $driver === 'sqlite' ? ['PRAGMA foreign_keys=ON;'] : ['SET FOREIGN_KEY_CHECKS=1;'];
    }

    /** @return list<string> */
    private function tables(Connection $connection, string $driver): array
    {
        if ($driver === 'sqlite') {
            $rows = $connection->select("select name from sqlite_master where type = 'table' and name not like 'sqlite_%' order by name");

            return array_map(fn ($row) => $row->name, $rows);
        }

        $rows = $connection->select("show full tables where Table_type = 'BASE TABLE'");

        return array_map(fn ($row) => (string) array_values((array) $row)[0], $rows);
    }

    /** @return list<string> */
    private function createStatements(Connection $connection, string $driver, string $table): array
    {
        $quoted = $this->identifier($table);

        if ($driver === 'sqlite') {
            $statements = ["DROP TABLE IF EXISTS {$quoted}"];
            $create = $connection->selectOne("select sql from sqlite_master where type = 'table' and name = ?", [$table]);
            $statements[] = (string) $create->sql;
            foreach ($connection->select("select sql from sqlite_master where type = 'index' and tbl_name = ? and sql is not null", [$table]) as $index) {
                $statements[] = (string) $index->sql;
            }

            return $statements;
        }

        $create = (array) $connection->selectOne("show create table {$quoted}");

        return ["DROP TABLE IF EXISTS {$quoted}", (string) ($create['Create Table'] ?? array_values($create)[1])];
    }

    private function insert(string $table, string $columns, array $values): string
    {
        return 'INSERT INTO '.$this->identifier($table)." ({$columns}) VALUES\n".implode(",\n", $values).";\n";
    }

    private function identifier(string $name): string
    {
        return '`'.str_replace('`', '``', $name).'`';
    }

    private function literal(PDO $pdo, mixed $value): string
    {
        return match (true) {
            $value === null => 'NULL',
            is_bool($value) => $value ? '1' : '0',
            is_int($value) => (string) $value,
            is_float($value) => rtrim(rtrim(sprintf('%.14F', $value), '0'), '.') ?: '0',
            default => (string) $pdo->quote((string) $value),
        };
    }
}
