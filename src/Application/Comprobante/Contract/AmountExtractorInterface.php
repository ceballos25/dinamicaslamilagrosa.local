<?php
declare(strict_types=1);

namespace App\Application\Comprobante\Contract;

interface AmountExtractorInterface
{
  /**
   * @return array{amount: float|null, raw: string|null}
   */
    public function extract(string $ocrText, ?float $expectedAmountCop = null): array;
}
