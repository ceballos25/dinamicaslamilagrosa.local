<?php
declare(strict_types=1);

namespace App\Shared\Date;

/**
 * Formato corto de fecha de evento para la landing y comprobantes: "10 agosto".
 */
final class EventDateFormatter
{
    private const MONTHS = [
        1 => 'enero',
        2 => 'febrero',
        3 => 'marzo',
        4 => 'abril',
        5 => 'mayo',
        6 => 'junio',
        7 => 'julio',
        8 => 'agosto',
        9 => 'septiembre',
        10 => 'octubre',
        11 => 'noviembre',
        12 => 'diciembre',
    ];

    public static function formatDayMonth(?string $raw): string
    {
        $raw = trim((string)($raw ?? ''));
        if ($raw === '') {
            return '';
        }

        $tz = new \DateTimeZone('America/Bogota');
        $normalized = str_replace('T', ' ', $raw);

        $dt = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $normalized, $tz);
        if ($dt === false) {
            $dt = \DateTimeImmutable::createFromFormat('!Y-m-d', $normalized, $tz);
        }
        if ($dt === false) {
            try {
                $dt = new \DateTimeImmutable($normalized, $tz);
            } catch (\Exception) {
                return '';
            }
        }

        $day = (int)$dt->format('j');
        $month = (int)$dt->format('n');

        if ($day < 1 || $month < 1 || $month > 12) {
            return '';
        }

        return $day . ' de ' . mb_convert_case(self::MONTHS[$month], MB_CASE_TITLE, 'UTF-8');
    }
}
