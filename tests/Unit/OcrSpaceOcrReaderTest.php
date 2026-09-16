<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Infrastructure\Comprobante\OcrSpaceOcrReader;
use App\Application\Comprobante\Service\GdImagePreprocessor;
use PHPUnit\Framework\TestCase;

final class OcrSpaceOcrReaderTest extends TestCase
{
    public function testIsUnavailableWithoutApiKey(): void
    {
        $reader = new OcrSpaceOcrReader(new GdImagePreprocessor(), '');

        self::assertFalse($reader->isAvailable());
    }

    public function testIsAvailableWithApiKeyAndCurl(): void
    {
        if (!function_exists('curl_init')) {
            self::markTestSkipped('curl no disponible');
        }

        $reader = new OcrSpaceOcrReader(new GdImagePreprocessor(), 'demo-key');

        self::assertTrue($reader->isAvailable());
    }

    public function testExtractTextAcceptsPartialOcrExitCode(): void
    {
        $json = json_encode([
            'OCRExitCode' => 2,
            'IsErroredOnProcessing' => false,
            'ParsedResults' => [
                [
                    'FileParseExitCode' => 1,
                    'ParsedText' => "Transferencia exitosa\nValor $10.000",
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $text = OcrSpaceOcrReader::extractTextFromResponse($json);

        self::assertStringContainsString('Transferencia', $text);
        self::assertStringContainsString('10.000', $text);
    }

    public function testExtractTextSkipsFailedParseResults(): void
    {
        $json = json_encode([
            'OCRExitCode' => 1,
            'IsErroredOnProcessing' => false,
            'ParsedResults' => [
                [
                    'FileParseExitCode' => -10,
                    'ParsedText' => 'no debe aparecer',
                ],
                [
                    'FileParseExitCode' => 1,
                    'ParsedText' => 'Comprobante válido',
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $text = OcrSpaceOcrReader::extractTextFromResponse($json);

        self::assertSame('Comprobante válido', $text);
    }

    public function testExtractTextRejectsFatalOcrExitCode(): void
    {
        $json = json_encode([
            'OCRExitCode' => 3,
            'IsErroredOnProcessing' => true,
            'ParsedResults' => [],
        ], JSON_THROW_ON_ERROR);

        $result = OcrSpaceOcrReader::parseResponse($json);

        self::assertFalse($result->serviceOk);
        self::assertSame('', $result->text);
        self::assertSame('', OcrSpaceOcrReader::extractTextFromResponse($json));
    }

    public function testParseResponseMarksSuccessfulEmptyTextAsOk(): void
    {
        $json = json_encode([
            'OCRExitCode' => 1,
            'IsErroredOnProcessing' => false,
            'ParsedResults' => [
                [
                    'FileParseExitCode' => 1,
                    'ParsedText' => '   ',
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $result = OcrSpaceOcrReader::parseResponse($json);

        self::assertTrue($result->serviceOk);
        self::assertSame('', $result->text);
    }
}
