<?php

namespace App\Support\Backup;

use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * Um "conjunto" de backup é o que um backup:run gera: o dump do banco, o .zip de arquivos (opcional)
 * e o manifesto com checksums e a contagem de linhas por tabela.
 */
class BackupSet
{
    /** @param array<string, mixed> $manifest */
    public function __construct(public readonly string $directory, public readonly string $stamp, public array $manifest = []) {}

    public static function manifestPath(string $directory, string $stamp): string
    {
        return rtrim($directory, '/\\').DIRECTORY_SEPARATOR."manifest-{$stamp}.json";
    }

    public static function latest(string $directory): ?self
    {
        $manifests = glob(rtrim($directory, '/\\').DIRECTORY_SEPARATOR.'manifest-*.json') ?: [];
        rsort($manifests);

        return $manifests === [] ? null : self::fromManifest($manifests[0]);
    }

    public static function fromManifest(string $path): self
    {
        $data = json_decode((string) File::get($path), true);
        if (! is_array($data) || ! isset($data['stamp'])) {
            throw new RuntimeException("Manifesto inválido: {$path}");
        }

        return new self(dirname($path), (string) $data['stamp'], $data);
    }

    public function path(string $file): string
    {
        return $this->directory.DIRECTORY_SEPARATOR.$file;
    }

    /** @return array<string, array{sha256: string, bytes: int}> */
    public function files(): array
    {
        return (array) ($this->manifest['files'] ?? []);
    }

    /** Confere o sha256 de cada arquivo do conjunto. @return list<string> problemas encontrados */
    public function checksumProblems(): array
    {
        $problems = [];
        foreach ($this->files() as $name => $info) {
            $path = $this->path($name);
            if (! is_file($path)) {
                $problems[] = "Arquivo ausente: {$name}";
            } elseif (! hash_equals((string) $info['sha256'], hash_file('sha256', $path))) {
                $problems[] = "Checksum diferente (arquivo alterado ou corrompido): {$name}";
            }
        }

        return $problems;
    }

    public function databaseFile(): ?string
    {
        foreach (array_keys($this->files()) as $name) {
            if (str_starts_with($name, 'db-')) {
                return $name;
            }
        }

        return null;
    }
}
