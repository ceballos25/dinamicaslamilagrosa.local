<?php
declare(strict_types=1);

namespace App\Application\Comprobante\Contract;

interface ImagePreprocessorInterface
{
    /**
     * @return string Ruta absoluta de la imagen lista para OCR (puede ser la misma de entrada).
     */
    public function prepareForOcr(string $imagePath): string;
}
