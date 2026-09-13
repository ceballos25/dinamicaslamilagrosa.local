<?php
declare(strict_types=1);

namespace App\Application\Audit;

final class AuditActionLabels
{
    /** @var array<string, string> */
    public const LABELS = [
        'meta.page_view' => 'Vio la página',
        'meta.add_to_cart' => 'Agregó al carrito',
        'meta.initiate_checkout' => 'Inició el pago',
        'meta.lead' => 'Llenó el formulario',
        'meta.add_payment_info' => 'Abrió el pago',
        'meta.whatsapp_pay' => 'Prefirió WhatsApp',
        'meta.initiate_checkout.sent' => 'Envió el comprobante',
        'sale.created' => 'Venta creada',
        'sale.cancelled.total' => 'Venta anulada',
        'sale.cancelled.partial' => 'Venta anulada parcial',
        'raffle.created' => 'Rifa creada',
        'raffle.paused' => 'Rifa pausada',
        'raffle.resumed' => 'Rifa reanudada',
        'raffle.finished' => 'Rifa finalizada',
        'raffle.hidden' => 'Rifa oculta',
        'raffle.deleted' => 'Rifa eliminada',
        'raffle.sales_blocked' => 'Ventas bloqueadas',
        'raffle.sales_unblocked' => 'Ventas desbloqueadas',
    ];

    public static function label(string $action): string
    {
        return self::LABELS[$action] ?? $action;
    }

    public static function isMetaTraffic(string $action): bool
    {
        return str_starts_with($action, 'meta.');
    }

    /**
     * @return list<string>
     */
    public static function actionsMatching(string $query): array
    {
        $query = mb_strtolower(trim($query));
        if ($query === '') {
            return [];
        }

        $keys = [];
        foreach (self::LABELS as $action => $label) {
            if (str_contains(mb_strtolower($action), $query) || str_contains(mb_strtolower($label), $query)) {
                $keys[] = $action;
            }
        }

        return $keys;
    }
}
