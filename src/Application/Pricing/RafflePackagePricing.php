<?php
declare(strict_types=1);

namespace App\Application\Pricing;

use App\Shared\Config\DynamicConfig;

require_once dirname(__DIR__, 3) . '/includes/pricing-packages.php';

/**
 * Precios por paquetes fijos (legacy — desactivado; ver pricing-packages.php).
 */
final class RafflePackagePricing
{
    public const SETTING_ENABLED = 'pricing_packages_enabled';

    private function __construct(private readonly bool $enabled)
    {
    }

    public static function fromConfig(?DynamicConfig $config = null): self
    {
        if (!function_exists('cs_package_pricing_enabled') || !cs_package_pricing_enabled()) {
            return new self(false);
        }

        if ($config === null) {
            return new self(true);
        }

        $raw = strtolower(trim((string)$config->get(self::SETTING_ENABLED, '1')));

        return new self(!in_array($raw, ['0', 'false', 'no', 'off', ''], true));
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * @return array{
     *   total: int,
     *   qty: int,
     *   is_package: bool,
     *   unit_price: int,
     *   enabled: bool,
     *   min_quantity: int,
     *   packages: list<array{qty: int, price: int}>
     * }
     */
    public function calculate(int $quantity): array
    {
        if (!$this->enabled) {
            return [
                'total' => 0,
                'qty' => max(0, $quantity),
                'is_package' => false,
                'unit_price' => cs_package_unit_price(),
                'enabled' => false,
                'min_quantity' => cs_package_min_quantity(),
                'packages' => [],
            ];
        }

        $result = cs_calculate_package_total($quantity);
        $packages = [];
        foreach (cs_package_prices() as $qty => $price) {
            $packages[] = ['qty' => $qty, 'price' => $price];
        }

        return [
            'total' => $result['total'],
            'qty' => $result['qty'],
            'is_package' => $result['is_package'],
            'unit_price' => $result['unit_price'],
            'enabled' => true,
            'min_quantity' => cs_package_min_quantity(),
            'packages' => $packages,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toPublicArray(): array
    {
        $packages = [];
        foreach (cs_package_prices() as $qty => $price) {
            $packages[] = ['qty' => $qty, 'price' => $price];
        }

        return [
            'mode' => 'packages',
            'enabled' => $this->enabled,
            'unit_price' => cs_package_unit_price(),
            'min_quantity' => cs_package_min_quantity(),
            'packages' => $packages,
            'grid_items' => cs_package_grid_items(),
        ];
    }
}
