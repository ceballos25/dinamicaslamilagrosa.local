<?php
declare(strict_types=1);

namespace App\Application\Comprobante\Contract;

interface OcrReaderInterface
{
    public function isAvailable(): bool;

    public function read(string $imagePath): string;
}
