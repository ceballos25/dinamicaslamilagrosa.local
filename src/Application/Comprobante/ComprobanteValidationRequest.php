<?php
declare(strict_types=1);

namespace App\Application\Comprobante;

final class ComprobanteValidationRequest
{
    public function __construct(
        public readonly string $imagePath,
        public readonly string $extension,
        public readonly int $transferId = 0,
        public readonly ?float $expectedAmountCop = null,
    ) {
    }
}
