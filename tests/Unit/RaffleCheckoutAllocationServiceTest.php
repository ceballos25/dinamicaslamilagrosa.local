<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Application\Ticket\RaffleCheckoutAllocationService;
use App\Application\Ticket\TicketReservationService;
use App\Domain\Raffle\ValueObject\RaffleType;
use App\Shared\Exception\DomainException;
use PHPUnit\Framework\TestCase;
use Tests\Support\InMemoryRaffleRepository;
use Tests\Support\InMemoryTicketRepository;

final class RaffleCheckoutAllocationServiceTest extends TestCase
{
    public function testDecodeTicketIdsFromJsonString(): void
    {
        self::assertSame([1, 2, 3], RaffleCheckoutAllocationService::decodeTicketIds('[1,2,3,2]'));
    }

    public function testDecodeTicketIdsFromArray(): void
    {
        self::assertSame([10, 20], RaffleCheckoutAllocationService::decodeTicketIds([10, 20]));
    }

    public function testDecodeTicketIdsEmpty(): void
    {
        self::assertSame([], RaffleCheckoutAllocationService::decodeTicketIds(null));
        self::assertSame([], RaffleCheckoutAllocationService::decodeTicketIds(''));
        self::assertSame([], RaffleCheckoutAllocationService::decodeTicketIds('not-json'));
    }

    public function testAutomaticRaffleDoesNotReserveOnPendingPayment(): void
    {
        $raffles = InMemoryRaffleRepository::automatic(5);
        $tickets = new InMemoryTicketRepository();
        $tickets->seed(1, 5, '001');

        $reservations = new TicketReservationService($tickets, $raffles, $this->mockPdo());
        $service = new RaffleCheckoutAllocationService($reservations, $raffles, $this->mockPdo());

        $result = $service->reserveForPendingPayment(5, 3, [1], true);

        self::assertSame([], $result['ticket_ids']);
        self::assertNull($result['expires_at']);
        self::assertSame(RaffleType::AUTOMATIC, $result['allocation_mode']);
        self::assertSame(0, $tickets->statusOf(1));
    }

    public function testManualRaffleTransferHoldUntilReview(): void
    {
        $raffles = InMemoryRaffleRepository::manual(7, 15);
        $tickets = new InMemoryTicketRepository();
        $tickets->seed(100, 7, '100');
        $tickets->seed(101, 7, '101');
        $tickets->seed(102, 7, '102');

        $reservations = new TicketReservationService($tickets, $raffles, $this->mockPdo());
        $service = new RaffleCheckoutAllocationService($reservations, $raffles, $this->mockPdo());

        $result = $service->reserveForPendingPayment(7, 3, [100, 101, 102], true);

        self::assertSame([100, 101, 102], $result['ticket_ids']);
        self::assertNull($result['expires_at']);
        self::assertNull($tickets->expiresAtOf(100));
    }

    public function testManualRafflePseSetsExpiry(): void
    {
        $raffles = InMemoryRaffleRepository::manual(8, 1);
        $tickets = new InMemoryTicketRepository();
        $tickets->seed(200, 8, '200');

        $reservations = new TicketReservationService($tickets, $raffles, $this->mockPdo());
        $service = new RaffleCheckoutAllocationService($reservations, $raffles, $this->mockPdo());

        $result = $service->reserveForPendingPayment(8, 1, [200], false);

        self::assertNotNull($result['expires_at']);
        self::assertNotNull($tickets->expiresAtOf(200));
    }

    public function testManualRaffleRequiresTicketSelection(): void
    {
        $raffles = InMemoryRaffleRepository::manual(9);
        $service = new RaffleCheckoutAllocationService(
            new TicketReservationService(new InMemoryTicketRepository(), $raffles, $this->mockPdo()),
            $raffles,
            $this->mockPdo()
        );

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Debes seleccionar los códigos');

        $service->reserveForPendingPayment(9, 3, null, false);
    }

    public function testBothRaffleReservesOnlyWhenCustomerPicksNumbers(): void
    {
        $raffles = InMemoryRaffleRepository::both(11);
        $tickets = new InMemoryTicketRepository();
        $tickets->seed(1, 11, '0001');
        $tickets->seed(2, 11, '0002');
        $tickets->seed(3, 11, '0003');

        $service = new RaffleCheckoutAllocationService(
            new TicketReservationService($tickets, $raffles, $this->mockPdo()),
            $raffles,
            $this->mockPdo()
        );

        $random = $service->reserveForPendingPayment(11, 3, null, true);
        self::assertSame([], $random['ticket_ids']);
        self::assertSame(RaffleType::AUTOMATIC, $random['allocation_mode']);

        $picked = $service->reserveForPendingPayment(11, 3, [1, 2, 3], true);
        self::assertSame([1, 2, 3], $picked['ticket_ids']);
        self::assertSame(RaffleType::MANUAL, $picked['allocation_mode']);
    }

    public function testResolveForAutomaticApprovalReturnsNullTicketIds(): void
    {
        $raffles = InMemoryRaffleRepository::automatic(10);
        $service = new RaffleCheckoutAllocationService(
            new TicketReservationService(new InMemoryTicketRepository(), $raffles, $this->mockPdo()),
            $raffles,
            $this->mockPdo()
        );

        $result = $service->resolveTicketIdsForSaleApproval(10, 3, [1, 2, 3]);

        self::assertNull($result['ticket_ids']);
    }

    private function mockPdo(): \PDO
    {
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('rowCount')->willReturn(0);
        $stmt->method('fetchAll')->willReturn([]);

        $pdo = $this->createMock(\PDO::class);
        $pdo->method('beginTransaction')->willReturn(true);
        $pdo->method('commit')->willReturn(true);
        $pdo->method('rollBack')->willReturn(true);
        $pdo->method('inTransaction')->willReturn(false);
        $pdo->method('prepare')->willReturn($stmt);

        return $pdo;
    }
}
