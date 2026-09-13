<?php
declare(strict_types=1);

require_once __DIR__ . '/vapid-keys.php';

$pwaName = defined('SITE_NAME') && SITE_NAME ? (string)SITE_NAME : 'Dinámicas La Milagrosa';
$pwaIcon = htmlspecialchars(cr_request_origin() . '/pwa-icon.php?s=180', ENT_QUOTES, 'UTF-8');
$vapidPublic = cr_vapid_keys()['publicKey'];
?>
<link rel="manifest" href="/manifest-admin.php">
<meta name="theme-color" content="#1e4db7">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="Panel">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<link rel="apple-touch-icon" href="<?= $pwaIcon ?>">
<script>
window.ADMIN_VAPID_PUBLIC_KEY = <?= json_encode($vapidPublic, JSON_UNESCAPED_SLASHES) ?>;
if ('serviceWorker' in navigator) {
  window.addEventListener('load', function () {
    navigator.serviceWorker.register('/sw-admin.js', { scope: '/' }).catch(function () {});
  });
}
</script>
