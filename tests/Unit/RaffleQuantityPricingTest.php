<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Application\Pricing\RaffleQuantityPricing;
use PHPUnit\Framework\TestCase;

final class RaffleQuantityPricingTest extends TestCase
{
    private function pricing(): RaffleQuantityPricing
    {
        return RaffleQuantityPricing::forTest(true, 65000, 60000, 55000);
    }

    public function testFirstNumberCosts65000(): void
    {
        $result = $this->pricing()->calculate(1);

        self::assertSame(65000, $result['total']);
        self::assertFalse($result['promo_active']);
    }

    public function testTwoNumbersCost120000At60000Each(): void
    {
        $result = $this->pricing()->calculate(2);

        self::assertSame(120000, $result['total']);
        self::assertTrue($result['promo_active']);
        self::assertTrue($result['pair_promo_active']);
        self::assertSame(2, $result['second_count']);
    }

    public function testThreeNumbers(): void
    {
        $result = $this->pricing()->calculate(3);

        self::assertSame(165000, $result['total']);
        self::assertSame(3, $result['third_plus_count']);
        self::assertSame(0, $result['second_count']);
        self::assertTrue($result['promo_active']);
        self::assertFalse($result['pair_promo_active']);
    }

    public function testFourNumbers(): void
    {
        $result = $this->pricing()->calculate(4);

        self::assertSame(220000, $result['total']);
        self::assertSame(4, $result['third_plus_count']);
    }

    public function testBulkThresholdUsesReducedUnitFromForty(): void
    {
        $pricing = RaffleQuantityPricing::forTest(true, 1500, 1500, 1200, 40);

        self::assertSame(45000, $pricing->calculate(30)['total']);
        self::assertFalse($pricing->calculate(30)['promo_active']);

        self::assertSame(48000, $pricing->calculate(40)['total']);
        self::assertTrue($pricing->calculate(40)['promo_active']);

        self::assertSame(120000, $pricing->calculate(100)['total']);
    }

    public function testPublicArrayExposesBulkThreshold(): void
    {
        $pricing = RaffleQuantityPricing::forTest(true, 1500, 1500, 1200, 40);
        $public = $pricing->toPublicArray();

        self::assertSame(40, $public['bulk_threshold']);
        self::assertSame(1500, $public['first_unit']);
        self::assertSame(1200, $public['third_plus_unit']);
    }
}
