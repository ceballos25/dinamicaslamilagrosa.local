<?php
declare(strict_types=1);

namespace App\Domain\Raffle\ValueObject;

final class RaffleType
{
    public const MANUAL = 'manual';
    public const AUTOMATIC = 'automatic';
    public const BOTH = 'both';

    private function __construct()
    {
    }

    public static function isValid(string $type): bool
    {
        return in_array($type, [self::MANUAL, self::AUTOMATIC, self::BOTH], true);
    }

    public static function allowsPickedTickets(string $type): bool
    {
        return $type === self::MANUAL || $type === self::BOTH;
    }

    public static function requiresPickedTickets(string $type): bool
    {
        return $type === self::MANUAL;
    }
}
