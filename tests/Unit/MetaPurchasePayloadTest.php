<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Application\Marketing\MetaConversionsApi;
use PHPUnit\Framework\TestCase;

final class MetaPurchasePayloadTest extends TestCase
{
    public function testPurchaseEventIdIsDeterministicBySaleId(): void
    {
        $first = MetaConversionsApi::purchaseEventId(42, 'CR-2026-001');
        $second = MetaConversionsApi::purchaseEventId(42, 'CR-2026-001');

        self::assertSame($first, $second);
        self::assertStringStartsWith('purchase-', $first);
    }

    public function testPurchaseCustomDataFollowsMetaExample(): void
    {
        $customData = MetaConversionsApi::buildPurchaseCustomDataFromSale([
            'id_sale' => 7,
            'code_sale' => 'CR-007',
            'total_sale' => 65000,
            'quantity_sale' => 1,
        ]);

        self::assertSame([
            'currency' => 'COP',
            'value' => 65000.0,
            'order_id' => 'CR-007',
        ], $customData);
    }

    public function testPurchaseUserDataIncludesFullCustomerAndCheckoutContext(): void
    {
        $customer = MetaConversionsApi::userDataFromCustomer([
            'email_customer' => 'test@example.com',
            'phone_customer' => '3001234567',
            'name_customer' => 'Juan Perez',
            'lastname_customer' => 'Garcia',
            'id_customer_sale' => '55',
        ]);

        self::assertArrayHasKey('em', $customer);
        self::assertArrayHasKey('ph', $customer);
        self::assertArrayHasKey('fn', $customer);
        self::assertArrayHasKey('external_id', $customer);

        $checkout = MetaConversionsApi::userDataFromStoredMeta([
            'fbp' => 'fb.1.123.abc',
            'client_ip_address' => '190.0.0.1',
        ]);

        self::assertSame('190.0.0.1', $checkout['client_ip_address']);
    }

    public function testPurchaseIsServerOnlyNotBrowserTrackEvent(): void
    {
        self::assertFalse(MetaConversionsApi::isAllowedTrackEvent('Purchase'));
        self::assertTrue(MetaConversionsApi::isStandardEvent('Purchase'));
    }

    public function testInitiateCheckoutIsAllowedTrackAndStandardEvent(): void
    {
        self::assertTrue(MetaConversionsApi::isAllowedTrackEvent('InitiateCheckout'));
        self::assertTrue(MetaConversionsApi::isStandardEvent('InitiateCheckout'));
    }

    public function testStandardFunnelEventsAreAllowed(): void
    {
        foreach (['PageView', 'ViewContent', 'AddToCart', 'InitiateCheckout', 'Lead', 'AddPaymentInfo'] as $ev) {
            self::assertTrue(MetaConversionsApi::isAllowedTrackEvent($ev), "Expected {$ev} to be allowed in browser track");
            self::assertTrue(MetaConversionsApi::isStandardEvent($ev), "Expected {$ev} to be a standard event");
        }
        self::assertFalse(MetaConversionsApi::isAllowedTrackEvent('Purchase'), 'Purchase must stay server-only');
        self::assertTrue(MetaConversionsApi::isStandardEvent('Purchase'));
    }

    public function testInitiateCheckoutCustomDataIncludesValueAndNumItems(): void
    {
        $customData = MetaConversionsApi::buildInitiateCheckoutCustomData([
            'id_transfer' => 99,
            'code_transfer' => '0123456789',
            'value' => 240000,
            'quantity' => 200,
        ]);

        self::assertSame([
            'currency' => 'COP',
            'value' => 240000.0,
            'num_items' => 200,
        ], $customData);
    }

    public function testInitiateCheckoutEventIdIsDeterministicByTransferId(): void
    {
        $first = MetaConversionsApi::initiateCheckoutEventId(15, '0123456789');
        $second = MetaConversionsApi::initiateCheckoutEventId(15, '0123456789');

        self::assertSame($first, $second);
        self::assertStringStartsWith('initiatecheckout-', $first);
    }

    public function testUserDataFromInputHashesEmailAndPhone(): void
    {
        $userData = MetaConversionsApi::userDataFromInput([
            'email' => 'Ada@Example.com',
            'phone' => '3001234567',
            'name' => 'Ada Lovelace',
            'lastname' => 'Byron',
            'city' => 'Medellín',
            'state' => 'Antioquia',
        ]);

        self::assertSame(hash('sha256', 'ada@example.com'), $userData['em']);
        self::assertSame(hash('sha256', '573001234567'), $userData['ph']);
        self::assertSame(hash('sha256', 'ada'), $userData['fn']);
        self::assertArrayNotHasKey('email', $userData);
    }

    public function testRememberedMatchUserDataIsReusedForLaterPageViews(): void
    {
        $this->resetMetaMatchStorage();

        $hashed = MetaConversionsApi::userDataFromInput([
            'email' => 'ada@example.com',
            'phone' => '3001234567',
            'name' => 'Ada',
            'lastname' => 'Lovelace',
        ]);
        MetaConversionsApi::rememberMatchUserData($hashed);

        $stored = MetaConversionsApi::storedMatchUserData();
        self::assertSame($hashed['em'], $stored['em']);
        self::assertSame($hashed['ph'], $stored['ph']);
        self::assertSame(64, strlen($stored['em']));
        self::assertArrayNotHasKey('fbp', $stored);
        self::assertArrayNotHasKey('client_ip_address', $stored);
    }

    public function testStoredMatchUserDataIgnoresTamperedValues(): void
    {
        $this->resetMetaMatchStorage();
        $_COOKIE['_cs_mm'] = json_encode([
            'em' => 'not-a-hash',
            'ph' => str_repeat('a', 64),
            'evil' => str_repeat('b', 64),
        ], JSON_THROW_ON_ERROR);

        $stored = MetaConversionsApi::storedMatchUserData();
        self::assertArrayNotHasKey('em', $stored);
        self::assertArrayNotHasKey('evil', $stored);
        self::assertSame(str_repeat('a', 64), $stored['ph']);
    }

    public function testRememberMatchDoesNotOverwriteWithEmptyPayload(): void
    {
        $this->resetMetaMatchStorage();
        $hashed = MetaConversionsApi::userDataFromInput(['email' => 'ada@example.com']);
        MetaConversionsApi::rememberMatchUserData($hashed);
        MetaConversionsApi::rememberMatchUserData([]);

        $stored = MetaConversionsApi::storedMatchUserData();
        self::assertSame($hashed['em'], $stored['em']);
    }

    private function resetMetaMatchStorage(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        unset($_SESSION['meta_match_user_data'], $_COOKIE['_cs_mm']);
    }
}
