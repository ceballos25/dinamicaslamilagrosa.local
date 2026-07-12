<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Application\Comprobante\ComprobanteValidationConfig;
use App\Application\Comprobante\ComprobanteValidationRequest;
use App\Application\Comprobante\ComprobanteValidator;
use App\Application\Comprobante\ComprobanteValidatorFactory;
use App\Application\Comprobante\CopAmountComparer;
use App\Application\Comprobante\ReferenceNormalizer;
use App\Application\Comprobante\Service\ColombianBankReferenceExtractor;
use App\Application\Comprobante\Service\ColombianReceiptAmountExtractor;
use App\Application\Comprobante\Service\ColombianReceiptDateExtractor;
use App\Application\Comprobante\Service\HeuristicReceiptScorer;
use App\Domain\Comprobante\ValueObject\ComprobanteValidationMode;
use App\Domain\Comprobante\ValueObject\ComprobanteValidationStatus;
use PHPUnit\Framework\TestCase;
use Tests\Support\InMemoryProofReferenceRepository;

final class ComprobanteValidationTest extends TestCase
{
    public function testReferenceNormalizerKeepsAlphanumericReference(): void
    {
        self::assertSame('M1234567890', ReferenceNormalizer::normalize('M-1234567890'));
        self::assertNull(ReferenceNormalizer::normalize('123'));
    }

    public function testCopAmountParserHandlesColombianFormat(): void
    {
        self::assertSame(10000.0, CopAmountComparer::parseCopAmountString('10.000'));
        self::assertSame(100000.0, CopAmountComparer::parseCopAmountString('$ 100.000'));
    }

    public function testAmountExtractorFindsLabeledValue(): void
    {
        $extractor = new ColombianReceiptAmountExtractor();
        $result = $extractor->extract('Comprobante Nequi. Valor $100.000 COP. Referencia 123456', 100000.0);

        self::assertSame(100000.0, $result['amount']);
    }

    public function testHeuristicScorerDetectsReceiptText(): void
    {
        $scorer = new HeuristicReceiptScorer();
        $result = $scorer->score(
            'Comprobante de transferencia Nequi. Valor $10.000 COP. Referencia 948372819201. 02/07/2026'
        );

        self::assertGreaterThanOrEqual(50, $result['score']);
        self::assertSame('Nequi', $result['bank']);
    }

    public function testReferenceExtractorFindsReferenceNearLabel(): void
    {
        $extractor = new ColombianBankReferenceExtractor();
        $result = $extractor->extract('Transferencia exitosa. Referencia: 948372819201. Bancolombia');

        self::assertSame('948372819201', $result['raw']);
        self::assertSame('948372819201', $result['normalized']);
    }

    public function testReferenceExtractorFindsBreBComprobanteReference(): void
    {
        $extractor = new ColombianBankReferenceExtractor();
        $ocrText = <<<'TXT'
Bre-B
¡Transferencia exitosa!
Comprobante No. TR9drli4k5EC
Valor de la transferencia
$ 250.000
TXT;

        $result = $extractor->extract($ocrText);

        self::assertSame('TR9drli4k5EC', $result['raw']);
        self::assertSame('TR9DRLI4K5EC', $result['normalized']);
        self::assertNull($result['bank']);
    }

    public function testHeuristicScorerDetectsBreBReceipt(): void
    {
        $scorer = new HeuristicReceiptScorer();
        $result = $scorer->score(
            "Bre-B\n¡Transferencia exitosa!\nComprobante No. TR9drli4k5EC\nValor de la transferencia\n\$ 250.000"
        );

        self::assertGreaterThanOrEqual(50, $result['score']);
        self::assertNull($result['bank']);
    }

    public function testValidatorAcceptsBreBComprobante(): void
    {
        $validator = $this->makeValidator(new InMemoryProofReferenceRepository(), ComprobanteValidationMode::STRICT);
        $ocrText = <<<'TXT'
Bre-B
¡Transferencia exitosa!
Comprobante No. TR9drli4k5EC
Valor de la transferencia
$ 250.000
TXT;

        $result = $validator->validate(new ComprobanteValidationRequest(
            $this->fakeImagePath($ocrText),
            'png',
            5,
            250000.0
        ));

        self::assertTrue($result->accepted);
        self::assertSame('TR9DRLI4K5EC', $result->referenceNorm);
        self::assertNull($result->detectedBank);
        self::assertSame(250000.0, $result->detectedAmountCop);
    }

    public function testValidatorRejectsDuplicateReference(): void
    {
        $repo = new InMemoryProofReferenceRepository();
        $repo->seedReference('948372819201');

        $validator = $this->makeValidator($repo, ComprobanteValidationMode::PERMISSIVE);
        $tmp = $this->writeTempTextImage('Referencia 948372819201 Valor $10.000');

        $result = $validator->validate(new ComprobanteValidationRequest($tmp, 'png', 10, 10000.0));

        @unlink($tmp);
        self::assertFalse($result->accepted);
        self::assertSame(ComprobanteValidationStatus::REJECTED, $result->status);
    }

    public function testValidatorAcceptsMatchingAmount(): void
    {
        $validator = $this->makeValidator(new InMemoryProofReferenceRepository(), ComprobanteValidationMode::PERMISSIVE);
        $ocrText = 'Comprobante transferencia Daviplata valor $100.000 referencia 112233445566';

        $result = $validator->validate(new ComprobanteValidationRequest(
            $this->fakeImagePath($ocrText),
            'jpg',
            5,
            100000.0
        ));

        self::assertTrue($result->accepted);
        self::assertSame(100000.0, $result->detectedAmountCop);
    }

    public function testValidatorRejectsMismatchedAmount(): void
    {
        $validator = $this->makeValidator(new InMemoryProofReferenceRepository(), ComprobanteValidationMode::STRICT);
        $ocrText = 'Comprobante transferencia Nequi valor $50.000 referencia 998877665544';

        $result = $validator->validate(new ComprobanteValidationRequest(
            $this->fakeImagePath($ocrText),
            'jpg',
            5,
            100000.0
        ));

        self::assertFalse($result->accepted);
        self::assertStringContainsString('$100.000', $result->message);
        self::assertStringContainsString('$50.000', $result->message);
    }

    public function testValidatorRejectsLowScoreInStrictMode(): void
    {
        $validator = $this->makeValidator(new InMemoryProofReferenceRepository(), ComprobanteValidationMode::STRICT);
        $result = $validator->validate(new ComprobanteValidationRequest(
            $this->fakeImagePath('hola mundo sin comprobante'),
            'jpg',
            5,
            10000.0
        ));

        self::assertFalse($result->accepted);
        self::assertSame(ComprobanteValidationStatus::REJECTED, $result->status);
    }

    public function testValidatorRejectsReviewBand(): void
    {
        $validator = $this->makeValidator(new InMemoryProofReferenceRepository(), ComprobanteValidationMode::PERMISSIVE);
        $ocrText = 'pago transferencia valor $10.000';

        $result = $validator->validate(new ComprobanteValidationRequest(
            $this->fakeImagePath($ocrText),
            'jpg',
            5,
            10000.0
        ));

        self::assertFalse($result->accepted);
        self::assertSame(ComprobanteValidationStatus::REJECTED, $result->status);
        self::assertStringContainsString('inténtalo de nuevo', mb_strtolower($result->message));
    }

    public function testDateExtractorParsesNumericColombianFormat(): void
    {
        $extractor = new ColombianReceiptDateExtractor();

        self::assertSame('2026-07-02', $extractor->extract('Fecha: 02/07/2026 10:35 a. m.')['date']);
        self::assertSame('2026-07-13', $extractor->extract('Realizado el 13-07-2026')['date']);
        self::assertSame('2026-07-02', $extractor->extract('02.07.26')['date']);
    }

    public function testDateExtractorParsesTextualMonth(): void
    {
        $extractor = new ColombianReceiptDateExtractor();

        self::assertSame('2026-07-02', $extractor->extract('2 de julio de 2026')['date']);
        self::assertSame('2026-07-03', $extractor->extract('Jul. 03, 2026')['date']);
        self::assertSame('2026-12-25', $extractor->extract('25 dic 2026')['date']);
    }

    public function testDateExtractorParsesIsoFormat(): void
    {
        $extractor = new ColombianReceiptDateExtractor();

        self::assertSame('2026-07-02', $extractor->extract('2026-07-02T10:35:00')['date']);
    }

    public function testDateExtractorReturnsNullWhenNoDate(): void
    {
        $extractor = new ColombianReceiptDateExtractor();

        self::assertNull($extractor->extract('Comprobante valor $10.000 referencia 112233445566')['date']);
    }

    public function testValidatorRejectsReceiptFromAnotherDay(): void
    {
        $validator = $this->makeValidator(new InMemoryProofReferenceRepository(), ComprobanteValidationMode::PERMISSIVE);
        $ocrText = 'Comprobante transferencia Daviplata valor $100.000 referencia 112233445566 fecha 02/07/2020';

        $result = $validator->validate(new ComprobanteValidationRequest(
            $this->fakeImagePath($ocrText),
            'jpg',
            5,
            100000.0
        ));

        self::assertFalse($result->accepted);
        self::assertSame(ComprobanteValidationStatus::REJECTED, $result->status);
        // No debe revelar que el rechazo fue por la fecha: usa el mensaje/código genérico.
        self::assertSame('proof_not_receipt', $result->errorCode);
        self::assertStringContainsString('inténtalo de nuevo', mb_strtolower($result->message));
    }

    public function testValidatorAcceptsReceiptFromToday(): void
    {
        $validator = $this->makeValidator(new InMemoryProofReferenceRepository(), ComprobanteValidationMode::PERMISSIVE);
        $today = (new \DateTimeImmutable('today'))->format('d/m/Y');
        $ocrText = 'Comprobante transferencia Daviplata valor $100.000 referencia 112233445566 fecha ' . $today;

        $result = $validator->validate(new ComprobanteValidationRequest(
            $this->fakeImagePath($ocrText),
            'jpg',
            5,
            100000.0
        ));

        self::assertTrue($result->accepted);
    }

    private function makeValidator(
        InMemoryProofReferenceRepository $repo,
        string $mode,
    ): ComprobanteValidator {
        $config = new ComprobanteValidationConfig(
            enabled: true,
            mode: $mode,
            rejectDuplicates: true,
            minScoreStrict: 50,
            minScoreReview: 25,
            ocrSpaceApiKey: '',
            validateAmount: true,
        );

        $ocr = new class implements \App\Application\Comprobante\Contract\OcrReaderInterface {
            public function isAvailable(): bool
            {
                return true;
            }

            public function read(string $imagePath): string
            {
                return (string)file_get_contents($imagePath);
            }
        };

        return ComprobanteValidatorFactory::create($config, $repo, $ocr);
    }

    private function fakeImagePath(string $ocrText): string
    {
        $path = tempnam(sys_get_temp_dir(), 'comp_');
        self::assertNotFalse($path);
        file_put_contents($path, $ocrText);

        return $path;
    }

    private function writeTempTextImage(string $content): string
    {
        return $this->fakeImagePath($content);
    }
}
