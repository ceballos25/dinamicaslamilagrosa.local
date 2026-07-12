<?php
declare(strict_types=1);

ob_start();

use App\Shared\Http\Request;
use App\Shared\Http\Response;

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$action = trim((string)($_POST['action'] ?? $_GET['action'] ?? ''));

// Lectura de settings: pública para landing; completa si hay admin con permiso.
if ($method !== 'POST' || $action === 'obtener') {
    require_once dirname(__DIR__, 2) . '/config/config.php';
    require_once dirname(__DIR__, 2) . '/controllers/settings.controller.php';

    $isAdminSettings = $method === 'POST'
        && $action === 'obtener'
        && function_exists('adminCan')
        && adminCan('settings', 'manage');

    Response::json(
        $isAdminSettings
            ? SettingsController::obtenerSettings()
            : SettingsController::obtenerSettingsPublicas()
    );
}

/** @var \App\Shared\Routing\Router $router */
$router = require dirname(__DIR__, 2) . '/bootstrap/app.php';
$result = $router->dispatch(Request::fromGlobals());

if ($result === null) {
    Response::json(['success' => false, 'message' => 'Ruta no encontrada'], 404);
}
