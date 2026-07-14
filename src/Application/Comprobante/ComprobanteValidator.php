<?php
declare(strict_types=1);

namespace App\Application\Comprobante;

use App\Application\Comprobante\Contract\AmountExtractorInterface;
use App\Application\Comprobante\Contract\DateExtractorInterface;
use App\Application\Comprobante\Contract\OcrReaderInterface;
use App\Application\Comprobante\Contract\ReceiptScorerInterface;
use App\Application\Comprobante\Contract\ReferenceExtractorInterface;
use App\Application\Comprobante\Service\ColombianReceiptDateExtractor;
use App\Domain\Comprobante\Repository\ProofReferenceRepositoryInterface;
use App\Domain\Comprobante\ValueObject\ComprobanteValidationStatus;

final class ComprobanteValidator
{
    private readonly DateExtractorInterface $dateExtractor;

    public function __construct(
        private readonly ComprobanteValidationConfig $config,
        private readonly OcrReaderInterface $ocrReader,
        private readonly ReceiptScorerInterface $scorer,
        private readonly ReferenceExtractorInterface $referenceExtractor,
        private readonly AmountExtractorInterface $amountExtractor,
        private readonly ProofReferenceRepositoryInterface $proofReferences,
        ?DateExtractorInterface $dateExtractor = null,
    ) {
        $this->dateExtractor = $dateExtractor ?? new ColombianReceiptDateExtractor();
    }

    public function validate(ComprobanteValidationRequest $request): ComprobanteValidationResult
    {
        $imagePath = $request->imagePath;
        $extension = $request->extension;
        $transferId = $request->transferId;
        $expectedAmountCop = $request->expectedAmountCop;

        $fileHash = is_file($imagePath) ? (string)hash_file('sha256', $imagePath) : '';

        if (!$this->config->enabled) {
            return $this->buildResult(
                accepted: true,
                status: ComprobanteValidationStatus::DISABLED,
                message: '',
                score: 0,
                referenceRaw: null,
                referenceNorm: null,
                detectedBank: null,
                ocrText: '',
                fileHash: $fileHash,
                extension: $extension,
                transferId: $transferId,
                detectedAmountCop: null,
                expectedAmountCop: $expectedAmountCop,
                detectedAmountRaw: null,
            );
        }

        $ocrText = $this->ocrReader->read($imagePath);
        $scoreData = $this->scorer->score($ocrText);
        $score = (int)$scoreData['score'];
        $detectedBank = $scoreData['bank'] ?? null;
        $keywordHits = (int)($scoreData['keyword_hits'] ?? 0);
        $hasAmount = (bool)($scoreData['has_amount'] ?? false);

        $reference = $this->referenceExtractor->extract($ocrText);
        $referenceRaw = $reference['raw'] ?? null;
        $referenceNorm = $reference['normalized'] ?? null;
        if ($referenceNorm !== null && ReferenceNormalizer::isNonTransactional($referenceNorm)) {
            $referenceRaw = null;
            $referenceNorm = null;
        }
        if ($detectedBank === null && isset($reference['bank'])) {
            $detectedBank = $reference['bank'];
        }
        $detectedBank = $this->normalizeDetectedBank($detectedBank);

        $amountData = $this->amountExtractor->extract($ocrText, $expectedAmountCop);
        $detectedAmountCop = $amountData['amount'] ?? null;
        $detectedAmountRaw = $amountData['raw'] ?? null;

        $dateData = $this->dateExtractor->extract($ocrText);
        $detectedDate = $dateData['date'] ?? null;

        if ($this->config->rejectDuplicates) {
            if ($referenceNorm !== null && $this->proofReferences->existsByReferenceNorm($referenceNorm)) {
                return $this->buildRejected(
                    ComprobanteUserMessages::duplicateReference(),
                    $score,
                    $referenceRaw,
                    $referenceNorm,
                    $detectedBank,
                    $ocrText,
                    $fileHash,
                    $extension,
                    $transferId,
                    $detectedAmountCop,
                    $expectedAmountCop,
                    $detectedAmountRaw,
                );
            }

            if ($fileHash !== '' && $this->proofReferences->existsByFileHash($fileHash)) {
                return $this->buildRejected(
                    ComprobanteUserMessages::duplicateImage(),
                    $score,
                    $referenceRaw,
                    $referenceNorm,
                    $detectedBank,
                    $ocrText,
                    $fileHash,
                    $extension,
                    $transferId,
                    $detectedAmountCop,
                    $expectedAmountCop,
                    $detectedAmountRaw,
                );
            }
        }

        if ($this->ocrReader->isAvailable() && trim($ocrText) === '') {
            $userMsg = ComprobanteUserMessages::unreadable();

            return $this->buildResult(
                accepted: false,
                status: ComprobanteValidationStatus::OCR_FAILED,
                message: $userMsg->message,
                score: $score,
                referenceRaw: $referenceRaw,
                referenceNorm: $referenceNorm,
                detectedBank: $detectedBank,
                ocrText: $ocrText,
                fileHash: $fileHash,
                extension: $extension,
                transferId: $transferId,
                detectedAmountCop: $detectedAmountCop,
                expectedAmountCop: $expectedAmountCop,
                detectedAmountRaw: $detectedAmountRaw,
                errorCode: $userMsg->code,
                hint: $userMsg->hint,
            );
        }

        if (!$this->looksLikeReceipt($score, $ocrText, $detectedBank, $referenceNorm, $keywordHits, $hasAmount)) {
            return $this->buildRejected(
                ComprobanteUserMessages::notReceipt(),
                $score,
                $referenceRaw,
                $referenceNorm,
                $detectedBank,
                $ocrText,
                $fileHash,
                $extension,
                $transferId,
                $detectedAmountCop,
                $expectedAmountCop,
                $detectedAmountRaw,
            );
        }

        $amountCheck = $this->validateAmount($detectedAmountCop, $expectedAmountCop);
        if ($amountCheck instanceof ComprobanteUserMessages) {
            return $this->buildRejected(
                $amountCheck,
                $score,
                $referenceRaw,
                $referenceNorm,
                $detectedBank,
                $ocrText,
                $fileHash,
                $extension,
                $transferId,
                $detectedAmountCop,
                $expectedAmountCop,
                $detectedAmountRaw,
            );
        }

        $dateCheck = $this->validateDate($detectedDate);
        if ($dateCheck instanceof ComprobanteUserMessages) {
            return $this->buildRejected(
                $dateCheck,
                $score,
                $referenceRaw,
                $referenceNorm,
                $detectedBank,
                $ocrText,
                $fileHash,
                $extension,
                $transferId,
                $detectedAmountCop,
                $expectedAmountCop,
                $detectedAmountRaw,
            );
        }

        $status = $this->resolveStatus($score);
        if ($status === ComprobanteValidationStatus::REVIEW) {
            return $this->buildRejected(
                ComprobanteUserMessages::unclear(),
                $score,
                $referenceRaw,
                $referenceNorm,
                $detectedBank,
                $ocrText,
                $fileHash,
                $extension,
                $transferId,
                $detectedAmountCop,
                $expectedAmountCop,
                $detectedAmountRaw,
            );
        }

        return $this->buildResult(
            accepted: true,
            status: $status,
            message: '',
            score: $score,
            referenceRaw: $referenceRaw,
            referenceNorm: $referenceNorm,
            detectedBank: $detectedBank,
            ocrText: $ocrText,
            fileHash: $fileHash,
            extension: $extension,
            transferId: $transferId,
            detectedAmountCop: $detectedAmountCop,
            expectedAmountCop: $expectedAmountCop,
            detectedAmountRaw: $detectedAmountRaw,
        );
    }

    private function validateAmount(?float $detectedAmountCop, ?float $expectedAmountCop): ?ComprobanteUserMessages
    {
        if (!$this->config->validateAmount || $expectedAmountCop === null || $expectedAmountCop <= 0) {
            return null;
        }

        if ($detectedAmountCop === null) {
            return ComprobanteUserMessages::amountMissing();
        }

        if (!CopAmountComparer::equivalent($detectedAmountCop, $expectedAmountCop)) {
            return ComprobanteUserMessages::amountMismatch(
                CopAmountComparer::formatCop($detectedAmountCop),
                CopAmountComparer::formatCop($expectedAmountCop),
            );
        }

        return null;
    }

    private function validateDate(?string $detectedDate): ?ComprobanteUserMessages
    {
        if (!$this->config->validateDate) {
            return null;
        }

        if ($detectedDate === null) {
            return $this->config->rejectWhenDateMissing
                ? ComprobanteUserMessages::dateMissing()
                : null;
        }

        $detected = \DateTimeImmutable::createFromFormat('!Y-m-d', $detectedDate);
        if ($detected === false) {
            return $this->config->rejectWhenDateMissing
                ? ComprobanteUserMessages::dateMissing()
                : null;
        }

        $today = new \DateTimeImmutable('today');
        $diffDays = (int)$today->diff($detected)->format('%r%a');

        if (abs($diffDays) <= $this->config->dateToleranceDays) {
            return null;
        }

        return ComprobanteUserMessages::dateMismatch();
    }

    private function looksLikeReceipt(
        int $score,
        string $ocrText,
        ?string $detectedBank,
        ?string $referenceNorm,
        int $keywordHits,
        bool $hasAmount,
    ): bool {
        if ($score >= $this->config->minScoreStrict) {
            return true;
        }

        if ($this->config->isStrict()) {
            return false;
        }

        if ($score < $this->config->minScoreReview) {
            return false;
        }

        $text = mb_strtolower($ocrText);
        $hasStrongKeyword = str_contains($text, 'comprobante')
            || str_contains($text, 'transferencia')
            || str_contains($text, 'transaccion')
            || str_contains($text, 'transacción')
            || str_contains($text, 'pago');

        return $detectedBank !== null
            || $referenceNorm !== null
            || ($hasStrongKeyword && ($hasAmount || $keywordHits >= 2));
    }

    private function resolveStatus(int $score): string
    {
        if ($score >= $this->config->minScoreStrict) {
            return ComprobanteValidationStatus::VALID;
        }

        return ComprobanteValidationStatus::REVIEW;
    }

    private function normalizeDetectedBank(?string $bank): ?string
    {
        if ($bank === null || trim($bank) === '') {
            return null;
        }

        $key = mb_strtolower(trim($bank));

        return in_array($key, ['bre-b', 'breb'], true) ? null : $bank;
    }

    private function buildRejected(
        ComprobanteUserMessages $userMsg,
        int $score,
        ?string $referenceRaw,
        ?string $referenceNorm,
        ?string $detectedBank,
        string $ocrText,
        string $fileHash,
        string $extension,
        int $transferId,
        ?float $detectedAmountCop,
        ?float $expectedAmountCop,
        ?string $detectedAmountRaw,
    ): ComprobanteValidationResult {
        return $this->buildResult(
            accepted: false,
            status: ComprobanteValidationStatus::REJECTED,
            message: $userMsg->message,
            score: $score,
            referenceRaw: $referenceRaw,
            referenceNorm: $referenceNorm,
            detectedBank: $detectedBank,
            ocrText: $ocrText,
            fileHash: $fileHash,
            extension: $extension,
            transferId: $transferId,
            detectedAmountCop: $detectedAmountCop,
            expectedAmountCop: $expectedAmountCop,
            detectedAmountRaw: $detectedAmountRaw,
            errorCode: $userMsg->code,
            hint: $userMsg->hint,
        );
    }

    private function buildResult(
        bool $accepted,
        string $status,
        string $message,
        int $score,
        ?string $referenceRaw,
        ?string $referenceNorm,
        ?string $detectedBank,
        string $ocrText,
        string $fileHash,
        string $extension,
        int $transferId,
        ?float $detectedAmountCop,
        ?float $expectedAmountCop,
        ?string $detectedAmountRaw,
        ?string $errorCode = null,
        ?string $hint = null,
    ): ComprobanteValidationResult {
        return new ComprobanteValidationResult(
            accepted: $accepted,
            status: $status,
            message: $message,
            score: $score,
            referenceRaw: $referenceRaw,
            referenceNorm: $referenceNorm,
            detectedBank: $detectedBank,
            ocrText: $ocrText,
            fileHash: $fileHash,
            suggestedFilename: ComprobanteFilenameBuilder::build($referenceNorm, $extension, $transferId),
            ocrAvailable: $this->ocrReader->isAvailable(),
            detectedAmountCop: $detectedAmountCop,
            expectedAmountCop: $expectedAmountCop,
            detectedAmountRaw: $detectedAmountRaw,
            errorCode: $errorCode,
            hint: $hint,
        );
    }
}
