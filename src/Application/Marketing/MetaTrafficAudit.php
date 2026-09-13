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
