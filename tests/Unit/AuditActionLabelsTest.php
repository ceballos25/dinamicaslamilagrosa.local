<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Application\Audit\AuditActionLabels;
use App\Application\Marketing\MetaTrafficAudit;
use PHPUnit\Framework\TestCase;

final class AuditActionLabelsTest extends TestCase
{
    public function testMetaFunnelUsesReadableSpanishLabels(): void
    {
        self::assertSame('Visualización', AuditActionLabels::label('meta.page_view'));
        self::assertSame('Inició el pago', AuditActionLabels::label('meta.initiate_checkout'));
        self::assertSame('Envió comprobante', AuditActionLabels::label('meta.initiate_checkout.sent'));
        self::assertSame('Compra en Meta', AuditActionLabels::label('meta.purchase.sent'));
        self::assertTrue(AuditActionLabels::isMetaTraffic('meta.page_view'));
        self::assertFalse(AuditActionLabels::isMetaTraffic('sale.created'));
    }

    public function testSearchMatchesSpanishLabels(): void
    {
        $keys = AuditActionLabels::actionsMatching('visualización');
        self::assertContains('meta.page_view', $keys);

        $pago = AuditActionLabels::actionsMatching('pago');
        self::assertContains('meta.initiate_checkout', $pago);
        self::assertContains('meta.add_payment_info', $pago);
    }

    public function testBrowserEventsMapToAuditActionsExceptPurchase(): void
    {
        self::assertSame('meta.page_view', MetaTrafficAudit::actionForEvent('PageView'));
        self::assertSame('meta.initiate_checkout', MetaTrafficAudit::actionForEvent('InitiateCheckout'));
        self::assertSame('meta.lead', MetaTrafficAudit::actionForEvent('Lead'));
        self::assertNull(MetaTrafficAudit::actionForEvent('Purchase'));
    }
}
