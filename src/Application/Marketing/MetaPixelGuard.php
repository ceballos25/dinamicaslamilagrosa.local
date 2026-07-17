<?php
declare(strict_types=1);

namespace App\Application\Marketing;

/**
 * Meta Pixel / CAPI anti-duplication: PageView, InitiateCheckout, Purchase.
 */
final class MetaPixelGuard
{
    private const SESSION_PAGEVIEW_PREFIX = 'meta_pageview_sent_';
    private const PURCHASE_PREFIX = 'meta_purchase_sent_';
    private const INITIATE_CHECKOUT_PREFIX = 'meta_initiate_checkout_sent_';

    public function pageViewStorageKey(): string
    {
        $uri = (string)($_SERVER['REQUEST_URI'] ?? '/');
        $path = (string)(parse_url($uri, PHP_URL_PATH) ?: '/');

        return md5($path) . '-' . date('Ymd');
    }

    public function shouldSendPageView(): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return false;
        }

        return empty($_SESSION[self::SESSION_PAGEVIEW_PREFIX . $this->pageViewStorageKey()]);
    }

    public function markPageViewSent(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION[self::SESSION_PAGEVIEW_PREFIX . $this->pageViewStorageKey()] = true;
        }
    }

    public function shouldSendInitiateCheckout(string $transferCode): bool
    {
        if ($transferCode === '') {
            return false;
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            $key = self::INITIATE_CHECKOUT_PREFIX . $transferCode;
            if (!empty($_SESSION[$key])) {
                return false;
            }
        }

        return !$this->checkDbInitiateCheckoutSent($transferCode);
    }

    public function markInitiateCheckoutSent(string $transferCode): void
    {
        if (session_status() === PHP_SESSION_ACTIVE && $transferCode !== '') {
            $_SESSION[self::INITIATE_CHECKOUT_PREFIX . $transferCode] = true;
        }
    }

    public function shouldSendPurchase(string $saleCode): bool
    {
        if ($saleCode === '') {
            return false;
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            $key = self::PURCHASE_PREFIX . $saleCode;
            if (!empty($_SESSION[$key])) {
                return false;
            }
        }

        return !$this->checkDbPurchaseSent($saleCode);
    }

    public function markPurchaseSent(string $saleCode): void
    {
        if (session_status() === PHP_SESSION_ACTIVE && $saleCode !== '') {
            $_SESSION[self::PURCHASE_PREFIX . $saleCode] = true;
        }
    }

    private function checkDbPurchaseSent(string $saleCode): bool
    {
        return $this->checkDbAuditSent('meta.purchase.sent', $saleCode);
    }

    private function checkDbInitiateCheckoutSent(string $transferCode): bool
    {
        return $this->checkDbAuditSent('meta.initiate_checkout.sent', $transferCode);
    }

    private function checkDbAuditSent(string $action, string $code): bool
    {
        if ($code === '' || !class_exists('Db')) {
            return false;
        }
        try {
            $row = Db::fetchOne(
                'SELECT id_audit FROM audit_logs WHERE action_audit = :a AND new_data LIKE :code LIMIT 1',
                [':a' => $action, ':code' => '%"' . $code . '"%']
            );

            return $row !== null && $row !== false;
        } catch (\Throwable) {
            return false;
        }
    }
}
