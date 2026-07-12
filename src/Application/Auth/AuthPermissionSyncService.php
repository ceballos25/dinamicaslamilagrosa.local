<?php
declare(strict_types=1);

namespace App\Application\Auth;

use App\Infrastructure\Repository\PdoRoleRepository;
use PDO;

final class AuthPermissionSyncService
{
    private ?bool $hasDescriptionColumn = null;

    public function __construct(
        private readonly PDO $pdo,
        private readonly PdoRoleRepository $roles
    ) {
    }

    /**
     * @return array{permissions: int, roles_updated: int, roles_repaired: int, descriptions_updated: int}
     */
    public function syncCatalogAndDefaults(): array
    {
        $permissionCount = $this->syncPermissionCatalog();
        $descriptionsUpdated = $this->syncPermissionDescriptions();
        $rolesUpdated = $this->syncDefaultRolePermissions();
        $rolesRepaired = $this->repairStandardRolePermissions();

        return [
            'permissions' => $permissionCount,
            'descriptions_updated' => $descriptionsUpdated,
            'roles_updated' => $rolesUpdated,
            'roles_repaired' => $rolesRepaired,
        ];
    }

    public function syncPermissionCatalog(): int
    {
        $count = 0;
        $hasDescription = $this->permissionsTableHasDescription();
        $select = $this->pdo->prepare(
            'SELECT id_permission FROM permissions
             WHERE module_permission = :m AND action_permission = :a LIMIT 1'
        );

        foreach (PermissionRegistry::all() as $item) {
            $select->execute([':m' => $item['module'], ':a' => $item['action']]);
            if ($select->fetchColumn()) {
                continue;
            }

            if ($hasDescription) {
                $insert = $this->pdo->prepare(
                    'INSERT INTO permissions (module_permission, action_permission, description_permission)
                     VALUES (:m, :a, :d)'
                );
                $insert->execute([
                    ':m' => $item['module'],
                    ':a' => $item['action'],
                    ':d' => $item['action_label'],
                ]);
            } else {
                $insert = $this->pdo->prepare(
                    'INSERT INTO permissions (module_permission, action_permission) VALUES (:m, :a)'
                );
                $insert->execute([':m' => $item['module'], ':a' => $item['action']]);
            }

            ++$count;
        }

        return $count;
    }

    public function syncPermissionDescriptions(): int
    {
        if (!$this->permissionsTableHasDescription()) {
            return 0;
        }

        $updated = 0;
        $stmt = $this->pdo->prepare(
            'UPDATE permissions
             SET description_permission = :d
             WHERE module_permission = :m AND action_permission = :a
               AND (description_permission IS NULL OR description_permission = \'\')'
        );

        foreach (PermissionRegistry::all() as $item) {
            $stmt->execute([
                ':m' => $item['module'],
                ':a' => $item['action'],
                ':d' => $item['action_label'],
            ]);
            $updated += $stmt->rowCount();
        }

        return $updated;
    }

    public function syncDefaultRolePermissions(): int
    {
        $updated = 0;
        foreach (PermissionRegistry::defaultRolePermissions() as $slug => $keys) {
            $role = $this->roles->findBySlug((string)$slug);
            if ($role === null) {
                continue;
            }
            $roleId = (int)$role['id_role'];
            if ($this->roles->countPermissionsForRole($roleId) > 0) {
                continue;
            }

            $permissionIds = $keys === '*'
                ? $this->roles->findAllPermissionIds()
                : $this->roles->findPermissionIdsByKeys(is_array($keys) ? $keys : []);

            if ($permissionIds !== []) {
                $this->roles->syncPermissions($roleId, $permissionIds);
                ++$updated;
            }
        }

        return $updated;
    }

    public function repairStandardRolePermissions(): int
    {
        $repaired = 0;
        foreach (PermissionRegistry::defaultRolePermissions() as $slug => $keys) {
            if ($keys === '*') {
                continue;
            }

            $role = $this->roles->findBySlug((string)$slug);
            if ($role === null) {
                continue;
            }

            $roleId = (int)$role['id_role'];
            $existing = $this->roles->findPermissionIdsForRole($roleId);
            $required = $this->roles->findPermissionIdsByKeys(is_array($keys) ? $keys : []);
            $merged = array_values(array_unique(array_merge($existing, $required)));

            if (count($merged) > count($existing)) {
                $this->roles->syncPermissions($roleId, $merged);
                ++$repaired;
            }
        }

        return $repaired;
    }

    private function permissionsTableHasDescription(): bool
    {
        if ($this->hasDescriptionColumn !== null) {
            return $this->hasDescriptionColumn;
        }

        try {
            $stmt = $this->pdo->query("SHOW COLUMNS FROM permissions LIKE 'description_permission'");
            $this->hasDescriptionColumn = $stmt !== false && $stmt->fetch() !== false;
        } catch (\Throwable) {
            $this->hasDescriptionColumn = false;
        }

        return $this->hasDescriptionColumn;
    }
}
