<?php

namespace App\Console\Commands;

use App\Support\Backup\BackupCrypto;
use App\Support\Backup\BackupSet;
use App\Support\Backup\DatabaseDumper;
use App\Support\Backup\SqlRestorer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class BackupRun extends Command
{
    protected $signature = 'backup:run {--no-files : Salva apenas o banco de dados}';

    protected $description = 'Gera o backup do banco (.sql.gz) e dos arquivos enviados (.zip), com manifesto e checksums, e aplica a retenção.';

    public function handle(DatabaseDumper $dumper, SqlRestorer $restorer, BackupCrypto $crypto): int
    {
        if (! config('backup.enabled')) {
            $this->warn('Backup desativado (BACKUP_ENABLED=false).');

            return self::SUCCESS;
        }

        $directory = (string) config('backup.path');
        File::ensureDirectoryExists($directory, 0750);
        // Nunca expõe a pasta pela web, mesmo se alguém apontar BACKUP_PATH para dentro de public/.
        File::put($directory.DIRECTORY_SEPARATOR.'.htaccess', "Require all denied\nDeny from all\n");

        $stamp = now()->format('Ymd-His');
        $created = [];

        try {
            $key = BackupCrypto::keyFromConfig();
            $connection = app('db')->connection();
            $backslashEscapes = $connection->getDriverName() !== 'sqlite';

            // 1) Banco: dump consistente + conferência imediata do arquivo gerado.
            $plain = $directory.DIRECTORY_SEPARATOR."db-{$stamp}.sql.gz";
            $created[] = $plain;
            $stats = $dumper->dump($connection, $plain, (array) config('backup.skip_data'));
            $analysis = $restorer->analyze($plain, $backslashEscapes);
            $this->assertDumpMatches($stats['tables'], $analysis);
            $this->info(sprintf('Banco: %d tabelas, %d linhas, %s (conferido).', count($stats['tables']), $stats['rows'], $this->size($stats['bytes'])));

            $artifacts = [$this->finalize($plain, $key, $crypto, $created)];

            // 2) Arquivos enviados pelos usuários.
            if (! $this->option('no-files')) {
                $zipFile = $directory.DIRECTORY_SEPARATOR."files-{$stamp}.zip";
                $created[] = $zipFile;
                $count = $this->archiveFiles($zipFile);
                if ($count !== null) {
                    $this->info(sprintf('Arquivos: %d arquivo(s), %s.', $count, $this->size((int) filesize($zipFile))));
                    $artifacts[] = $this->finalize($zipFile, $key, $crypto, $created);
                } else {
                    array_pop($created);
                }
            }

            // 3) Manifesto: checksums + linhas por tabela (é o que backup:verify compara depois).
            $manifest = [
                'stamp' => $stamp,
                'created_at' => now()->toIso8601String(),
                'app' => config('app.name'),
                'driver' => $connection->getDriverName(),
                'database' => $connection->getDatabaseName(),
                'encrypted' => $key !== null,
                'tables' => $stats['tables'],
                'rows' => $stats['rows'],
                'files' => collect($artifacts)->mapWithKeys(fn (string $path) => [
                    basename($path) => ['sha256' => hash_file('sha256', $path), 'bytes' => (int) filesize($path)],
                ])->all(),
            ];
            $manifestPath = BackupSet::manifestPath($directory, $stamp);
            File::put($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $created[] = $manifestPath;

            $this->copyOffsite([...$artifacts, $manifestPath]);
        } catch (\Throwable $exception) {
            foreach ($created as $file) {
                @unlink($file);
            }
            Log::error('Falha no backup: '.$exception->getMessage(), ['exception' => $exception]);
            $this->error('Backup falhou: '.$exception->getMessage());
            $this->notifyFailure($exception->getMessage());

            return self::FAILURE;
        }

        File::put($directory.DIRECTORY_SEPARATOR.'last-success.json', json_encode([
            'finished_at' => now()->toIso8601String(),
            'stamp' => $stamp,
        ], JSON_PRETTY_PRINT));

        $removed = $this->prune($directory);
        $this->info("Backup concluído. {$removed} arquivo(s) antigo(s) removido(s).");

        return self::SUCCESS;
    }

    /** Criptografa o arquivo (se houver chave) e devolve o caminho final. @param list<string> $created */
    private function finalize(string $path, ?string $key, BackupCrypto $crypto, array &$created): string
    {
        if ($key === null) {
            return $path;
        }

        $encrypted = $path.'.enc';
        $created[] = $encrypted;
        $crypto->encryptFile($path, $encrypted, $key);
        // Garante que o arquivo cifrado realmente abre antes de apagar o original.
        $probe = $encrypted.'.probe';
        try {
            $crypto->decryptFile($encrypted, $probe, (string) BackupCrypto::keyFromConfig());
            if (! hash_equals(hash_file('sha256', $path), hash_file('sha256', $probe))) {
                throw new \RuntimeException('A criptografia não reproduziu o arquivo original.');
            }
        } finally {
            @unlink($probe);
        }
        @unlink($path);

        return $encrypted;
    }

    /** @param array<string, int> $expected linhas gravadas por tabela; @param array<string, int> $actual contadas no arquivo */
    private function assertDumpMatches(array $expected, array $actual): void
    {
        foreach ($expected as $table => $rows) {
            if (! array_key_exists($table, $actual)) {
                throw new \RuntimeException("O dump não contém a tabela {$table}.");
            }
            if ($actual[$table] !== $rows) {
                throw new \RuntimeException("O dump da tabela {$table} tem {$actual[$table]} linhas, esperado {$rows}.");
            }
        }
    }

    /** @return int|null quantidade de arquivos, ou null se não foi possível arquivar */
    private function archiveFiles(string $zipFile): ?int
    {
        if (! class_exists(ZipArchive::class)) {
            $this->warn('Extensão zip do PHP indisponível: os arquivos enviados NÃO entraram no backup.');

            return null;
        }

        $zip = new ZipArchive;
        if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Não foi possível criar o .zip dos arquivos.');
        }

        $count = 0;
        foreach ((array) config('backup.file_dirs') as $dir) {
            if (! is_dir($dir)) {
                continue;
            }
            $base = basename(dirname($dir)).'/'.basename($dir);
            foreach (File::allFiles($dir, true) as $file) {
                $zip->addFile($file->getPathname(), $base.'/'.str_replace('\\', '/', $file->getRelativePathname()));
                $count++;
            }
        }
        $zip->close();

        if ($count === 0) {
            @unlink($zipFile);
            $this->line('Arquivos: nenhum arquivo enviado até agora.');

            return null;
        }

        return $count;
    }

    /** @param list<string> $files */
    private function copyOffsite(array $files): void
    {
        $disk = config('backup.copy_disk');
        if (! $disk) {
            $this->warn('BACKUP_COPY_DISK não configurado: o backup está só neste servidor.');

            return;
        }

        foreach ($files as $file) {
            $stream = fopen($file, 'rb');
            Storage::disk($disk)->put('trilha-backups/'.basename($file), $stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
        $this->info("Cópia externa enviada para o disco \"{$disk}\".");
    }

    private function notifyFailure(string $reason): void
    {
        $to = (string) config('backup.notify_email');
        if ($to === '') {
            return;
        }

        try {
            Mail::raw(
                'O backup automático do Trilha+ ('.config('app.url').') falhou em '.now()->format('d/m/Y H:i').".\n\nMotivo: {$reason}\n\nO último backup bom continua guardado. Rode php artisan backup:run para tentar de novo.",
                fn ($message) => $message->to($to)->subject('Trilha+ · FALHA no backup'),
            );
        } catch (\Throwable $exception) {
            Log::error('Não foi possível avisar a falha do backup por e-mail: '.$exception->getMessage());
        }
    }

    /** Remove conjuntos mais antigos que keep_days, mantendo ao menos keep_min. */
    private function prune(string $directory): int
    {
        $sets = [];
        foreach (File::files($directory) as $file) {
            if (preg_match('/^(db|files|manifest)-(\d{8}-\d{6})\./', $file->getFilename(), $m)) {
                $sets[$m[2]][] = $file->getPathname();
            }
        }
        krsort($sets);

        $limit = now()->subDays((int) config('backup.keep_days'))->format('Ymd-His');
        $removed = 0;
        $index = 0;
        foreach ($sets as $stamp => $paths) {
            $index++;
            if ($index > (int) config('backup.keep_min') && $stamp < $limit) {
                foreach ($paths as $path) {
                    $removed += @unlink($path) ? 1 : 0;
                }
            }
        }

        return $removed;
    }

    private function size(int $bytes): string
    {
        return $bytes < 1048576 ? round($bytes / 1024, 1).' KB' : round($bytes / 1048576, 1).' MB';
    }
}
