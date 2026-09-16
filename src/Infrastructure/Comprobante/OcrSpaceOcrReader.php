<?php
declare(strict_types=1);

namespace App\Infrastructure\Comprobante;

use App\Application\Comprobante\Contract\ImagePreprocessorInterface;
use App\Application\Comprobante\Contract\OcrReaderInterface;
use App\Application\Comprobante\OcrReadResult;

/**
 * OCR vía OCR.space API (POST /parse/image).
 *
 * @see https://ocr.space/OCRAPI
 */
final class OcrSpaceOcrReader implements OcrReaderInterface
{
    private const API_URL = 'https://api.ocr.space/parse/image';

    /** Plan gratuito: máx. 1 MB por archivo (PRO: 5 MB). */
    private const MAX_UPLOAD_BYTES = 950_000;

    public function __construct(
        private readonly ImagePreprocessorInterface $preprocessor,
        private readonly string $apiKey,
        private readonly int $timeoutSeconds = 25,
    ) {
    }

    public function isAvailable(): bool
    {
        return trim($this->apiKey) !== '' && function_exists('curl_init');
    }

    public function read(string $imagePath): OcrReadResult
    {
        if (!$this->isAvailable() || !is_file($imagePath) || !is_readable($imagePath)) {
            return OcrReadResult::unavailable();
        }

        $prepared = $this->preprocessor->prepareForOcr($imagePath);
        $mime = $this->detectMime($prepared);
        $filename = 'comprobante.' . $this->extensionFromMime($mime);

        if (@filesize($prepared) > self::MAX_UPLOAD_BYTES) {
            $this->logOcrFailure('Archivo preparado supera límite OCR.space (~1 MB en plan free).');
            $this->cleanupPrepared($prepared, $imagePath);

            return OcrReadResult::failed();
        }

        $postFields = [
            'language' => 'spa',
            'OCREngine' => '2',
            'scale' => 'true',
            'isTable' => 'true',
            'detectOrientation' => 'true',
            'file' => new \CURLFile($prepared, $mime, $filename),
        ];

        $ch = curl_init(self::API_URL);
        if ($ch === false) {
            $this->cleanupPrepared($prepared, $imagePath);

            return OcrReadResult::failed();
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postFields,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => min(10, $this->timeoutSeconds),
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_HTTPHEADER => [
                'apikey: ' . $this->apiKey,
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        $this->cleanupPrepared($prepared, $imagePath);

        if (!is_string($response) || $response === '' || $httpCode < 200 || $httpCode >= 300) {
            $this->logOcrFailure('HTTP ' . $httpCode . ($curlError !== '' ? ' — ' . $curlError : ''));

            return OcrReadResult::failed();
        }

        return self::parseResponse($response);
    }

    /**
     * @internal Visible for unit tests.
     */
    public static function extractTextFromResponse(string $json): string
    {
        return self::parseResponse($json)->text;
    }

    /**
     * @internal Visible for unit tests.
     */
    public static function parseResponse(string $json): OcrReadResult
    {
        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return OcrReadResult::failed();
        }

        if (!is_array($data)) {
            return OcrReadResult::failed();
        }

        $ocrExitCode = (int)($data['OCRExitCode'] ?? 0);
        // 1 = éxito total, 2 = éxito parcial (útil en capturas con ruido).
        if (!in_array($ocrExitCode, [1, 2], true)) {
            return OcrReadResult::failed();
        }

        if (($data['IsErroredOnProcessing'] ?? false) === true) {
            return OcrReadResult::failed();
        }

        $chunks = [];
        foreach ($data['ParsedResults'] ?? [] as $item) {
            if (!is_array($item)) {
                continue;
            }

            if ((int)($item['FileParseExitCode'] ?? -99) !== 1) {
                continue;
            }

            $text = trim((string)($item['ParsedText'] ?? ''));
            if ($text !== '') {
                $chunks[] = $text;
            }
        }

        return OcrReadResult::ok(trim(implode("\n", $chunks)));
    }

    private function logOcrFailure(string $detail): void
    {
        if (!function_exists('writeAppLog')) {
            return;
        }

        writeAppLog('ocr_space.log', '[OcrSpace] ' . $detail);
    }

    private function detectMime(string $path): string
    {
        if (function_exists('finfo_open')) {
            $f = finfo_open(\FILEINFO_MIME_TYPE);
            if ($f !== false) {
                $mime = (string)finfo_file($f, $path);
                finfo_close($f);
                if ($mime !== '') {
                    return $mime;
                }
            }
        }

        return 'image/jpeg';
    }

    private function extensionFromMime(string $mime): string
    {
        return match (strtolower($mime)) {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/webp' => 'webp',
            'image/png' => 'png',
            default => 'jpg',
        };
    }

    private function cleanupPrepared(string $prepared, string $original): void
    {
        if ($prepared !== $original && is_file($prepared)) {
            @unlink($prepared);
        }
    }
}
