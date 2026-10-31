<?php
declare(strict_types=1);

namespace App\Application\Ticket;

use App\Domain\Raffle\Repository\RaffleRepositoryInterface;
use App\Domain\Raffle\ValueObject\RaffleType;
use App\Infrastructure\Database\PdoFactory;
use App\Shared\Exception\DomainException;
use PDO;

/**
 * Reglas de asignación según tipo de rifa:
 * - manual: el cliente elige IDs → reservar al crear respaldo/transferencia.
 * - automatic: sin reserva previa → asignar números disponibles al aprobar la venta.
 * - both: el cliente puede ir al azar o elegir nros; si envía IDs se reservan.
 */
final class RaffleCheckoutAllocationService
{
    private PDO $pdo;

    public function __construct(
        private readonly TicketReservationService $reservations,
        private readonly RaffleRepositoryInterface $raffles,
        ?PDO $pdo = null
    ) {
        $this->pdo = $pdo ?? PdoFactory::get();
    }

    public function isManualRaffle(int $raffleId): bool
    {
        $raffle = $this->raffles->findById($raffleId);
        if ($raffle === null) {
            throw new DomainException('Campaña no encontrada', 'RAFFLE_NOT_FOUND');
        }

        return ($raffle['type_raffle'] ?? RaffleType::AUTOMATIC) === RaffleType::MANUAL;
    }

    public function raffleType(int $raffleId): string
    {
        $raffle = $this->raffles->findById($raffleId);
        if ($raffle === null) {
            throw new DomainException('Campaña no encontrada', 'RAFFLE_NOT_FOUND');
        }

        $type = (string)($raffle['type_raffle'] ?? RaffleType::AUTOMATIC);

        return RaffleType::isValid($type) ? $type : RaffleType::AUTOMATIC;
    }

    public function acceptsPickedTickets(int $raffleId): bool
    {
        return RaffleType::allowsPickedTickets($this->raffleType($raffleId));
    }

    /**
     * Al iniciar checkout (transferencia pendiente).
     *
     * @param list<int>|null $ticketIds
     * @return array{
     *   ticket_ids: list<int>,
     *   numbers: list<string>,
     *   expires_at: string|null,
     *   allocation_mode: string
     * }
     */
    public function reserveForPendingPayment(
        int $raffleId,
        int $quantity,
        ?array $ticketIds,
        bool $holdUntilReview = false
    ): array {
        $hasIds = $ticketIds !== null && $ticketIds !== [];
        $type = $this->raffleType($raffleId);

        if (RaffleType::requiresPickedTickets($type) && !$hasIds) {
            throw new DomainException(
                'Debes seleccionar los códigos antes de continuar',
                'MANUAL_TICKETS_REQUIRED'
            );
        }

        if ($hasIds && RaffleType::allowsPickedTickets($type)) {
            $reservation = $this->reservations->reserveForPayment(
                $raffleId,
                $quantity,
                $ticketIds,
                $holdUntilReview
            );

            return [
                'ticket_ids' => $reservation['ticket_ids'],
                'numbers' => $reservation['numbers'],
                'expires_at' => $reservation['expires_at'],
                'allocation_mode' => RaffleType::MANUAL,
            ];
        }

        return [
            'ticket_ids' => [],
            'numbers' => [],
            'expires_at' => null,
            'allocation_mode' => RaffleType::AUTOMATIC,
        ];
    }

    /**
     * Al aprobar venta (transferencia u operación manual).
     *
     * @param list<int>|null $preReservedIds IDs ya reservados (rifa manual).
     * @return array{ticket_ids: list<int>|null, numbers: list<string>}
     */
    public function resolveTicketIdsForSaleApproval(int $raffleId, int $quantity, ?array $preReservedIds): array
    {
        $preReservedIds = $preReservedIds !== null
            ? array_values(array_unique(array_map('intval', $preReservedIds)))
            : [];
        $type = $this->raffleType($raffleId);

        if ($preReservedIds !== [] && RaffleType::allowsPickedTickets($type)) {
            if (count($preReservedIds) !== $quantity) {
                throw new DomainException(
                    'La cantidad no coincide con los códigos reservados',
                    'QUANTITY_MISMATCH'
                );
            }

            return [
                'ticket_ids' => $preReservedIds,
                'numbers' => $this->numbersForTicketIds($raffleId, $preReservedIds),
            ];
        }

        if (RaffleType::requiresPickedTickets($type)) {
            throw new DomainException(
                'No hay códigos reservados para esta compra manual',
                'MANUAL_NO_RESERVATION'
            );
        }

        return [
            'ticket_ids' => null,
            'numbers' => [],
        ];
    }

    /**
     * @param list<int> $ticketIds
     * @return list<string>
     */
    public function numbersForTicketIds(int $raffleId, array $ticketIds): array
    {
        if ($ticketIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ticketIds), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT number_ticket FROM tickets
             WHERE id_raffle_ticket = ? AND id_ticket IN ({$placeholders})
             ORDER BY CAST(number_ticket AS UNSIGNED), number_ticket"
        );
        $params = array_merge([$raffleId], $ticketIds);
        $stmt->execute($params);

        return array_map(static fn (array $row): string => (string)$row['number_ticket'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * @param mixed $rawJson
     * @return list<int>
     */
    public static function decodeTicketIds(mixed $rawJson): array
    {
        if ($rawJson === null || $rawJson === '') {
            return [];
        }
        if (is_array($rawJson)) {
            return array_values(array_unique(array_map('intval', $rawJson)));
        }
        if (!is_string($rawJson)) {
            return [];
        }
        $decoded = json_decode($rawJson, true);

        return is_array($decoded)
            ? array_values(array_unique(array_map('intval', $decoded)))
            : [];
    }
}
