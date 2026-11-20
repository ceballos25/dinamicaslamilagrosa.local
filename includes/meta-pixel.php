<?php
declare(strict_types=1);

use App\Application\Marketing\MetaConversionsApi;
use App\Application\Marketing\MetaPixelGuard;

function cs_meta_pixel_head(): void
{
    static $rendered = false;
    if ($rendered) {
        return;
    }
    $rendered = true;

    if (!MetaConversionsApi::isPixelConfigured()) {
        return;
    }

    $guard = new MetaPixelGuard();
    $metaPixelId = MetaConversionsApi::pixelId();
    $metaPageViewEventId = '';
    $sendPageView = $guard->shouldSendPageView();
    // isCapiReady = token+pixel Y META_CAPI_ENABLED=true (antes solo miraba credenciales)
    $sendCapiPageView = $sendPageView && MetaConversionsApi::isCapiReady();
    $pageViewRef = (string)(session_id() ?: 'guest') . '-' . $guard->pageViewStorageKey();

    $pageViewPath = (string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/');

    if ($sendCapiPageView) {
        $metaPageViewEventId = MetaConversionsApi::eventId('PageView', $pageViewRef);
        if (MetaConversionsApi::sendPageView($metaPageViewEventId)) {
            $guard->markPageViewSent();
            \App\Application\Marketing\MetaTrafficAudit::record('PageView', [
                'event_id' => $metaPageViewEventId,
                'capi' => true,
                'path' => $pageViewPath,
            ]);
        }
        // Si CAPI falla, no marcamos: reintenta en la próxima carga.
    } elseif ($sendPageView) {
        $metaPageViewEventId = MetaConversionsApi::eventId('PageView', $pageViewRef);
        $guard->markPageViewSent();
        \App\Application\Marketing\MetaTrafficAudit::record('PageView', [
            'event_id' => $metaPageViewEventId,
            'capi' => false,
            'path' => $pageViewPath,
        ]);
    }
    ?>
    <script>
    // Pixel (~100KB): tras interacción o a los 8s. PageView queda en cola / CAPI.
    window.fbq = window.fbq || function () { (window.fbq.q = window.fbq.q || []).push(arguments); };
    window.fbq.q = window.fbq.q || [];
    fbq('init', <?= json_encode($metaPixelId, JSON_UNESCAPED_SLASHES) ?>);
    <?php if ($sendPageView && $metaPageViewEventId !== ''): ?>
    fbq('track', 'PageView', {}, {eventID: <?= json_encode($metaPageViewEventId, JSON_UNESCAPED_SLASHES) ?>});
    <?php endif; ?>
    (function () {
        var loaded = false;
        function loadPixel() {
            if (loaded) return;
            loaded = true;
            var t = document.createElement('script');
            t.async = true;
            t.src = 'https://connect.facebook.net/en_US/fbevents.js';
            var s = document.getElementsByTagName('script')[0];
            s.parentNode.insertBefore(t, s);
            ['scroll', 'pointerdown', 'keydown', 'touchstart'].forEach(function (ev) {
                window.removeEventListener(ev, loadPixel, { capture: true });
            });
        }
        ['scroll', 'pointerdown', 'keydown', 'touchstart'].forEach(function (ev) {
            window.addEventListener(ev, loadPixel, { once: true, passive: true, capture: true });
        });
        window.addEventListener('load', function () {
            setTimeout(loadPixel, 10000);
        }, { once: true });
    })();
    </script>
    <noscript><img height="1" width="1" style="display:none"
    src="https://www.facebook.com/tr?id=<?= htmlspecialchars($metaPixelId, ENT_QUOTES, 'UTF-8') ?>&ev=PageView&noscript=1"
    /></noscript>
    <script>
    window.META_EVENTS_CONFIG = {
        enabled: true,
        capiEnabled: <?= MetaConversionsApi::isCapiReady() ? 'true' : 'false' ?>,
        pixelId: <?= json_encode($metaPixelId, JSON_UNESCAPED_SLASHES) ?>,
        ajaxUrl: <?= json_encode(BASE_URL . '/front/ajax/meta.ajax.php', JSON_UNESCAPED_SLASHES) ?>,
        standardEvents: <?= json_encode(MetaConversionsApi::BROWSER_TRACK_EVENTS, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
    };
    </script>
    <?php
}
