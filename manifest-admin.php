<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/vapid-keys.php';

$name = defined('SITE_NAME') && SITE_NAME ? (string)SITE_NAME : 'Dinámicas La Milagrosa';
$origin = cr_request_origin();
$icon180 = $origin . '/pwa-icon.php?s=180';
$icon192 = $origin . '/pwa-icon.php?s=192';
$icon512 = $origin . '/pwa-icon.php?s=512';

header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: no-store');

echo json_encode([
    'id' => $origin . '/front/dashboard.php',
    'name' => 'Panel ' . $name,
    'short_name' => 'Panel',
    'description' => 'Avisos de comprobantes y gestión del panel',
    'start_url' => $origin . '/front/dashboard.php',
    'scope' => $origin . '/',
    'display' => 'standalone',
    'display_override' => ['standalone', 'minimal-ui'],
    'orientation' => 'any',
    'background_color' => '#ffffff',
    'theme_color' => '#1e4db7',
    'icons' => [
        ['src' => $icon180, 'sizes' => '180x180', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => $icon192, 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => $icon512, 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
