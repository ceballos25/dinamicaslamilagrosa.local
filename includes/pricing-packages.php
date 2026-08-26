<?php
declare(strict_types=1);

/**
 * Paquetes por cantidad — Dinámicas La Milagrosa (3era Dinámica · COMBO PAREJA).
 * Base $2.000 c/u · desde 40: $1.700 · desde 100: $1.500.
 *
 * @return array<int, int> cantidad => total COP
 */
function cs_package_prices(): array
{
    return [
        10 => 20000,
        18 => 36000,
        30 => 60000,
        40 => 68000,
        60 => 102000,
        100 => 150000,
    ];
}

function cs_package_unit_price(): int
{
    return 2000;
}

/** Precio unitario desde el primer umbral (40+). */
function cs_package_bulk_unit_price(): int
{
    return 1700;
}

function cs_package_bulk_threshold(): int
{
    return 40;
}

/** Segundo descuento (100+). */
function cs_package_bulk2_unit_price(): int
{
    return 1500;
}

function cs_package_bulk2_threshold(): int
{
    return 100;
}

function cs_package_min_quantity(): int
{
    return 10;
}

function cs_package_pricing_enabled(): bool
{
    return true;
}

function cs_package_unit_for_quantity(int $quantity): int
{
    $qty = max(0, $quantity);
    if ($qty >= cs_package_bulk2_threshold()) {
        return cs_package_bulk2_unit_price();
    }
    if ($qty >= cs_package_bulk_threshold()) {
        return cs_package_bulk_unit_price();
    }

    return cs_package_unit_price();
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

    $unit = cs_package_unit_for_quantity($qty);

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
        ['id' => 'paq10', 'qty' => 10, 'price' => 20000, 'badge' => null, 'badge_slug' => null],
        ['id' => 'paq18', 'qty' => 18, 'price' => 36000, 'badge' => null, 'badge_slug' => null],
        ['id' => 'paq30', 'qty' => 30, 'price' => 60000, 'badge' => 'Popular', 'badge_slug' => 'popular'],
        ['id' => 'paq40', 'qty' => 40, 'price' => 68000, 'badge' => 'Ahorro', 'badge_slug' => 'ahorro'],
        ['id' => 'paq60', 'qty' => 60, 'price' => 102000, 'badge' => null, 'badge_slug' => null],
        ['id' => 'paq100', 'qty' => 100, 'price' => 150000, 'badge' => 'Más vendido', 'badge_slug' => 'mas-vendido'],
    ];
}
