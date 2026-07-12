<?php
declare(strict_types=1);

namespace App\Application\Comprobante\Contract;

interface DateExtractorInterface
{
    /**
     * Extrae la fecha del comprobante desde el texto OCR.
     *
     * @return array{date: string|null, raw: string|null} date en formato Y-m-d
     */
    public function extract(string $ocrText): array;
}
