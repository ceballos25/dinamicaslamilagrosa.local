<?php
declare(strict_types=1);

namespace App\Application\Comprobante\Service;

use App\Application\Comprobante\Contract\ReferenceExtractorInterface;
use App\Application\Comprobante\ReferenceNormalizer;

final class ColombianBankReferenceExtractor implements ReferenceExtractorInterface
{
    /**
     * @var list<array{pattern: string, bank: string|null}>
     */
    private const PATTERNS = [
        ['pattern' => '/referencia(?:\s+de)?[:\s#-]*([A-Z0-9][A-Z0-9\-]{5,24})/iu', 'bank' => null],
        ['pattern' => '/num(?:ero|éro)?\.?\s*de\s*autorizaci[oó]n[:\s#-]*([0-9]{6,20})/iu', 'bank' => null],
        ['pattern' => '/comprobante(?:\s+no\.?|\s+n[°º])?[:\s#-]*([A-Z0-9][A-Z0-9\-]{5,24})/iu', 'bank' => null],
        ['pattern' => '/id(?:\s+de)?\s*transacci[oó]n[:\s#-]*([A-Z0-9]{6,24})/iu', 'bank' => null],
        ['pattern' => '/transacci[oó]n[:\s#-]*([0-9]{8,20})/iu', 'bank' => null],
        ['pattern' => '/n[uú]mero\s+de\s+aprobaci[oó]n[:\s#-]*([0-9]{6,20})/iu', 'bank' => null],
        ['pattern' => '/codigo(?:\s+de)?\s+seguridad[:\s#-]*([0-9]{6,20})/iu', 'bank' => null],
        ['pattern' => '/\b([0-9]{10,20})\b/u', 'bank' => null],
    ];

    public function extract(string $ocrText): array
    {
        $text = preg_replace('/\s+/u', ' ', trim($ocrText)) ?? '';
        if ($text === '') {
            return ['raw' => null, 'normalized' => null, 'bank' => null];
        }

        $bestRaw = null;
        $bestNorm = null;
        $bestBank = null;
        $bestWeight = 0;

        foreach (self::PATTERNS as $index => $item) {
            if (!preg_match($item['pattern'], $text, $matches)) {
                continue;
            }

            $raw = trim((string)($matches[1] ?? ''));
            $norm = ReferenceNormalizer::normalize($raw);
            if ($norm === null) {
                continue;
            }

            $weight = 100 - $index;
            if ($weight <= $bestWeight) {
                continue;
            }

            $bestWeight = $weight;
            $bestRaw = $raw;
            $bestNorm = $norm;
            $bestBank = $item['bank'];
        }

        return [
            'raw' => $bestRaw,
            'normalized' => $bestNorm,
            'bank' => $bestBank,
        ];
    }
}
