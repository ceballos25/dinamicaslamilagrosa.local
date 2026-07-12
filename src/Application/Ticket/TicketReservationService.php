<?php
declare(strict_types=1);

namespace App\Application\Ticket;

use App\Domain\Raffle\Repository\RaffleRepositoryInterface;
use App\Domain\Ticket\Repository\TicketRepositoryInterface;
use App\Domain\Ticket\ValueObject\TicketStatus;
use App\Infrastructure\Database\PdoFactory;
use App\Shared\Exception\DomainException;
use PDO;

final class TicketReservationService
{
    private PDO $pdo;

    public function __construct(
        private readonly TicketRepositoryInterface $tickets,
        private readonly RaffleRepositoryInterface $raffles,
        ?PDO $pdo = null
    ) {
        $this->pdo = $pdo ?? PdoFactory::get();
    }

    /**
     * @param list<int>|null $ticketIds
     * @return array{ticket_ids: list<int>, numbers: list<string>, expires_at: string|null}
     */
    public function reserveForPayment(
        int $raffleId,
        int $quantity,
        ?array $ticketIds = null,
        bool $holdUntilReview = false
    ): array
    {
        if (!$this->raffles->canAcceptSales($raffleId)) {
            throw new DomainException('La campaña no acepta ventas en este momento', 'RAFFLE_SALES_BLOCKED');
        }

        $raffle = $this->raffles->findById($raffleId);
        if ($raffle === null) {
            throw new DomainException('Campaña no encontrada', 'RAFFLE_NOT_FOUND');
        }

        $minQty = (int)($raffle['min_quantity_raffle'] ?? 1);
        if ($quantity < $minQty) {
            throw new DomainException("La cantidad mínima es {$minQty}", 'MIN_QUANTITY');
        }

        $expiresAt = $holdUntilReview
            ? null
            : new \DateTimeImmutable('+' . (int)($raffle['reservation_minutes_raffle'] ?? 15) . ' minutes');

        $manualSelection = false;

        $this->pdo->beginTransaction();
        try {
            $this->tickets->releaseExpiredReservations();

            if ($ticketIds !== null && $ticketIds !== []) {
                $manualSelection = true;
                $ticketIds = array_values(array_unique(array_map('intval', $ticketIds)));
                if (count($ticketIds) !== $quantity) {
                    throw new DomainException('La cantidad no coincide con los nros seleccionados', 'QUANTITY_MISMATCH');
                }
                $rows = $this->tickets->findByIdsForUpdate($raffleId, $ticketIds);
                if (count($rows) !== count($ticketIds)) {
                    throw new DomainException('Algunos nros no existen', 'TICKETS_NOT_FOUND');
                }
                foreach ($rows as $row) {
                    if ((int)$row['status_ticket'] !== TicketStatus::AVAILABLE) {
                        $this->throwUnavailableRow($row);
                    }
                }
            } else {
                $rows = $this->tickets->findAvailableRandom($raffleId, $quantity);
                if (count($rows) < $quantity) {
                    throw new DomainException('No hay suficientes nros disponibles', 'INSUFFICIENT_TICKETS');
                }
                $ticketIds = array_map(static fn (array $r) => (int)$r['id_ticket'], $rows);
            }

            if ($manualSelection) {
                $this->reserveManualTickets($raffleId, $ticketIds, $expiresAt);
            } else {
                $reserved = $this->tickets->reserve($raffleId, $ticketIds, $expiresAt);
                if ($reserved !== count($ticketIds)) {
                    throw new DomainException('No hay suficientes nros disponibles', 'INSUFFICIENT_TICKETS');
                }
            }

            $numbers = array_map(static fn (array $r) => (string)$r['number_ticket'], $rows);
            if ($numbers === [] && $ticketIds !== []) {
                $rows = $this->tickets->findByIdsForUpdate($raffleId, $ticketIds);
                $numbers = array_map(static fn (array $r) => (string)$r['number_ticket'], $rows);
            }

            $this->pdo->commit();

            return [
                'ticket_ids' => $ticketIds,
                'numbers' => $numbers,
                'expires_at' => $expiresAt?->format('Y-m-d H:i:s'),
            ];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * @param list<int> $ticketIds
     */
    public function release(int $raffleId, array $ticketIds): int
    {
        $this->pdo->beginTransaction();
        try {
            $released = $this->tickets->release($raffleId, $ticketIds);
            $this->pdo->commit();

            return $released;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function releaseExpired(): int
    {
        return $this->tickets->releaseExpiredReservations();
    }

    /** @param list<int> $ticketIds */
    private function reserveManualTickets(int $raffleId, array $ticketIds, ?\DateTimeInterface $expiresAt): void
    {
        foreach ($ticketIds as $ticketId) {
            if ($this->tickets->reserve($raffleId, [$ticketId], $expiresAt) === 1) {
                continue;
            }

            $rows = $this->tickets->findByIdsForUpdate($raffleId, [$ticketId]);
            if ($rows !== []) {
                $this->throwUnavailableRow($rows[0]);
            }

            throw new DomainException(
                'No se pudieron reservar todos los nros. Intenta de nuevo.',
                'RESERVATION_RACE'
            );
        }
    }

    /** @param array{id_ticket: int|string, number_ticket: string, status_ticket: int|string} $row */
    private function throwUnavailableRow(array $row): void
    {
        $this->throwUnavailableMany([$row]);
    }

    /**
     * @param list<array{id_ticket: int|string, number_ticket: string, status_ticket: int|string}> $rows
     */
    private function throwUnavailableMany(array $rows): void
    {
        $ids = [];
        $numbers = [];
        $allSold = true;

        foreach ($rows as $row) {
            $status = (int)$row['status_ticket'];
            $ids[] = (int)$row['id_ticket'];
            $numbers[] = (string)$row['number_ticket'];
            if ($status !== TicketStatus::PAID) {
                $allSold = false;
            }
        }

        if (count($numbers) === 1) {
            $message = (int)$rows[0]['status_ticket'] === TicketStatus::PAID
                ? "Lo siento, el número {$numbers[0]} ya fue vendido."
                : "Lo siento, el número {$numbers[0]} ya no está disponible.";
        } else {
            $list = implode(', ', $numbers);
            $message = $allSold
                ? "Lo siento, estos números ya fueron vendidos: {$list}."
                : "Lo siento, estos números ya no están disponibles: {$list}.";
        }

        throw new DomainException($message, 'TICKET_UNAVAILABLE', [
            'unavailable_ticket_ids' => $ids,
            'unavailable_numbers' => $numbers,
        ]);
    }
}
