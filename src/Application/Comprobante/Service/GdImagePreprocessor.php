<?php
declare(strict_types=1);

namespace App\Application\Comprobante\Service;

use App\Application\Comprobante\Contract\ImagePreprocessorInterface;

final class GdImagePreprocessor implements ImagePreprocessorInterface
{
    /** Ancho máximo antes de enviar a OCR.space (el API también puede escalar con scale=true). */
    private const MAX_WIDTH = 1800;

    /** Objetivo bajo el límite de 1 MB del plan gratuito de OCR.space. */
    private const TARGET_MAX_BYTES = 900_000;

    public function prepareForOcr(string $imagePath): string
    {
        if (!is_file($imagePath) || !is_readable($imagePath) || !function_exists('imagecreatefromstring')) {
            return $imagePath;
        }

        $bytes = @file_get_contents($imagePath);
        if ($bytes === false) {
            return $imagePath;
        }

        $source = @imagecreatefromstring($bytes);
        if ($source === false) {
            return $imagePath;
        }

        $width = imagesx($source);
        $height = imagesy($source);
        if ($width < 1 || $height < 1) {
            imagedestroy($source);

            return $imagePath;
        }

        $targetWidth = min(self::MAX_WIDTH, max($width, (int)round($width * 1.4)));
        $targetHeight = (int)round($height * ($targetWidth / $width));
        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
        if ($canvas === false) {
            imagedestroy($source);

            return $imagePath;
        }

        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
        imagedestroy($source);

        imagefilter($canvas, IMG_FILTER_GRAYSCALE);
        imagefilter($canvas, IMG_FILTER_CONTRAST, -25);

        $tmp = tempnam(sys_get_temp_dir(), 'ocr_');
        if ($tmp === false) {
            imagedestroy($canvas);

            return $imagePath;
        }

        $jpegPath = $tmp . '.jpg';
        @unlink($tmp);

        foreach ([85, 72, 58] as $quality) {
            imagejpeg($canvas, $jpegPath, $quality);
            if (!is_file($jpegPath)) {
                continue;
            }
            if (@filesize($jpegPath) <= self::TARGET_MAX_BYTES) {
                break;
            }
        }

        imagedestroy($canvas);

        return is_file($jpegPath) ? $jpegPath : $imagePath;
    }
}
