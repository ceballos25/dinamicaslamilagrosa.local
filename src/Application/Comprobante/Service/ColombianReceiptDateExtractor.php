<?php
declare(strict_types=1);

namespace App\Application\Comprobante\Service;

use App\Application\Comprobante\Contract\DateExtractorInterface;

/**
 * Extrae la fecha de un comprobante de pago colombiano (Nequi, Bancolombia,
 * Daviplata, Bre-B, etc.) a partir del texto OCR. Devuelve la fecha normalizada
 * en formato Y-m-d y el fragmento crudo detectado.
 */
final class ColombianReceiptDateExtractor implements DateExtractorInterface
{
    /**
     * @var array<string, int>
     */
    private const MONTHS = [
        'enero' => 1, 'ene' => 1,
        'febrero' => 2, 'feb' => 2,
        'marzo' => 3, 'mar' => 3,
        'abril' => 4, 'abr' => 4,
        'mayo' => 5, 'may' => 5,
        'junio' => 6, 'jun' => 6,
        'julio' => 7, 'jul' => 7,
        'agosto' => 8, 'ago' => 8, 'agto' => 8,
        'septiembre' => 9, 'setiembre' => 9, 'sept' => 9, 'sep' => 9, 'set' => 9,
        'octubre' => 10, 'oct' => 10,
        'noviembre' => 11, 'nov' => 11,
        'diciembre' => 12, 'dic' => 12,
    ];

    public function extract(string $ocrText): array
    {
        $text = preg_replace('/\s+/u', ' ', trim($ocrText)) ?? '';
        if ($text === '') {
            return ['date' => null, 'raw' => null];
        }

        $normalized = $this->stripAccents(mb_strtolower($text));

        return $this->matchIso($normalized)
            ?? $this->matchTextualMonth($normalized)
            ?? $this->matchNumeric($normalized)
            ?? ['date' => null, 'raw' => null];
    }

    /**
     * @return array{date: string, raw: string}|null
     */
    private function matchIso(string $normalized): ?array
    {
        if (!preg_match('/(?<!\d)(\d{4})[\/\-.](\d{1,2})[\/\-.](\d{1,2})(?!\d)/u', $normalized, $m)) {
            return null;
        }

        return $this->build((int)$m[1], (int)$m[2], (int)$m[3], $m[0]);
    }

    /**
     * @return array{date: string, raw: string}|null
     */
    private function matchTextualMonth(string $normalized): ?array
    {
        $months = implode('|', array_keys(self::MONTHS));

        // "2 de julio de 2026", "02 julio 2026", "14 julio, 2026", "2-jul-2026"
        if (preg_match(
            '/\b(\d{1,2})\s*(?:de\s+)?(' . $months . ')[a-z]*\.?\s*(?:de\s+|,)?\s*(\d{4})\b/u',
            $normalized,
            $m
        )) {
            return $this->build((int)$m[3], self::MONTHS[$m[2]] ?? 0, (int)$m[1], $m[0]);
        }

        // "julio 2 de 2026", "julio 02, 2026", "jul 2 2026"
        if (preg_match('/\b(' . $months . ')[a-z]*\.?\s*(\d{1,2})[\s,]+(?:de\s+)?(\d{4})\b/u', $normalized, $m)) {
            return $this->build((int)$m[3], self::MONTHS[$m[1]] ?? 0, (int)$m[2], $m[0]);
        }

        return null;
    }

    /**
     * @return array{date: string, raw: string}|null
     */
    private function matchNumeric(string $normalized): ?array
    {
        if (!preg_match_all('/(?<!\d)(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{2,4})(?!\d)/u', $normalized, $all, PREG_SET_ORDER)) {
            return null;
        }

        foreach ($all as $m) {
            $a = (int)$m[1];
            $b = (int)$m[2];
            $year = $this->normalizeYear((int)$m[3], strlen($m[3]));

            // Formato colombiano por defecto: día/mes. Se corrige si es imposible.
            $day = $a;
            $month = $b;
            if ($b > 12 && $a <= 12) {
                $day = $b;
                $month = $a;
            }

            $built = $this->build($year, $month, $day, $m[0]);
            if ($built !== null) {
                return $built;
            }
        }

        return null;
    }

    private function normalizeYear(int $year, int $digits): int
    {
        if ($digits >= 4) {
            return $year;
        }

        return $year >= 70 ? 1900 + $year : 2000 + $year;
    }

    /**
     * @return array{date: string, raw: string}|null
     */
    private function build(int $year, int $month, int $day, string $raw): ?array
    {
        if ($month < 1 || $month > 12 || $day < 1 || $day > 31) {
            return null;
        }

        if ($year < 2000 || $year > 2100) {
            return null;
        }

        if (!checkdate($month, $day, $year)) {
            return null;
        }

        return [
            'date' => sprintf('%04d-%02d-%02d', $year, $month, $day),
            'raw' => trim($raw),
        ];
    }

    private function stripAccents(string $value): string
    {
        return strtr($value, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u',
        ]);
    }
}
