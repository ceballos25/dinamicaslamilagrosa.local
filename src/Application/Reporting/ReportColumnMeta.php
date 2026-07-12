<?php
declare(strict_types=1);

namespace App\Application\Reporting;

/**
 * Metadatos de columnas (títulos y formatos) a partir del spec del constructor visual.
 */
final class ReportColumnMeta
{
    /**
     * @param array<string, mixed> $spec
     * @return array{labels: array<string, string>, formats: array<string, string>}
     */
    public static function fromSpec(array $spec): array
    {
        $labels = [];
        $formats = [];

        foreach ($spec['dimensions'] ?? [] as $dim) {
            if (!is_array($dim)) {
                continue;
            }
            $field = (string)($dim['field'] ?? '');
            $alias = self::aliasFrom($dim, $field);
            if ($alias === '') {
                continue;
            }
            $labels[$alias] = ReportDisplayFormatter::labelForField($field);
            $formats[$alias] = ReportDisplayFormatter::inferFormatForField($field, $alias);
        }

        foreach ($spec['measures'] ?? [] as $measure) {
            if (!is_array($measure)) {
                continue;
            }
            $fn = (string)($measure['fn'] ?? '');
            $field = (string)($measure['field'] ?? '');
            $alias = self::aliasFrom($measure, $field !== '' ? $field : 'n');
            if ($alias === '') {
                continue;
            }
            $labels[$alias] = ReportDisplayFormatter::labelForMeasure($fn, $field, $alias);
            $formats[$alias] = ReportDisplayFormatter::inferFormatForField(
                $field === '*' ? 'quantity_sale' : $field,
                $alias,
                $fn
            );
        }

        return ['labels' => $labels, 'formats' => $formats];
    }

    /**
     * @param array<string, mixed> $item
     */
    private static function aliasFrom(array $item, string $fallback): string
    {
        $alias = trim((string)($item['alias'] ?? $fallback));

        return preg_match('/^[a-zA-Z0-9_]{1,64}$/', $alias) ? $alias : '';
    }
}
