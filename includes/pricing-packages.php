<?php
declare(strict_types=1);

/**
 * Paquetes por cantidad — Dinámicas La Milagrosa (evento a 5 números).
 * Base $1.500 c/u · desde 40 números $1.200 c/u.
 *
 * @return array<int, int> cantidad => total COP
 */
function cs_package_prices(): array
{
    return [
        10 => 15000,
        18 => 27000,
        30 => 45000,
        40 => 48000,
        60 => 72000,
        100 => 120000,
        200 => 240000,
        300 => 360000,
    ];
}

function cs_package_unit_price(): int
{
    return 1500;
}

function cs_package_bulk_unit_price(): int
{
    return 1200;
}

function cs_package_bulk_threshold(): int
{
    return 40;
}

function cs_package_min_quantity(): int
{
    return 10;
}

function cs_package_pricing_enabled(): bool
{
    return true;
}

/**
 * @return array{total: int, qty: int, is_package: bool, unit_price: int}
 */
function cs_calculate_package_total(int $quantity): array
{
    $qty = max(0, $quantity);
    $min = cs_package_min_quantity();
    $packages = cs_package_prices();

    if ($qty < $min) {
        return [
            'total' => 0,
            'qty' => $qty,
            'is_package' => false,
            'unit_price' => cs_package_unit_price(),
        ];
    }

    if (isset($packages[$qty])) {
        $total = (int)$packages[$qty];

        return [
            'total' => $total,
            'qty' => $qty,
            'is_package' => true,
            'unit_price' => (int)round($total / $qty),
        ];
    }

    $unit = $qty >= cs_package_bulk_threshold()
        ? cs_package_bulk_unit_price()
        : cs_package_unit_price();

    return [
        'total' => $qty * $unit,
        'qty' => $qty,
        'is_package' => false,
        'unit_price' => $unit,
    ];
}

/**
 * @return list<array{id: string, qty: int, price: int, badge: ?string, badge_slug: ?string}>
 */
function cs_package_grid_items(): array
{
    return [
        ['id' => 'paq10', 'qty' => 10, 'price' => 15000, 'badge' => null, 'badge_slug' => null],
        ['id' => 'paq18', 'qty' => 18, 'price' => 27000, 'badge' => null, 'badge_slug' => null],
        ['id' => 'paq30', 'qty' => 30, 'price' => 45000, 'badge' => 'Popular', 'badge_slug' => 'popular'],
        ['id' => 'paq40', 'qty' => 40, 'price' => 48000, 'badge' => 'Ahorro', 'badge_slug' => 'ahorro'],
        ['id' => 'paq60', 'qty' => 60, 'price' => 72000, 'badge' => null, 'badge_slug' => null],
        ['id' => 'paq100', 'qty' => 100, 'price' => 120000, 'badge' => 'Más vendido', 'badge_slug' => 'mas-vendido'],
        ['id' => 'paq200', 'qty' => 200, 'price' => 240000, 'badge' => null, 'badge_slug' => null],
        ['id' => 'paq300', 'qty' => 300, 'price' => 360000, 'badge' => 'Mega', 'badge_slug' => 'mega'],
    ];
}
