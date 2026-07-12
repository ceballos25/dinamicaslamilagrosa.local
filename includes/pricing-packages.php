<?php
declare(strict_types=1);

/**
 * Paquetes por cantidad (legacy, desactivado). Combo Stickers usa precio unitario en la rifa.
 *
 * @return array<int, int> cantidad => total COP
 */
function cs_package_prices(): array
{
    return [];
}

function cs_package_unit_price(): int
{
    return 0;
}

function cs_package_min_quantity(): int
{
    return 1;
}

function cs_package_pricing_enabled(): bool
{
    return false;
}

/**
 * @return array{total: int, qty: int, is_package: bool, unit_price: int}
 */
function cs_calculate_package_total(int $quantity): array
{
    $qty = max(0, $quantity);

    return ['total' => 0, 'qty' => $qty, 'is_package' => false, 'unit_price' => 0];
}

/**
 * @return list<array{id: string, qty: int, price: int, badge: ?string, badge_slug: ?string}>
 */
function cs_package_grid_items(): array
{
    return [];
}
