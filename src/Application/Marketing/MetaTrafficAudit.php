<?php
declare(strict_types=1);

namespace App\Application\Marketing;

final class MetaTrafficAudit
{
    public const ENTITY = 'meta';

    /** @var array<string, string> */
    private const EVENT_ACTIONS = [
        'PageView' => 'meta.page_view',
        'AddToCart' => 'meta.add_to_cart',
        'InitiateCheckout' => 'meta.initiate_checkout',
        'Lead' => 'meta.lead',
        'AddPaymentInfo' => 'meta.add_payment_info',
    ];

    /** @var list<string> */
    public const LANDING_ACTIONS = [
        'meta.page_view',
        'meta.add_to_cart',
        'meta.initiate_checkout',
        'meta.lead',
        'meta.add_payment_info',
        'meta.whatsapp_pay',
        'meta.initiate_checkout.sent',
    ];

    /** @var array<string, string> */
    public const LABELS = [
        'meta.page_view' => 'Vio la página',
        'meta.add_to_cart' => 'Agregó al carrito',
        'meta.initiate_checkout' => 'Inició el pago',
        'meta.lead' => 'Llenó el formulario',
        'meta.add_payment_info' => 'Abrió el pago',
        'meta.whatsapp_pay' => 'Prefirió WhatsApp',
        'meta.initiate_checkout.sent' => 'Envió el comprobante',
    ];

    /** @var list<array{action: string, label: string, hint: string, icon: string}> */
    public const FUNNEL = [
        ['action' => 'meta.page_view', 'label' => 'Vio la página', 'hint' => 'Entró a la página', 'icon' => 'ti-eye'],
        ['action' => 'meta.add_to_cart', 'label' => 'Agregó al carrito', 'hint' => 'Eligieron un paquete', 'icon' => 'ti-shopping-cart'],
        ['action' => 'meta.lead', 'label' => 'Llenó el formulario', 'hint' => 'Dejó nombre y celular', 'icon' => 'ti-user'],
        ['action' => 'meta.initiate_checkout', 'label' => 'Inició el pago', 'hint' => 'Abrió el checkout', 'icon' => 'ti-credit-card'],
        ['action' => 'meta.whatsapp_pay', 'label' => 'Prefirió WhatsApp', 'hint' => 'Clic en pagar por WhatsApp', 'icon' => 'ti-brand-whatsapp'],
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

        $eventId = trim((string)($detail['event_id'] ?? ''));
        if ($eventId !== '' && self::wasAuditRecorded($eventId)) {
            return;
        }

        self::recordAction($action, $detail);

        if ($eventId !== '') {
            self::markAuditRecorded($eventId);
        }
    }

    /**
     * @param array<string, mixed> $detail
     */
    public static function recordAction(string $action, array $detail = []): void
    {
        if (!isset(self::LABELS[$action])) {
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
    private static function wasAuditRecorded(string $eventId): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return false;
        }

        return !empty($_SESSION['meta_audit_evt_' . $eventId]);
    }

    private static function markAuditRecorded(string $eventId): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['meta_audit_evt_' . $eventId] = true;
        }
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
