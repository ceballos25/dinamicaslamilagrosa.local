<?php
declare(strict_types=1);

namespace App\Infrastructure\Comprobante;

use App\Application\Comprobante\Contract\OcrReaderInterface;

final class NullOcrReader implements OcrReaderInterface
{
    public function isAvailable(): bool
    {
        return false;
    }

    public function read(string $imagePath): string
    {
        return '';
    }
}
