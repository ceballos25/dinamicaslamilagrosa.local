<?php
declare(strict_types=1);

namespace App\Application\Audit;

final class AuditActionLabels
{
    /** @var array<string, string> */
    public const LABELS = [
        'meta.page_view' => 'Visualización',
        'meta.view_content' => 'Vio el contenido',
        'meta.add_to_cart' => 'Eligió paquete',
        'meta.initiate_checkout' => 'Inició el pago',
        'meta.lead' => 'Dejó sus datos',
        'meta.add_payment_info' => 'Abrió el pago',
        'meta.initiate_checkout.sent' => 'Envió comprobante',
        'meta.purchase.sent' => 'Compra en Meta',
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

    /** @var list<array{action: string, label: string, hint: string}> */
    public const META_FUNNEL = [
        ['action' => 'meta.page_view', 'label' => 'Visualizaciones', 'hint' => 'Entradas a la landing'],
        ['action' => 'meta.initiate_checkout', 'label' => 'Iniciaron pago', 'hint' => 'Clics al checkout'],
        ['action' => 'meta.lead', 'label' => 'Dejaron datos', 'hint' => 'Nombre y celular'],
        ['action' => 'meta.initiate_checkout.sent', 'label' => 'Comprobantes', 'hint' => 'Pago enviado'],
        ['action' => 'meta.purchase.sent', 'label' => 'Compras', 'hint' => 'Aprobadas en Meta'],
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
