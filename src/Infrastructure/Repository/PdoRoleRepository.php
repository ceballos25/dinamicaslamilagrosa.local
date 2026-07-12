<?php
declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Application\Auth\PermissionRegistry;
use App\Infrastructure\Database\PdoFactory;
use PDO;

final class PdoRoleRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? PdoFactory::get();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findAll(): array
    {
        $stmt = $this->pdo->query(
            'SELECT r.id_role, r.name_role, r.slug_role,
                    (SELECT COUNT(*) FROM admins a WHERE a.id_role = r.id_role) AS admin_count
             FROM roles r
             ORDER BY r.id_role ASC'
        );

        return $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id_role, name_role, slug_role FROM roles WHERE id_role = :id LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id_role, name_role, slug_role FROM roles WHERE slug_role = :slug LIMIT 1'
        );
        $stmt->execute([':slug' => strtolower(trim($slug))]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function slugExists(string $slug, ?int $excludeId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM roles WHERE slug_role = :slug';
        $params = [':slug' => strtolower(trim($slug))];
        if ($excludeId !== null) {
            $sql .= ' AND id_role != :id';
            $params[':id'] = $excludeId;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int)$stmt->fetchColumn() > 0;
    }

    public function create(string $name, string $slug): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO roles (name_role, slug_role) VALUES (:name, :slug)'
        );
        $stmt->execute([
            ':name' => trim($name),
            ':slug' => strtolower(trim($slug)),
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $id, string $name, string $slug): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE roles SET name_role = :name, slug_role = :slug WHERE id_role = :id'
        );
        $stmt->execute([
            ':id' => $id,
            ':name' => trim($name),
            ':slug' => strtolower(trim($slug)),
        ]);
    }

    public function delete(int $id): void
    {
        $role = $this->findById($id);
        if ($role === null) {
            return;
        }

        if ($this->isSystemSlug((string)$role['slug_role'])) {
            throw new \DomainException('No se puede eliminar un rol del sistema');
        }

        $count = $this->countAdminsForRole($id);
        if ($count > 0) {
            throw new \DomainException('No se puede eliminar un rol con usuarios asignados');
        }

        $this->pdo->prepare('DELETE FROM role_permissions WHERE id_role = :id')->execute([':id' => $id]);
        $this->pdo->prepare('DELETE FROM roles WHERE id_role = :id')->execute([':id' => $id]);
    }

    public function countAdminsForRole(int $roleId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM admins WHERE id_role = :id');
        $stmt->execute([':id' => $roleId]);

        return (int)$stmt->fetchColumn();
    }

    public function countPermissionsForRole(int $roleId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM role_permissions WHERE id_role = :id');
        $stmt->execute([':id' => $roleId]);

        return (int)$stmt->fetchColumn();
    }

    /**
     * @return list<int>
     */
    public function findPermissionIdsForRole(int $roleId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id_permission FROM role_permissions WHERE id_role = :id ORDER BY id_permission'
        );
        $stmt->execute([':id' => $roleId]);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

    /**
     * @param list<string> $keys
     * @return list<int>
     */
    public function findPermissionIdsByKeys(array $keys): array
    {
        if ($keys === []) {
            return [];
        }

        $ids = [];
        $stmt = $this->pdo->prepare(
            'SELECT id_permission FROM permissions
             WHERE module_permission = :m AND action_permission = :a LIMIT 1'
        );
        foreach ($keys as $key) {
            $key = strtolower(trim($key));
            if (!PermissionRegistry::isValidKey($key)) {
                continue;
            }
            [$module, $action] = explode('.', $key, 2);
            $stmt->execute([':m' => $module, ':a' => $action]);
            $id = (int)$stmt->fetchColumn();
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @return list<int>
     */
    public function findAllPermissionIds(): array
    {
        $stmt = $this->pdo->query('SELECT id_permission FROM permissions ORDER BY id_permission');

        return array_map('intval', $stmt ? ($stmt->fetchAll(PDO::FETCH_COLUMN) ?: []) : []);
    }

    /**
     * @param list<int> $permissionIds
     */
    public function syncPermissions(int $roleId, array $permissionIds): void
    {
        $permissionIds = array_values(array_unique(array_filter(array_map('intval', $permissionIds), static fn (int $id): bool => $id > 0)));

        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare('DELETE FROM role_permissions WHERE id_role = :id')->execute([':id' => $roleId]);
            if ($permissionIds !== []) {
                $insert = $this->pdo->prepare(
                    'INSERT INTO role_permissions (id_role, id_permission) VALUES (:role, :perm)'
                );
                foreach ($permissionIds as $permId) {
                    $insert->execute([':role' => $roleId, ':perm' => $permId]);
                }
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findAllPermissions(): array
    {
        $stmt = $this->pdo->query(
            'SELECT id_permission, module_permission, action_permission
             FROM permissions
             ORDER BY module_permission, action_permission'
        );

        return $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    }

    public function isSystemSlug(string $slug): bool
    {
        return in_array(strtolower(trim($slug)), PermissionRegistry::SYSTEM_ROLE_SLUGS, true);
    }
}
