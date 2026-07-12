<?php
declare(strict_types=1);

namespace App\Application\Comprobante;

/**
 * Textos orientados al comprador (checkout público).
 * Estructura: qué pasó + qué hacer, sin jerga técnica.
 */
final class ComprobanteUserMessages
{
    public const CODE_DUPLICATE_REFERENCE = 'proof_duplicate_reference';
    public const CODE_DUPLICATE_IMAGE = 'proof_duplicate_image';
    public const CODE_UNREADABLE = 'proof_unreadable';
    public const CODE_NOT_RECEIPT = 'proof_not_receipt';
    public const CODE_UNCLEAR = 'proof_unclear';
    public const CODE_AMOUNT_MISSING = 'proof_amount_missing';
    public const CODE_AMOUNT_MISMATCH = 'proof_amount_mismatch';

    public function __construct(
        public readonly string $code,
        public readonly string $message,
        public readonly string $hint = '',
    ) {
    }

    public static function duplicateReference(): self
    {
        return self::alreadyRegistered(self::CODE_DUPLICATE_REFERENCE);
    }

    public static function duplicateImage(): self
    {
        return self::alreadyRegistered(self::CODE_DUPLICATE_IMAGE);
    }

    private static function alreadyRegistered(string $code): self
    {
        return new self(
            $code,
            '¡Upss! Parece que este comprobante de pago ya fue registrado.',
            'Si crees que se trata de un error, escríbenos por WhatsApp y te ayudamos enseguida.',
        );
    }

    public static function unreadable(): self
    {
        return new self(
            self::CODE_UNREADABLE,
            'No pudimos procesar tu comprobante. Inténtalo de nuevo.',
            'Sube una captura nítida y completa de tu transferencia Bre-B.',
        );
    }

    public static function notReceipt(): self
    {
        return new self(
            self::CODE_NOT_RECEIPT,
            'No pudimos procesar tu comprobante. Inténtalo de nuevo.',
            'Si crees que se trata de un error, escríbenos por WhatsApp.',
        );
    }

    public static function unclear(): self
    {
        return new self(
            self::CODE_UNCLEAR,
            'No pudimos procesar tu comprobante. Inténtalo de nuevo.',
            'Si crees que se trata de un error, escríbenos por WhatsApp.',
        );
    }

    public static function amountMissing(): self
    {
        return new self(
            self::CODE_AMOUNT_MISSING,
            'No pudimos procesar tu comprobante. Inténtalo de nuevo.',
            'Asegúrate de que el valor transferido se vea claro en la captura.',
        );
    }

    public static function amountMismatch(string $detectedFormatted, string $expectedFormatted): self
    {
        return new self(
            self::CODE_AMOUNT_MISMATCH,
            sprintf(
                'Tu compra es por %s, pero en el comprobante aparece %s.',
                $expectedFormatted,
                $detectedFormatted,
            ),
            'Transfiere el monto exacto de tu compra o sube el comprobante correcto. Inténtalo de nuevo.',
        );
    }

    /**
     * El rechazo por fecha usa el mensaje y código genéricos para no revelar
     * el motivo real al comprador.
     */
    public static function dateMissing(): self
    {
        return self::notReceipt();
    }

    public static function dateMismatch(): self
    {
        return self::notReceipt();
    }

    /**
     * @return array{message: string, hint?: string, error_code: string}
     */
    public function toApiPayload(): array
    {
        $payload = [
            'message' => $this->message,
            'error_code' => $this->code,
        ];

        if ($this->hint !== '') {
            $payload['hint'] = $this->hint;
        }

        return $payload;
    }
}
