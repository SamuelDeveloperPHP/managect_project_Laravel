<?php

namespace App\Support;

final class BrazilianTaxDocument
{
    public static function isValid(string $type, string $value): bool
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';

        return match (strtoupper($type)) {
            'CPF' => self::isValidCpf($digits),
            'CNPJ' => self::isValidCnpj($digits),
            default => false,
        };
    }

    private static function isValidCpf(string $digits): bool
    {
        if (strlen($digits) !== 11 || preg_match('/^(\d)\1{10}$/', $digits)) {
            return false;
        }

        $first = self::modulo11Digit(substr($digits, 0, 9), range(10, 2));
        $second = self::modulo11Digit(substr($digits, 0, 9).$first, range(11, 2));

        return substr($digits, -2) === $first.$second;
    }

    private static function isValidCnpj(string $digits): bool
    {
        if (strlen($digits) !== 14 || preg_match('/^(\d)\1{13}$/', $digits)) {
            return false;
        }

        $first = self::modulo11Digit(substr($digits, 0, 12), [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]);
        $second = self::modulo11Digit(substr($digits, 0, 12).$first, [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]);

        return substr($digits, -2) === $first.$second;
    }

    private static function modulo11Digit(string $digits, array $weights): string
    {
        $sum = 0;
        foreach (str_split($digits) as $index => $digit) {
            $sum += (int) $digit * $weights[$index];
        }

        $remainder = $sum % 11;

        return (string) ($remainder < 2 ? 0 : 11 - $remainder);
    }
}
