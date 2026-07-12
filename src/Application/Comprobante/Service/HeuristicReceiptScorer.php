<?php
declare(strict_types=1);

namespace App\Application\Comprobante\Service;

use App\Application\Comprobante\Contract\ReceiptScorerInterface;

final class HeuristicReceiptScorer implements ReceiptScorerInterface
{
    /** @var list<string> */
    private const RECEIPT_KEYWORDS = [
        'comprobante', 'transferencia', 'transaccion', 'transacción', 'enviado', 'enviada',
        'recibido', 'recibida', 'aprobada', 'aprobado', 'exitosa', 'exitoso', 'pago', 'pagado',
        'valor', 'monto', 'referencia', 'autorizacion', 'autorización', 'destino', 'origen',
        'bre-b', 'breb', 'llave', 'consignacion', 'consignación',
    ];

    /** @var array<string, string> */
    private const BANK_KEYWORDS = [
        'nequi' => 'Nequi',
        'daviplata' => 'Daviplata',
        'bancolombia' => 'Bancolombia',
        'davivienda' => 'Davivienda',
        'bbva' => 'BBVA',
        'banco de bogota' => 'Banco de Bogotá',
        'banco de bogotá' => 'Banco de Bogotá',
        'banco caja social' => 'Banco Caja Social',
        'banco agrario' => 'Banco Agrario',
        'scotiabank' => 'Scotiabank',
        'banco popular' => 'Banco Popular',
        'banco occidente' => 'Banco de Occidente',
        'av villas' => 'AV Villas',
        'colpatria' => 'Colpatria',
        'pse' => 'PSE',
        'transfiya' => 'Transfiya',
    ];

    public function score(string $ocrText): array
    {
        $text = mb_strtolower(trim($ocrText));
        if ($text === '') {
            return ['score' => 0, 'bank' => null, 'keyword_hits' => 0, 'has_amount' => false];
        }

        $score = 0;
        $bank = $this->detectBank($text);
        $keywordHits = 0;

        foreach (self::RECEIPT_KEYWORDS as $keyword) {
            if (str_contains($text, $keyword)) {
                $score += 8;
                $keywordHits++;
            }
        }

        if ($bank !== null) {
            $score += 18;
        }

        $hasAmount = preg_match('/\$\s*[\d.,]+/', $text) === 1 || preg_match('/\bcop\b/', $text) === 1;
        if ($hasAmount) {
            $score += 15;
        }

        if (preg_match('/\b\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4}\b/', $text)) {
            $score += 10;
        }

        $hasReferenceLabel = preg_match(
            '/referencia|autorizaci[oó]n|comprobante|transacci[oó]n/i',
            $text
        ) === 1;
        if ($hasReferenceLabel && preg_match('/\b[A-Z0-9][A-Z0-9\-]{5,24}\b/i', $text)) {
            $score += 12;
        } elseif ($hasReferenceLabel && preg_match('/\b\d{6,20}\b/', $text)) {
            $score += 12;
        }

        return [
            'score' => min(100, $score),
            'bank' => $bank,
            'keyword_hits' => $keywordHits,
            'has_amount' => $hasAmount,
        ];
    }

    private function detectBank(string $text): ?string
    {
        foreach (self::BANK_KEYWORDS as $needle => $label) {
            if (str_contains($text, $needle)) {
                return $label;
            }
        }

        return null;
    }
}
