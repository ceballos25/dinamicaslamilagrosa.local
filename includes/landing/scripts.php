<script src="<?= ASSETS_URL ?>/libs/jquery/dist/jquery.min.js" defer></script>
<script src="<?= ASSETS_URL ?>/libs/bootstrap/dist/js/bootstrap.bundle.min.js" defer></script>
<script src="https://cdn.jsdelivr.net/npm/@splidejs/splide@4.1.4/dist/js/splide.min.js" defer></script>
<script src="<?= htmlspecialchars(ASSETS_URL . '/js/meta-events.js', ENT_QUOTES, 'UTF-8') ?>?v=9" defer></script>
<script src="<?= htmlspecialchars(ASSETS_URL . '/js/money-cop.js', ENT_QUOTES, 'UTF-8') ?>?v=2" defer></script>
<script>
window.PUBLIC_CSRF_TOKEN = <?= json_encode($_SESSION['csrf_token'] ?? '', JSON_UNESCAPED_UNICODE) ?>;
window.ASSETS_URL = <?= json_encode(ASSETS_URL, JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="<?= htmlspecialchars(ASSETS_URL . '/js/public-settings.js', ENT_QUOTES, 'UTF-8') ?>?v=1" defer></script>
<script src="<?= htmlspecialchars(ASSETS_URL . '/js/phone-co.js', ENT_QUOTES, 'UTF-8') ?>?v=1" defer></script>
<script src="<?= htmlspecialchars(ASSETS_URL . '/js/frontend.js', ENT_QUOTES, 'UTF-8') ?>?v=134" defer></script>
<script>
// Contentsquare (~170KB): solo tras interacción o a los 12s.
(function () {
    var loaded = false;
    function loadCs() {
        if (loaded) return;
        loaded = true;
        var s = document.createElement('script');
        s.src = 'https://t.contentsquare.net/uxa/8c88e0bc219df.js';
        s.async = true;
        document.head.appendChild(s);
        ['scroll', 'pointerdown', 'keydown', 'touchstart'].forEach(function (ev) {
            window.removeEventListener(ev, loadCs, { capture: true });
        });
    }
    ['scroll', 'pointerdown', 'keydown', 'touchstart'].forEach(function (ev) {
        window.addEventListener(ev, loadCs, { once: true, passive: true, capture: true });
    });
    window.addEventListener('load', function () {
        setTimeout(loadCs, 12000);
    }, { once: true });
})();
</script>
