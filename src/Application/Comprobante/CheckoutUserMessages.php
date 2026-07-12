<?php
declare(strict_types=1);

namespace App\Application\Comprobante;

final class CheckoutUserMessages
{
    public static function missingProof(): array
    {
        return self::payload(
            'checkout_proof_required',
            'Adjunta la captura de tu transferencia para continuar.',
            'Después de pagar por Bre-B, toma una captura del comprobante y súbela aquí.',
        );
    }

    public static function invalidUpload(): array
    {
        return self::payload(
            'checkout_upload_invalid',
            'Hubo un problema al subir la imagen.',
            'Vuelve a seleccionar el archivo e intenta de nuevo.',
        );
    }

    public static function invalidFormat(): array
    {
        return self::payload(
            'checkout_format_invalid',
            'Formato de imagen no admitido.',
            'Usa una foto o captura en JPG, PNG o WebP.',
        );
    }

    public static function fileTooLarge(): array
    {
        return self::payload(
            'checkout_file_too_large',
            'La imagen pesa demasiado (máximo 5 MB).',
            'Toma otra captura o recorta la imagen antes de subirla.',
        );
    }

    public static function totalCalculationFailed(): array
    {
        return self::payload(
            'checkout_total_failed',
            'No pudimos calcular el total de tu compra.',
            'Recarga la página y vuelve a seleccionar tus números.',
        );
    }

    public static function approvalFailed(?string $detail = null): array
    {
        $hint = 'Escríbenos por WhatsApp con tu celular y el número de referencia del pago.';
        if ($detail !== null && $detail !== '') {
            $lower = strtolower($detail);
            if (str_contains($lower, 'disponib') || str_contains($lower, 'conflict') || str_contains($lower, 'reservad')) {
                $hint = 'Alguien más tomó esos números mientras confirmábamos. Intenta de nuevo con otros números o escríbenos por WhatsApp.';
            } elseif (str_contains($lower, 'monto') || str_contains($lower, 'precio')) {
                $hint = 'Recarga la página, verifica el total y vuelve a intentar. Si persiste, contáctanos por WhatsApp.';
            }
        }

        return self::payload(
            'checkout_approval_failed',
            'Recibimos tu comprobante, pero la confirmación no se completó.',
            $hint,
        );
    }

    public static function uploadError(int $code): array
    {
        $message = match ($code) {
            \UPLOAD_ERR_INI_SIZE, \UPLOAD_ERR_FORM_SIZE => 'La imagen supera el tamaño máximo permitido.',
            \UPLOAD_ERR_PARTIAL => 'La subida se interrumpió antes de terminar.',
            \UPLOAD_ERR_NO_FILE => 'No recibimos ninguna imagen.',
            default => 'No pudimos recibir la imagen.',
        };

        $hint = match ($code) {
            \UPLOAD_ERR_INI_SIZE, \UPLOAD_ERR_FORM_SIZE => 'Usa una captura más liviana (máximo 5 MB).',
            \UPLOAD_ERR_PARTIAL => 'Revisa tu conexión y vuelve a subir el comprobante.',
            \UPLOAD_ERR_NO_FILE => 'Selecciona la captura de tu transferencia Bre-B.',
            default => 'Intenta de nuevo en unos segundos.',
        };

        return self::payload('checkout_upload_error_' . $code, $message, $hint);
    }

    /** @return array{success: false, message: string, hint?: string, error_code: string} */
    public static function payload(string $code, string $message, string $hint = ''): array
    {
        $out = [
            'success' => false,
            'message' => $message,
            'error_code' => $code,
        ];

        if ($hint !== '') {
            $out['hint'] = $hint;
        }

        return $out;
    }
}
