<?php
declare(strict_types=1);

namespace App\Application\Comprobante;

final class ComprobanteFilenameBuilder
{
    public static function build(?string $referenceNorm, string $extension, int $transferId): string
    {
        $ext = strtolower(preg_replace('/[^a-z0-9]/', '', $extension) ?? '') ?: 'jpg';

        if ($referenceNorm !== null && $referenceNorm !== '') {
            $safeRef = preg_replace('/[^A-Za-z0-9_-]/', '', $referenceNorm) ?? '';

            return ($safeRef !== '' ? $safeRef : 'trf-' . $transferId) . '.' . $ext;
        }

        return 'trf-' . $transferId . '.' . $ext;
    }

    public static function extensionFromMime(string $tmpPath, string $browserType = ''): string
    {
        $browserType = strtolower(trim($browserType));
        $map = [
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/pjpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];
        if ($browserType !== '' && isset($map[$browserType])) {
            return $map[$browserType];
        }

        if (is_file($tmpPath) && is_readable($tmpPath) && function_exists('finfo_open')) {
            $f = finfo_open(\FILEINFO_MIME_TYPE);
            if ($f !== false) {
                $mime = strtolower((string)finfo_file($f, $tmpPath));
                finfo_close($f);
                if (isset($map[$mime])) {
                    return $map[$mime];
                }
            }
        }

        return 'jpg';
    }
}
