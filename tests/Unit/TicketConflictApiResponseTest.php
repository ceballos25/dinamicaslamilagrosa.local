<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Application\Ticket\TicketConflictApiResponse;
use App\Domain\Ticket\ValueObject\TicketStatus;
use App\Shared\Exception\DomainException;
use PHPUnit\Framework\TestCase;

final class TicketConflictApiResponseTest extends TestCase
{
    public function testFromDomainExceptionIncludesUnavailablePayload(): void
    {
        $e = new DomainException('Lo siento, el número 042 ya fue vendido.', 'TICKET_UNAVAILABLE', [
            'unavailable_ticket_ids' => [42],
            'unavailable_numbers' => ['042'],
        ]);

        $response = TicketConflictApiResponse::fromDomainException($e);

        self::assertFalse($response['success']);
        self::assertSame('TICKET_UNAVAILABLE', $response['error_code']);
        self::assertSame([42], $response['unavailable_ticket_ids']);
        self::assertSame(['042'], $response['unavailable_numbers']);
    }

    public function testFromUnavailableBuildsSoldMessage(): void
    {
        $response = TicketConflictApiResponse::fromUnavailable(9, '009', TicketStatus::PAID);

        self::assertSame('Lo siento, el número 009 ya fue vendido.', $response['message']);
        self::assertSame([9], $response['unavailable_ticket_ids']);
    }
}
