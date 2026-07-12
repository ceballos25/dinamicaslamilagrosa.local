<?php
declare(strict_types=1);

namespace App\Application\Comprobante\Contract;

interface ReferenceExtractorInterface
{
    /**
     * @return array{raw: string|null, normalized: string|null, bank: string|null}
     */
    public function extract(string $ocrText): array;
}
