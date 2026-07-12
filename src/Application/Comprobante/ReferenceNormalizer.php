<?php
declare(strict_types=1);

namespace App\Application\Comprobante;

final class ReferenceNormalizer
{
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

        return $value;
    }
}
