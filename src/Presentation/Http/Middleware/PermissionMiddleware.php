<?php
declare(strict_types=1);

namespace App\Presentation\Http\Middleware;

use App\Infrastructure\Repository\PdoPermissionRepository;
use App\Shared\Http\Response;

final class PermissionMiddleware
{
    public function __construct(
        private readonly PdoPermissionRepository $permissions
    ) {
    }

    /**
     * @param array<string, list<string>> $permissionsByAction
     */
    public function authorize(string $action, array $permissionsByAction): void
    {
        if (!isset($permissionsByAction[$action])) {
            return;
        }

        $adminId = (int)($_SESSION['user_id'] ?? 0);
        if ($adminId <= 0) {
            Response::json(['success' => false, 'message' => 'No autenticado'], 401);
        }

        $required = $permissionsByAction[$action];
        foreach ($required as $perm) {
            [$module, $permAction] = array_pad(explode('.', $perm, 2), 2, '');
            if ($this->permissions->adminHasPermission($adminId, $module, $permAction)) {
                return;
            }
        }

        if ($this->fallbackLegacyRole($required, $adminId)) {
            return;
        }

        Response::json(['success' => false, 'message' => 'No autorizado para esta acción'], 403);
    }

    /**
     * @param list<string> $required
     */
    private function fallbackLegacyRole(array $required, int $adminId): bool
    {
        if ($this->permissions->getAdminPermissions($adminId) !== []) {
            return false;
        }

        foreach ($required as $perm) {
            $parts = explode('.', strtolower($perm), 2);
            if (count($parts) === 2 && function_exists('adminLegacyCan') && adminLegacyCan($parts[0], $parts[1])) {
                return true;
            }
        }

        return false;
    }
}

