<?php
declare(strict_types=1);

namespace App\Application\Comprobante;

final class CopAmountComparer
{
    public static function equivalent(float $a, float $b): bool
    {
        return abs((int) round($a) - (int) round($b)) <= 1;
    }

    /**
     * True si el monto del comprobante cubre el esperado (exacto o sobrepago).
     * Tolera ±1 COP por redondeo OCR.
     */
    public static function coversExpected(float $detected, float $expected): bool
    {
        return ((int) round($detected)) + 1 >= (int) round($expected);
    }

    public static function parseCopAmountString(string $raw): ?float
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        $normalized = preg_replace('/[^\d,.]/', '', $raw) ?? '';
        if ($normalized === '') {
            return null;
        }

        if (preg_match('/,\d{1,2}$/', $normalized) === 1) {
            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '.', $normalized);
        } else {
            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '', $normalized);
        }

        if (!is_numeric($normalized)) {
            return null;
        }

        $value = (float) $normalized;

        return $value > 0 ? $value : null;
    }

    public static function formatCop(float $amount): string
    {
        return '$' . number_format((int) round($amount), 0, ',', '.');
    }
}
