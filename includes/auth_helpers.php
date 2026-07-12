<?php
declare(strict_types=1);

use App\Infrastructure\Repository\PdoPermissionRepository;

function adminId(): int
{
    return (int)($_SESSION['user_id'] ?? 0);
}

function adminRoleSlug(): string
{
    return strtolower((string)($_SESSION['user_role'] ?? ''));
}

function adminPermissions(): PdoPermissionRepository
{
    static $repo = null;
    if ($repo === null) {
        require_once dirname(__DIR__) . '/bootstrap/container.php';
        $repo = AppContainer::get()->permissions();
    }

    return $repo;
}

function adminCan(string $module, string $action): bool
{
    $adminId = adminId();
    if ($adminId <= 0) {
        return false;
    }

    if (adminPermissions()->adminHasPermission($adminId, $module, $action)) {
        return true;
    }

    $assigned = adminPermissions()->getAdminPermissions($adminId);
    if ($assigned !== []) {
        return false;
    }

    return adminLegacyCan($module, $action);
}

function adminLegacyCan(string $module, string $action): bool
{
    $role = adminRoleSlug();
    if ($role === '' || str_contains($role, 'superadmin')) {
        return str_contains($role, 'superadmin');
    }

    $perm = strtolower($module . '.' . $action);
    $isAdmin = str_contains($role, 'admin');
    $isStaff = $isAdmin || str_contains($role, 'vendedor') || str_contains($role, 'auditor');

    if (str_ends_with($perm, '.view') && $isStaff) {
        return true;
    }

    if (str_ends_with($perm, '.create') && $isStaff && str_starts_with($perm, 'ventas.')) {
        return true;
    }

    if (str_ends_with($perm, '.manage') && $isAdmin) {
        return !in_array($perm, ['usuarios.manage', 'roles.manage'], true);
    }

    if (str_ends_with($perm, '.edit') && $isAdmin) {
        return true;
    }

    if (str_ends_with($perm, '.delete') && $isAdmin) {
        return true;
    }

    if (str_ends_with($perm, '.pause') && $isAdmin) {
        return true;
    }

    if (str_ends_with($perm, '.export') && $isAdmin) {
        return true;
    }

    if (str_ends_with($perm, '.approve') && $isAdmin) {
        return true;
    }

    if (str_ends_with($perm, '.cancel') && $isAdmin) {
        return true;
    }

    if (str_ends_with($perm, '.cancel_partial') && $isAdmin) {
        return true;
    }

    return false;
}

function adminRequirePermission(string $module, string $action): void
{
    if (!adminCan($module, $action)) {
        header('Location: ' . BASE_URL . '/front/dashboard.php?error=forbidden');
        exit;
    }
}

/** @return array<string, bool> */
function adminCapsMap(): array
{
    $keys = [
        'dashboard.view',
        'ventas.view', 'ventas.create', 'ventas.cancel', 'ventas.cancel_partial',
        'rifas.view', 'rifas.create', 'rifas.edit', 'rifas.delete', 'rifas.pause',
        'transferencias.view', 'transferencias.approve',
        'clientes.view', 'clientes.edit',
        'usuarios.view', 'usuarios.manage',
        'roles.view', 'roles.manage',
        'auditoria.view',
        'configuracion.view', 'configuracion.manage',
        'settings.manage',
        'reportes.view', 'reportes.export',
    ];

    $caps = [];
    foreach ($keys as $key) {
        [$module, $action] = explode('.', $key, 2);
        $caps[$key] = adminCan($module, $action);
    }

    return $caps;
}
