<?php

namespace App\Support\Backup;

use Illuminate\Database\Connection;
use RuntimeException;

/**
 * Dump SQL em PHP puro (sem depender de mysqldump, que costuma não existir em hospedagem compartilhada).
 * Suporta MySQL/MariaDB e SQLite. O arquivo gerado é lido de volta por {@see SqlRestorer}.
 */
class DatabaseDumper
{
    private const ROWS_PER_INSERT = 100;

    /**
     * @param  list<string>  $skipData  tabelas cujos dados não entram no dump
     * @return array{tables: int, rows: int, bytes: int}
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
        $tables = 0;
        $rows = 0;

        try {
            gzwrite($handle, '-- Trilha+ backup '.now()->toIso8601String()."\n");
            foreach ($this->header($driver) as $line) {
                gzwrite($handle, $line."\n");
            }

            foreach ($this->tables($connection, $driver) as $table) {
                $tables++;
                foreach ($this->createStatements($connection, $driver, $table) as $statement) {
                    gzwrite($handle, $statement.";\n");
                }

                if (in_array($table, $skipData, true)) {
                    continue;
                }

                $buffer = [];
                $columns = null;
                foreach ($connection->table($table)->cursor() as $record) {
                    $record = (array) $record;
                    $columns ??= implode(', ', array_map(fn ($c) => $this->identifier($c), array_keys($record)));
                    $buffer[] = '('.implode(', ', array_map(fn ($v) => $this->literal($pdo, $v), array_values($record))).')';
                    $rows++;

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
            gzclose($handle);
        }

        $bytes = (int) filesize($gzPath);
        if ($bytes === 0) {
            throw new RuntimeException('O dump gerado está vazio.');
        }

        return ['tables' => $tables, 'rows' => $rows, 'bytes' => $bytes];
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

    private function literal(\PDO $pdo, mixed $value): string
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
