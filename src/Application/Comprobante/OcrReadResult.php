<?php
declare(strict_types=1);

namespace App\Application\Comprobante;

/**
 * Resultado de una lectura OCR.
 *
 * - serviceOk=true  → la API respondió (el texto puede venir vacío si la imagen no tiene caracteres).
 * - serviceOk=false → fallo de transporte/API/cuota; no debe bloquear el checkout.
 */
final class OcrReadResult
{
    public function __construct(
        public readonly string $text,
        public readonly bool $serviceOk,
    ) {
    }

    public static function ok(string $text): self
    {
        return new self($text, true);
    }

    public static function failed(): self
    {
        return new self('', false);
    }

    public static function unavailable(): self
    {
        return new self('', false);
    }
}
