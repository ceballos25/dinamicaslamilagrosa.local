<?php
declare(strict_types=1);

namespace App\Application\Comprobante\Service;

use App\Application\Comprobante\Contract\AmountExtractorInterface;
use App\Application\Comprobante\CopAmountComparer;

final class ColombianReceiptAmountExtractor implements AmountExtractorInterface
{
    /**
     * @var list<array{pattern: string, weight: int}>
     */
    private const PATTERNS = [
        ['pattern' => '/(?:valor|monto|total|enviado|enviaste|pagado|pagaste|transferido|transferiste|cantidad)[:\s]*(?:cop)?[:\s]*\$?\s*([\d][\d.,]*)/iu', 'weight' => 100],
        ['pattern' => '/\$\s*([\d][\d.,]*)/u', 'weight' => 70],
        ['pattern' => '/\b([\d]{1,3}(?:\.[\d]{3})+(?:,\d{1,2})?)\b/u', 'weight' => 55],
        ['pattern' => '/\b([\d]{4,9})\b/u', 'weight' => 20],
    ];

    public function extract(string $ocrText, ?float $expectedAmountCop = null): array
    {
        $text = preg_replace('/\s+/u', ' ', trim($ocrText)) ?? '';
        if ($text === '') {
            return ['amount' => null, 'raw' => null];
        }

        $bestAmount = null;
        $bestRaw = null;
        $bestWeight = -1;

        foreach (self::PATTERNS as $item) {
            if (!preg_match_all($item['pattern'], $text, $matches, PREG_SET_ORDER)) {
                continue;
            }

            foreach ($matches as $match) {
                $raw = trim((string)($match[1] ?? ''));
                $amount = CopAmountComparer::parseCopAmountString($raw);
                if ($amount === null) {
                    continue;
                }

                $weight = $item['weight'];
                if ($expectedAmountCop !== null && CopAmountComparer::equivalent($amount, $expectedAmountCop)) {
                    $weight += 200;
                }

                if ($weight < $bestWeight) {
                    continue;
                }

                if ($weight === $bestWeight && $bestAmount !== null) {
                    if ($expectedAmountCop !== null) {
                        $currentDiff = abs((int) round($bestAmount) - (int) round($expectedAmountCop));
                        $candidateDiff = abs((int) round($amount) - (int) round($expectedAmountCop));
                        if ($candidateDiff >= $currentDiff) {
                            continue;
                        }
                    }
                }

                $bestWeight = $weight;
                $bestAmount = $amount;
                $bestRaw = $raw;
            }
        }

        return ['amount' => $bestAmount, 'raw' => $bestRaw];
    }
}
