<?php
declare(strict_types=1);

require_once __DIR__ . '/vapid-keys.php';

$vapidPublic = cr_vapid_keys()['publicKey'];
?>
<link rel="manifest" href="/manifest-admin.php">
<meta name="theme-color" content="#1e4db7">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="Panel">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<link rel="apple-touch-icon" sizes="180x180" href="/assets/images/pwa/icon-180.png">
<script>
window.ADMIN_VAPID_PUBLIC_KEY = <?= json_encode($vapidPublic, JSON_UNESCAPED_SLASHES) ?>;
if ('serviceWorker' in navigator) {
  window.addEventListener('load', function () {
    navigator.serviceWorker.register('/sw-admin.js', { scope: '/' }).catch(function () {});
  });
}
</script>
