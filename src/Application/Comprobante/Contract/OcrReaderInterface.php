<?php
declare(strict_types=1);

namespace App\Application\Comprobante\Contract;

use App\Application\Comprobante\OcrReadResult;

interface OcrReaderInterface
{
    public function isAvailable(): bool;

    public function read(string $imagePath): OcrReadResult;
}
