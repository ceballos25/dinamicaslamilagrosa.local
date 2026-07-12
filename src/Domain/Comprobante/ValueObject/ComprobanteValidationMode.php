<?php
declare(strict_types=1);

namespace App\Domain\Comprobante\ValueObject;

final class ComprobanteValidationMode
{
    public const STRICT = 'strict';
    public const PERMISSIVE = 'permissive';

    private function __construct()
    {
    }
}
