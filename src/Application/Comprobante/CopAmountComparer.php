<?php
declare(strict_types=1);

namespace App\Application\Comprobante;

final class CopAmountComparer
{
    public static function equivalent(float $a, float $b): bool
    {
        return abs((int) round($a) - (int) round($b)) <= 1;
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
