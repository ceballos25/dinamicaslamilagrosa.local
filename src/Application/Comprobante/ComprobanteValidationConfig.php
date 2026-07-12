<?php
declare(strict_types=1);

namespace App\Application\Comprobante;

use App\Domain\Comprobante\ValueObject\ComprobanteValidationMode;
use App\Shared\Config\DynamicConfig;

final class ComprobanteValidationConfig
{
    public const PROVIDER_OCRSPACE = 'ocrspace';

    public function __construct(
        public readonly bool $enabled,
        public readonly string $mode,
        public readonly bool $rejectDuplicates,
        public readonly int $minScoreStrict,
        public readonly int $minScoreReview,
        public readonly string $ocrSpaceApiKey,
        public readonly bool $validateAmount,
        public readonly bool $validateDate = true,
        public readonly int $dateToleranceDays = 1,
        public readonly bool $rejectWhenDateMissing = false,
    ) {
    }

    public static function fromDynamicConfig(DynamicConfig $config): self
    {
        $enabledRaw = strtolower(trim((string)$config->get('comprobante_validation_enabled', '1')));
        $modeRaw = strtolower(trim((string)$config->get('comprobante_validation_mode', ComprobanteValidationMode::STRICT)));
        $dupRaw = strtolower(trim((string)$config->get('comprobante_reject_duplicates', '1')));
        $apiKey = trim((string)$config->get('comprobante_ocr_space_api_key', ''));
        if ($apiKey === '' && function_exists('env')) {
            $apiKey = trim((string)env('COMPROBANTE_OCR_SPACE_API_KEY', ''));
        }
        $amountRaw = strtolower(trim((string)$config->get('comprobante_validate_amount', '1')));
        $dateRaw = strtolower(trim((string)$config->get('comprobante_validate_date', '1')));
        $dateMissingRaw = strtolower(trim((string)$config->get('comprobante_reject_when_date_missing', '0')));

        return new self(
            enabled: !in_array($enabledRaw, ['0', 'false', 'no', 'off'], true),
            mode: $modeRaw === ComprobanteValidationMode::STRICT
                ? ComprobanteValidationMode::STRICT
                : ComprobanteValidationMode::PERMISSIVE,
            rejectDuplicates: !in_array($dupRaw, ['0', 'false', 'no', 'off'], true),
            minScoreStrict: max(1, (int)$config->get('comprobante_min_score_strict', '50')),
            minScoreReview: max(1, (int)$config->get('comprobante_min_score_review', '25')),
            ocrSpaceApiKey: $apiKey,
            validateAmount: !in_array($amountRaw, ['0', 'false', 'no', 'off'], true),
            validateDate: !in_array($dateRaw, ['0', 'false', 'no', 'off'], true),
            dateToleranceDays: max(0, (int)$config->get('comprobante_date_tolerance_days', '1')),
            rejectWhenDateMissing: in_array($dateMissingRaw, ['1', 'true', 'yes', 'on'], true),
        );
    }

    public function isStrict(): bool
    {
        return $this->mode === ComprobanteValidationMode::STRICT;
    }
}
