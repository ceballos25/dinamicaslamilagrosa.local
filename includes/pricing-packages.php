<?php
declare(strict_types=1);

/**
 * Paquetes — Dinámica Navideña (4.º sorteo · 4 cifras).
 * Base $8.000 c/u · desde 10: $7.000 · desde 20: $6.500.
 * Mínimo 2 códigos.
 *
 * @return array<int, int> cantidad => total COP
 */
function cs_package_prices(): array
{
    return [
        2 => 16000,
        3 => 24000,
        5 => 40000,
        8 => 64000,
        10 => 70000,
        15 => 105000,
        20 => 130000,
    ];
}

function cs_package_unit_price(): int
{
    return 8000;
}

/** Precio unitario desde el primer umbral (10+). */
function cs_package_bulk_unit_price(): int
{
    return 7000;
}

function cs_package_bulk_threshold(): int
{
    return 10;
}

/** Segundo descuento (20+). */
function cs_package_bulk2_unit_price(): int
{
    return 6500;
}

function cs_package_bulk2_threshold(): int
{
    return 20;
}

function cs_package_min_quantity(): int
{
    return 2;
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
 * @return list<array{id: string, qty: int, price: int, compare_at: int, badge: ?string, badge_slug: ?string}>
 */
function cs_package_grid_items(): array
{
    $unit = cs_package_unit_price();
    $items = [
        ['id' => 'paq2', 'qty' => 2, 'price' => 16000, 'badge' => null, 'badge_slug' => null],
        ['id' => 'paq3', 'qty' => 3, 'price' => 24000, 'badge' => null, 'badge_slug' => null],
        ['id' => 'paq5', 'qty' => 5, 'price' => 40000, 'badge' => null, 'badge_slug' => null],
        ['id' => 'paq8', 'qty' => 8, 'price' => 64000, 'badge' => null, 'badge_slug' => null],
        ['id' => 'paq10', 'qty' => 10, 'price' => 70000, 'badge' => null, 'badge_slug' => null],
        ['id' => 'paq15', 'qty' => 15, 'price' => 105000, 'badge' => null, 'badge_slug' => null],
        ['id' => 'paq20', 'qty' => 20, 'price' => 130000, 'badge' => null, 'badge_slug' => null],
    ];

    foreach ($items as &$item) {
        $item['compare_at'] = (int)$item['qty'] * $unit;
    }
    unset($item);

    return $items;
}
