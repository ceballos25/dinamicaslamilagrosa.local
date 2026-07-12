<?php
declare(strict_types=1);

namespace App\Domain\Comprobante\ValueObject;

final class ComprobanteValidationStatus
{
    public const VALID = 'valid';
    public const REVIEW = 'review';
    public const REJECTED = 'rejected';
    public const OCR_FAILED = 'ocr_failed';
    public const DISABLED = 'disabled';

    private function __construct()
    {
    }
}
