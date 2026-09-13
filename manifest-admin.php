<?php
declare(strict_types=1);

header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: no-store');

$manifest = __DIR__ . '/manifest-admin.webmanifest';
if (is_file($manifest)) {
    readfile($manifest);
    exit;
}

echo json_encode([
    'id' => '/front/dashboard.php',
    'name' => 'Panel Dinámicas La Milagrosa',
    'short_name' => 'Panel',
    'start_url' => '/front/dashboard.php',
    'scope' => '/',
    'display' => 'standalone',
    'background_color' => '#ffffff',
    'theme_color' => '#1e4db7',
    'icons' => [
        ['src' => '/pwa-icon.php?s=180', 'sizes' => '180x180', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => '/pwa-icon.php?s=192', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => '/pwa-icon.php?s=512', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
