<?php
declare(strict_types=1);

namespace App\Application\Marketing;

final class MetaTrafficAudit
{
    public const ENTITY = 'meta';

    /** @var array<string, string> */
    private const EVENT_ACTIONS = [
        'PageView' => 'meta.page_view',
        'ViewContent' => 'meta.view_content',
        'AddToCart' => 'meta.add_to_cart',
        'InitiateCheckout' => 'meta.initiate_checkout',
        'Lead' => 'meta.lead',
        'AddPaymentInfo' => 'meta.add_payment_info',
    ];

    /** @var list<string> */
    public const LANDING_ACTIONS = [
        'meta.page_view',
        'meta.view_content',
        'meta.add_to_cart',
        'meta.initiate_checkout',
        'meta.lead',
        'meta.add_payment_info',
        'meta.initiate_checkout.sent',
    ];

    /** @var array<string, string> */
    public const LABELS = [
        'meta.page_view' => 'Visita a la landing',
        'meta.view_content' => 'Vio el premio',
        'meta.add_to_cart' => 'Agregó al carrito',
        'meta.initiate_checkout' => 'Inició el pago',
        'meta.lead' => 'Llenó el formulario',
        'meta.add_payment_info' => 'Abrió el pago',
        'meta.initiate_checkout.sent' => 'Envió el comprobante',
    ];

    /** @var list<array{action: string, label: string, hint: string, icon: string}> */
    public const FUNNEL = [
        ['action' => 'meta.page_view', 'label' => 'Visitas a la landing', 'hint' => 'Entraron a la página', 'icon' => 'ti-eye'],
        ['action' => 'meta.add_to_cart', 'label' => 'Agregó al carrito', 'hint' => 'Eligieron un paquete', 'icon' => 'ti-shopping-cart'],
        ['action' => 'meta.lead', 'label' => 'Llenó el formulario', 'hint' => 'Dejó nombre y celular', 'icon' => 'ti-user'],
        ['action' => 'meta.initiate_checkout', 'label' => 'Inició el pago', 'hint' => 'Abrió el checkout', 'icon' => 'ti-credit-card'],
        ['action' => 'meta.initiate_checkout.sent', 'label' => 'Envió comprobante', 'hint' => 'Mandó el pago', 'icon' => 'ti-receipt'],
    ];

    public static function label(string $action): string
    {
        return self::LABELS[$action] ?? $action;
    }

    /**
     * @param array<string, mixed> $detail
     */
    public static function record(string $eventName, array $detail = []): void
    {
        $action = self::EVENT_ACTIONS[trim($eventName)] ?? '';
        if ($action === '') {
            return;
        }

        if (!class_exists('AppContainer')) {
            return;
        }

        try {
            \AppContainer::get()->audit()->record(
                $action,
                self::ENTITY,
                null,
                null,
                self::sanitizeDetail($detail),
                null,
                true
            );
        } catch (\Throwable) {
        }
    }

    public static function actionForEvent(string $eventName): ?string
    {
        return self::EVENT_ACTIONS[trim($eventName)] ?? null;
    }

    /**
     * @param array<string, mixed> $detail
     * @return array<string, mixed>
     */
    private static function sanitizeDetail(array $detail): array
    {
        $clean = [];

        $eventId = trim((string)($detail['event_id'] ?? ''));
        if ($eventId !== '') {
            $clean['event_id'] = substr($eventId, 0, 80);
        }
        if (isset($detail['value']) && is_numeric($detail['value'])) {
            $clean['value'] = round((float)$detail['value'], 2);
        }
        if (isset($detail['num_items']) && is_numeric($detail['num_items'])) {
            $clean['num_items'] = max(0, (int)$detail['num_items']);
        }
        if (array_key_exists('capi', $detail)) {
            $clean['capi'] = (bool)$detail['capi'];
        }
        $path = trim((string)($detail['path'] ?? ''));
        if ($path !== '') {
            $clean['path'] = substr($path, 0, 120);
        }

        return $clean;
    }
}
