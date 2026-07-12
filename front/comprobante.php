<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../bootstrap/container.php';
require_once __DIR__ . '/../controllers/transfersController.php';

use App\Infrastructure\Repository\PdoPermissionRepository;

$adminId = (int)($_SESSION['user_id'] ?? 0);
if ($adminId <= 0) {
    http_response_code(401);
    exit('No autenticado');
}

$permissions = new PdoPermissionRepository();
$allowed = $permissions->adminHasPermission($adminId, 'transferencias', 'view');
if (!$allowed) {
    $role = strtolower((string)($_SESSION['user_role'] ?? ''));
    $allowed = str_contains($role, 'admin')
        || str_contains($role, 'superadmin')
        || str_contains($role, 'vendedor')
        || str_contains($role, 'auditor');
}

if (!$allowed) {
    http_response_code(403);
    exit('No autorizado');
}

$idTransfer = (int)($_GET['id_transfer'] ?? 0);
if ($idTransfer <= 0) {
    http_response_code(400);
    exit('Solicitud inválida');
}

$row = Db::fetchOne(
    'SELECT url_transfer FROM transfers WHERE id_transfer = :id LIMIT 1',
    [':id' => $idTransfer]
);
if (!$row || trim((string)($row->url_transfer ?? '')) === '') {
    http_response_code(404);
    exit('Comprobante no encontrado');
}

$path = TransfersController::resolveComprobanteFilesystemPath((string)$row->url_transfer);
if ($path === null) {
    http_response_code(404);
    exit('Archivo no encontrado');
}

$mime = 'image/jpeg';
if (function_exists('finfo_open')) {
    $f = finfo_open(FILEINFO_MIME_TYPE);
    if ($f !== false) {
        $detected = finfo_file($f, $path);
        finfo_close($f);
        if (is_string($detected) && str_starts_with($detected, 'image/')) {
            $mime = $detected;
        }
    }
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . (string)filesize($path));
header('Cache-Control: private, max-age=300');
header('X-Content-Type-Options: nosniff');

readfile($path);
exit;
