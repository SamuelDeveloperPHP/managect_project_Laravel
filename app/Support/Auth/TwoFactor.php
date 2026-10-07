<?php

namespace App\Support\Auth;

use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use PragmaRX\Google2FA\Google2FA;

/**
 * Regras do segundo fator (TOTP, RFC 6238): segredo, QR code, verificação com proteção contra reuso
 * e códigos de recuperação de uso único.
 */
class TwoFactor
{
    /** Janela de tolerância: o passo atual e 1 passo (30 s) para cada lado, para relógios levemente fora de hora. */
    private const WINDOW = 1;

    public function __construct(private readonly Google2FA $engine) {}

    public function newSecret(): string
    {
        return $this->engine->generateSecretKey(32);
    }

    public function otpAuthUrl(User $user, string $secret): string
    {
        return $this->engine->getQRCodeUrl((string) config('security.two_factor_issuer'), $user->email, $secret);
    }

    /** QR code em SVG (sem depender de extensões de imagem do PHP). */
    public function qrCodeSvg(User $user, string $secret): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle(200, 1), new SvgImageBackEnd));
        $svg = $writer->writeString($this->otpAuthUrl($user, $secret));

        return trim((string) preg_replace('/^<\?xml[^>]*\?>\s*/', '', $svg));
    }

    /**
     * Confere o código do app. Retorna o passo de tempo usado (para gravar) ou null se o código é inválido
     * ou já foi usado: um mesmo código nunca vale duas vezes.
     */
    public function verify(string $secret, string $code, ?int $lastUsedStep): ?int
    {
        $code = preg_replace('/\D+/', '', $code) ?? '';
        if (strlen($code) !== 6) {
            return null;
        }

        // ATENÇÃO: com $oldTimestamp nulo a biblioteca devolve apenas "true" (sem o passo), o que desligaria a proteção
        // contra reuso no primeiro código. Passando 0 ela sempre devolve o número do passo aceito.
        $step = $this->engine->verifyKeyNewer($secret, $code, $lastUsedStep ?? 0, self::WINDOW);

        return is_int($step) ? $step : null;
    }

    /** @return list<string> códigos em texto claro (mostrados UMA vez; só os hashes são guardados) */
    public function generateRecoveryCodes(): array
    {
        $codes = [];
        for ($i = 0; $i < (int) config('security.recovery_codes'); $i++) {
            $raw = strtolower(bin2hex(random_bytes(5))); // 40 bits de entropia por código
            $codes[] = substr($raw, 0, 5).'-'.substr($raw, 5, 5);
        }

        return $codes;
    }

    public function hashRecoveryCode(string $code): string
    {
        return hash_hmac('sha256', $this->normalizeRecoveryCode($code), (string) config('app.key'));
    }

    /** @param list<string> $codes */
    public function hashRecoveryCodes(array $codes): array
    {
        return array_map(fn (string $code) => $this->hashRecoveryCode($code), $codes);
    }

    /**
     * Procura o código entre os guardados; se achar, devolve a lista SEM ele (uso único). Senão, null.
     *
     * @param  list<string>  $storedHashes
     * @return list<string>|null
     */
    public function consumeRecoveryCode(array $storedHashes, string $code): ?array
    {
        $candidate = $this->hashRecoveryCode($code);
        $found = null;
        foreach ($storedHashes as $index => $hash) {
            // sem "break": percorre todos para não revelar a posição pelo tempo de resposta
            if (hash_equals($hash, $candidate)) {
                $found = $index;
            }
        }

        if ($found === null) {
            return null;
        }

        unset($storedHashes[$found]);

        return array_values($storedHashes);
    }

    private function normalizeRecoveryCode(string $code): string
    {
        return strtolower(preg_replace('/[^a-z0-9]/i', '', $code) ?? '');
    }
}
