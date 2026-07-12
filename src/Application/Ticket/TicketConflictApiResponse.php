<?php
declare(strict_types=1);

namespace App\Application\Ticket;

use App\Domain\Ticket\ValueObject\TicketStatus;
use App\Shared\Exception\DomainException;

final class TicketConflictApiResponse
{
    public static function fromDomainException(DomainException $e): array
    {
        return array_merge(
            [
                'success' => false,
                'message' => $e->getMessage(),
                'error_code' => $e->errorCode(),
            ],
            self::payloadFromContext($e->context())
        );
    }

    public static function fromUnavailable(int $ticketId, string $number, int $statusTicket): array
    {
        if ($ticketId <= 0 || $number === '') {
            return [
                'success' => false,
                'message' => 'No se pudieron reservar todos los nros. Intenta de nuevo.',
                'error_code' => 'TICKET_UNAVAILABLE',
            ];
        }

        $sold = $statusTicket === TicketStatus::PAID;
        $message = $sold
            ? "Lo siento, el número {$number} ya fue vendido."
            : "Lo siento, el número {$number} ya no está disponible.";

        return [
            'success' => false,
            'message' => $message,
            'error_code' => 'TICKET_UNAVAILABLE',
            'unavailable_ticket_ids' => [$ticketId],
            'unavailable_numbers' => [$number],
        ];
    }

    /** @param array<string, mixed> $context */
    private static function payloadFromContext(array $context): array
    {
        $payload = [];

        if (!empty($context['unavailable_ticket_ids']) && is_array($context['unavailable_ticket_ids'])) {
            $payload['unavailable_ticket_ids'] = array_values(array_map('intval', $context['unavailable_ticket_ids']));
        }
        if (!empty($context['unavailable_numbers']) && is_array($context['unavailable_numbers'])) {
            $payload['unavailable_numbers'] = array_values(array_map('strval', $context['unavailable_numbers']));
        }

        return $payload;
    }
}
