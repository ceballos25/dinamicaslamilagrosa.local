<?php
declare(strict_types=1);

namespace App\Application\Comprobante;

final class ReferenceNormalizer
{
    /**
     * Valores que el OCR tomó por error (texto del QR, palabras sueltas).
     * Nunca deben bloquear un pago como "duplicado".
     *
     * @var list<string>
     */
    private const NON_TRANSACTIONAL = [
        'ESCANEANDO',
        'AUTENTICIDAD',
        'TRANSFERENCIA',
        'COMPROBANTE',
        'EXITOSA',
        'EXITOSO',
        'REALIZADO',
        'REALIZADA',
        'DAVIPLATA',
        'NEQUI',
        'BANCOLOMBIA',
        'BREB',
        'BBVA',
        'BOAVIVIENDA',
        'DAVIVIENDA',
    ];

    public static function normalize(?string $reference): ?string
    {
        if ($reference === null) {
            return null;
        }

        $value = strtoupper(trim($reference));
        $value = preg_replace('/[^A-Z0-9]/', '', $value) ?? '';
        if ($value === '' || strlen($value) < 6) {
            return null;
        }

        if (self::isNonTransactional($value)) {
            return null;
        }

        return $value;
    }

    public static function isNonTransactional(string $referenceNorm): bool
    {
        $value = strtoupper(preg_replace('/[^A-Z0-9]/', '', $referenceNorm) ?? '');
        if ($value === '') {
            return true;
        }

        if (in_array($value, self::NON_TRANSACTIONAL, true)) {
            return true;
        }

        // Solo letras: no es un ID de movimiento típico.
        if (preg_match('/^[A-Z]+$/', $value) === 1) {
            return true;
        }

        return false;
    }
}
