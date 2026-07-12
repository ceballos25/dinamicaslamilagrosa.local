<?php
declare(strict_types=1);

namespace App\Application\Comprobante\Contract;

interface ReceiptScorerInterface
{
    /**
     * @return array{score: int, bank: string|null, keyword_hits: int, has_amount: bool}
     */
    public function score(string $ocrText): array;
}
