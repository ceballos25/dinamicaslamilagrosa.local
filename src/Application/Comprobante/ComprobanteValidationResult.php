<?php
declare(strict_types=1);

namespace App\Application\Comprobante;

final class ComprobanteValidationResult
{
    public function __construct(
        public readonly bool $accepted,
        public readonly string $status,
        public readonly string $message,
        public readonly int $score,
        public readonly ?string $referenceRaw,
        public readonly ?string $referenceNorm,
        public readonly ?string $detectedBank,
        public readonly string $ocrText,
        public readonly string $fileHash,
        public readonly string $suggestedFilename,
        public readonly bool $ocrAvailable,
        public readonly ?float $detectedAmountCop = null,
        public readonly ?float $expectedAmountCop = null,
        public readonly ?string $detectedAmountRaw = null,
        public readonly ?string $errorCode = null,
        public readonly ?string $hint = null,
    ) {
    }

    /**
     * @return array{message: string, hint?: string, error_code?: string}
     */
    public function toUserErrorPayload(): array
    {
        if ($this->accepted || $this->message === '') {
            return ['message' => ''];
        }

        $payload = ['message' => $this->message];
        if ($this->hint !== null && $this->hint !== '') {
            $payload['hint'] = $this->hint;
        }
        if ($this->errorCode !== null && $this->errorCode !== '') {
            $payload['error_code'] = $this->errorCode;
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public function toProofColumns(): array
    {
        return [
            'proof_reference' => $this->referenceRaw,
            'proof_reference_norm' => $this->referenceNorm,
            'proof_bank' => $this->detectedBank,
            'proof_file_hash' => $this->fileHash,
            'proof_validation_status' => $this->status,
            'proof_validation_score' => $this->score,
            'proof_ocr_text' => $this->ocrText !== '' ? $this->ocrText : null,
            'proof_amount_detected' => $this->detectedAmountCop,
            'proof_amount_expected' => $this->expectedAmountCop,
        ];
    }
}
