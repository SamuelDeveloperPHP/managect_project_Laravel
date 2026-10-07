<?php

namespace App\Support\Backup;

use RuntimeException;

/**
 * Criptografia de arquivos de backup em fluxo (libsodium secretstream, XChaCha20-Poly1305).
 * Cada bloco é autenticado e o fim do arquivo também: arquivo adulterado ou cortado não é aceito.
 *
 * Formato: "TRBK1" + cabeçalho do stream + blocos [tamanho (4 bytes) + bloco cifrado].
 */
class BackupCrypto
{
    private const MAGIC = 'TRBK1';

    private const CHUNK = 65536;

    /** Lê a chave de BACKUP_ENCRYPTION_KEY (base64 de 32 bytes). Retorna null se a criptografia não está ativada. */
    public static function keyFromConfig(): ?string
    {
        $value = (string) config('backup.encryption_key');
        if ($value === '') {
            return null;
        }

        $key = base64_decode(str_starts_with($value, 'base64:') ? substr($value, 7) : $value, true);
        if ($key === false || strlen($key) !== SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES) {
            throw new RuntimeException('BACKUP_ENCRYPTION_KEY inválida: use "php artisan backup:key" para gerar uma chave de 32 bytes.');
        }

        return $key;
    }

    public static function generateKey(): string
    {
        return 'base64:'.base64_encode(sodium_crypto_secretstream_xchacha20poly1305_keygen());
    }

    public function encryptFile(string $source, string $destination, string $key): void
    {
        $in = fopen($source, 'rb');
        $out = fopen($destination, 'wb');
        if ($in === false || $out === false) {
            throw new RuntimeException('Não foi possível abrir os arquivos para criptografar.');
        }

        try {
            [$state, $header] = sodium_crypto_secretstream_xchacha20poly1305_init_push($key);
            fwrite($out, self::MAGIC.$header);

            $current = (string) fread($in, self::CHUNK);
            do {
                $next = (string) fread($in, self::CHUNK);
                $tag = $next === '' ? SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL : SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE;
                $cipher = sodium_crypto_secretstream_xchacha20poly1305_push($state, $current, '', $tag);
                fwrite($out, pack('N', strlen($cipher)).$cipher);
                $current = $next;
            } while ($tag !== SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL);
        } finally {
            fclose($in);
            fclose($out);
            sodium_memzero($key);
        }
    }

    public function decryptFile(string $source, string $destination, string $key): void
    {
        $in = fopen($source, 'rb');
        $out = fopen($destination, 'wb');
        if ($in === false || $out === false) {
            throw new RuntimeException('Não foi possível abrir os arquivos para descriptografar.');
        }

        try {
            $magic = fread($in, strlen(self::MAGIC));
            if ($magic !== self::MAGIC) {
                throw new RuntimeException('Arquivo não é um backup criptografado do Trilha+.');
            }

            $header = (string) fread($in, SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES);
            $state = sodium_crypto_secretstream_xchacha20poly1305_init_pull($header, $key);

            $finished = false;
            while (! feof($in) && ! $finished) {
                $prefix = fread($in, 4);
                if ($prefix === '' || $prefix === false) {
                    break;
                }
                $length = unpack('N', str_pad($prefix, 4, "\0"))[1];
                $cipher = (string) fread($in, $length);
                $plain = strlen($cipher) === $length ? sodium_crypto_secretstream_xchacha20poly1305_pull($state, $cipher) : false;
                if ($plain === false) {
                    throw new RuntimeException('Backup adulterado, truncado ou chave incorreta.');
                }
                fwrite($out, $plain[0]);
                $finished = $plain[1] === SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL;
            }

            if (! $finished) {
                throw new RuntimeException('Backup truncado: o marcador de fim não foi encontrado.');
            }
        } finally {
            fclose($in);
            fclose($out);
            sodium_memzero($key);
        }
    }
}
