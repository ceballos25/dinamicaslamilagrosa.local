<?php
declare(strict_types=1);

namespace App\Application\Reporting;

/**
 * Formato legible para celdas de reportes (tabla, Excel, PDF).
 */
final class ReportDisplayFormatter
{
  private const PAYMENT_LABELS = [
        'Efectivo' => 'Efectivo',
        'efectivo' => 'Efectivo',
        'transferencia' => 'Transferencia',
        'Transferencia' => 'Transferencia',
        'transfer' => 'Transferencia',
        'manual' => 'Manual',
    ];

    public static function format(mixed $value, string $format = 'text'): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return match ($format) {
            'currency' => self::currency($value),
            'status_sale' => self::statusSale($value),
            'status_ticket' => self::statusTicket($value),
            'payment_method' => self::paymentMethod($value),
            'boolean' => self::boolean($value),
            'integer' => self::integer($value),
            default => (string)$value,
        };
    }

    public static function currency(mixed $value): string
    {
        if (!is_numeric($value)) {
            return (string)$value;
        }

        return '$' . number_format((float)$value, 0, ',', '.');
    }

    public static function statusSale(mixed $value): string
    {
        return match ((int)$value) {
            1 => 'Completada',
            0 => 'Pendiente',
            2 => 'Anulada',
            default => (string)$value,
        };
    }

    public static function statusTicket(mixed $value): string
    {
        return match ((int)$value) {
            0 => 'Disponible',
            1 => 'Vendido',
            2 => 'Reservado',
            default => (string)$value,
        };
    }

    public static function paymentMethod(mixed $value): string
    {
        $key = trim((string)$value);

        return self::PAYMENT_LABELS[$key] ?? $key;
    }

    public static function boolean(mixed $value): string
    {
        return ((int)$value === 1 || $value === true || $value === '1') ? 'Sí' : 'No';
    }

    public static function integer(mixed $value): string
    {
        if (!is_numeric($value)) {
            return (string)$value;
        }

        return number_format((float)$value, 0, ',', '.');
    }

    /**
     * @return 'currency'|'status_sale'|'status_ticket'|'payment_method'|'boolean'|'integer'|'text'
     */
    public static function inferFormatForField(string $fieldKey, string $alias = '', ?string $aggregateFn = null): string
    {
        $fn = strtoupper($aggregateFn ?? '');

        if (in_array($fieldKey, ['total_sale', 'price_raffle'], true)) {
            return 'currency';
        }

        if (in_array($fieldKey, ['quantity_sale'], true) && in_array($fn, ['SUM', 'COUNT', ''], true)) {
            return $fn === 'AVG' ? 'currency' : 'integer';
        }

        if ($fieldKey === 'status_sale') {
            return 'status_sale';
        }

        if ($fieldKey === 'status_ticket') {
            return 'status_ticket';
        }

        if ($fieldKey === 'payment_method_sale') {
            return 'payment_method';
        }

        if (in_array($fieldKey, ['is_premium_ticket', 'is_winner_ticket'], true)) {
            return 'boolean';
        }

        $aliasLower = strtolower($alias);
        if (preg_match('/(total|recaudo|monto|precio|venta_cop)/', $aliasLower)) {
            return 'currency';
        }

        if (in_array($aliasLower, ['premium', 'ganador'], true)) {
            return 'boolean';
        }

        if (in_array($aliasLower, ['medio_pago', 'pago'], true)) {
            return 'payment_method';
        }

        if (in_array($aliasLower, ['origen'], true)) {
            return 'text';
        }

        if (preg_match('/^(num_|cantidad|tickets|numero)/', $aliasLower) && $fn !== 'AVG') {
            return 'integer';
        }

        if ($fn === 'COUNT') {
            return 'integer';
        }

        if ($fn === 'AVG' && str_contains($fieldKey, 'sale')) {
            return 'currency';
        }

        return 'text';
    }

    public static function labelForField(string $fieldKey): string
    {
        static $labels = [
            'id_sale' => 'ID venta',
            'code_sale' => 'Código venta',
            'total_sale' => 'Total venta',
            'quantity_sale' => 'Cantidad números',
            'payment_method_sale' => 'Medio de pago',
            'date_sale_day' => 'Día',
            'date_created_sale' => 'Fecha venta',
            'source_sale' => 'Origen',
            'status_sale' => 'Estado venta',
            'id_admin_sale' => 'ID vendedor',
            'id_raffle_sale' => 'ID rifa',
            'name_customer' => 'Nombres',
            'lastname_customer' => 'Apellidos',
            'phone_customer' => 'Celular',
            'email_customer' => 'Correo',
            'title_raffle' => 'Rifa',
            'id_ticket' => 'ID ticket',
            'number_ticket' => 'Número',
            'status_ticket' => 'Estado número',
            'is_winner_ticket' => 'Ganador',
            'is_premium_ticket' => 'Premium',
            'id_raffle_ticket' => 'ID rifa',
            'date_ticket_day' => 'Día',
            'date_created_ticket' => 'Fecha creación',
            'date_updated_ticket' => 'Última actualización',
            'code_sale' => 'Código venta',
        ];

        return $labels[$fieldKey] ?? ucfirst(str_replace('_', ' ', $fieldKey));
    }

    public static function labelForMeasure(string $fn, string $field, string $alias): string
    {
        $base = self::labelForField($field === '*' ? 'id_sale' : $field);
        if ($field === '*') {
            return match (strtoupper($fn)) {
                'COUNT' => 'Cantidad registros',
                default => $alias,
            };
        }

        return match (strtoupper($fn)) {
            'SUM' => match ($field) {
                'total_sale' => 'Total recaudado',
                'quantity_sale' => 'Cantidad números',
                default => 'Total ' . strtolower($base),
            },
            'AVG' => 'Promedio ' . strtolower($base),
            'MIN' => 'Mínimo ' . strtolower($base),
            'MAX' => 'Máximo ' . strtolower($base),
            'COUNT' => $field === '*' ? 'Cantidad registros' : 'Cantidad ' . strtolower($base),
            default => $alias,
        };
    }
}
