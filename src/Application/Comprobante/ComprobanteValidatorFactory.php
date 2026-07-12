<?php
declare(strict_types=1);

namespace App\Application\Comprobante;

use App\Application\Comprobante\Contract\AmountExtractorInterface;
use App\Application\Comprobante\Contract\DateExtractorInterface;
use App\Application\Comprobante\Contract\OcrReaderInterface;
use App\Application\Comprobante\Contract\ReceiptScorerInterface;
use App\Application\Comprobante\Contract\ReferenceExtractorInterface;
use App\Application\Comprobante\Service\ColombianBankReferenceExtractor;
use App\Application\Comprobante\Service\ColombianReceiptAmountExtractor;
use App\Application\Comprobante\Service\ColombianReceiptDateExtractor;
use App\Application\Comprobante\Service\GdImagePreprocessor;
use App\Application\Comprobante\Service\HeuristicReceiptScorer;
use App\Domain\Comprobante\Repository\ProofReferenceRepositoryInterface;
use App\Infrastructure\Comprobante\NullOcrReader;
use App\Infrastructure\Comprobante\OcrSpaceOcrReader;
use App\Infrastructure\Comprobante\PdoProofReferenceRepository;
use App\Shared\Config\DynamicConfig;

final class ComprobanteValidatorFactory
{
    public static function fromConfig(DynamicConfig $config): ComprobanteValidator
    {
        return self::create(
            ComprobanteValidationConfig::fromDynamicConfig($config),
            new PdoProofReferenceRepository(),
        );
    }

    public static function create(
        ComprobanteValidationConfig $config,
        ProofReferenceRepositoryInterface $proofReferences,
        ?OcrReaderInterface $ocrReader = null,
        ?ReceiptScorerInterface $scorer = null,
        ?ReferenceExtractorInterface $referenceExtractor = null,
        ?AmountExtractorInterface $amountExtractor = null,
        ?DateExtractorInterface $dateExtractor = null,
    ): ComprobanteValidator {
        return new ComprobanteValidator(
            $config,
            $ocrReader ?? self::resolveOcrReader($config),
            $scorer ?? new HeuristicReceiptScorer(),
            $referenceExtractor ?? new ColombianBankReferenceExtractor(),
            $amountExtractor ?? new ColombianReceiptAmountExtractor(),
            $proofReferences,
            $dateExtractor ?? new ColombianReceiptDateExtractor(),
        );
    }

    private static function resolveOcrReader(ComprobanteValidationConfig $config): OcrReaderInterface
    {
        $ocrSpace = new OcrSpaceOcrReader(new GdImagePreprocessor(), $config->ocrSpaceApiKey);

        return $ocrSpace->isAvailable() ? $ocrSpace : new NullOcrReader();
    }
}
