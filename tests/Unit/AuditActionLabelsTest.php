<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Application\Audit\AuditActionLabels;
use App\Application\Marketing\MetaTrafficAudit;
use PHPUnit\Framework\TestCase;

final class AuditActionLabelsTest extends TestCase
{
    public function testOperationalAuditKeepsReadableLabels(): void
    {
        self::assertSame('Venta creada', AuditActionLabels::label('sale.created'));
        self::assertFalse(AuditActionLabels::isMetaTraffic('sale.created'));
    }

    public function testLandingTrackingLabels(): void
    {
        self::assertSame('Vio la página', MetaTrafficAudit::label('meta.page_view'));
        self::assertSame('Agregó al carrito', MetaTrafficAudit::label('meta.add_to_cart'));
        self::assertSame('Llenó el formulario', MetaTrafficAudit::label('meta.lead'));
        self::assertContains('meta.page_view', MetaTrafficAudit::LANDING_ACTIONS);
        self::assertContains('meta.add_to_cart', MetaTrafficAudit::LANDING_ACTIONS);
        self::assertContains('meta.lead', MetaTrafficAudit::LANDING_ACTIONS);
        self::assertContains('meta.whatsapp_pay', MetaTrafficAudit::LANDING_ACTIONS);
        self::assertSame('Prefirió WhatsApp', MetaTrafficAudit::label('meta.whatsapp_pay'));
        self::assertNotContains('meta.view_content', MetaTrafficAudit::LANDING_ACTIONS);
        self::assertNotContains('meta.purchase.sent', MetaTrafficAudit::LANDING_ACTIONS);
    }

    public function testBrowserEventsMapToLandingActionsExceptPurchase(): void
    {
        self::assertSame('meta.page_view', MetaTrafficAudit::actionForEvent('PageView'));
        self::assertSame('meta.add_to_cart', MetaTrafficAudit::actionForEvent('AddToCart'));
        self::assertSame('meta.lead', MetaTrafficAudit::actionForEvent('Lead'));
        self::assertNull(MetaTrafficAudit::actionForEvent('ViewContent'));
        self::assertNull(MetaTrafficAudit::actionForEvent('Purchase'));
    }
}
