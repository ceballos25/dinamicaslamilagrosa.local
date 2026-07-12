<?php
declare(strict_types=1);

namespace App\Presentation\Http\Controller;

use App\Application\Auth\AuthPermissionSyncService;
use App\Application\Auth\PermissionRegistry;
use App\Infrastructure\Repository\PdoRoleRepository;
use App\Presentation\Http\Middleware\CsrfMiddleware;
use App\Presentation\Http\Middleware\PermissionMiddleware;
use App\Shared\Audit\AuditLogger;
use App\Shared\Exception\DomainException;
use App\Shared\Http\Request;
use App\Shared\Http\Response;
use Throwable;

final class RoleHttpController
{
    private const ACTIONS = [
        'listar',
        'obtener',
        'catalogo',
        'crear',
        'actualizar',
        'eliminar',
        'asignar_permisos',
        'sincronizar_catalogo',
    ];

    private const PERMS = [
        'listar' => ['roles.view', 'usuarios.view'],
        'obtener' => ['roles.view', 'usuarios.view'],
        'catalogo' => ['roles.view', 'usuarios.view'],
        'crear' => ['roles.manage'],
        'actualizar' => ['roles.manage'],
        'eliminar' => ['roles.manage'],
        'asignar_permisos' => ['roles.manage'],
        'sincronizar_catalogo' => ['roles.manage'],
    ];

    private const CSRF_ACTIONS = ['crear', 'actualizar', 'eliminar', 'asignar_permisos', 'sincronizar_catalogo'];

    public function __construct(
        private readonly PdoRoleRepository $roles,
        private readonly AuthPermissionSyncService $sync,
        private readonly PermissionMiddleware $permissions,
        private readonly CsrfMiddleware $csrf,
        private readonly AuditLogger $audit
    ) {
    }

    public function __invoke(Request $request): never
    {
        try {
            $action = trim((string)$request->input('action', ''));
            if (!in_array($action, self::ACTIONS, true)) {
                Response::json(['success' => false, 'message' => 'Acción no válida'], 422);
            }

            $this->permissions->authorize($action, self::PERMS);
            $this->csrf->handle($request, self::CSRF_ACTIONS);

            $result = match ($action) {
                'listar' => ['success' => true, 'data' => $this->roles->findAll()],
                'obtener' => $this->obtener($request),
                'catalogo' => $this->catalogo(),
                'crear' => $this->crear($request),
                'actualizar' => $this->actualizar($request),
                'eliminar' => $this->eliminar($request),
                'asignar_permisos' => $this->asignarPermisos($request),
                'sincronizar_catalogo' => $this->sincronizarCatalogo(),
                default => ['success' => false],
            };

            $this->audit->log('roles.' . $action, ['success' => (bool)($result['success'] ?? true)]);
            Response::json($result);
        } catch (Throwable $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    private function obtener(Request $request): array
    {
        $id = (int)$request->input('id_role', 0);
        if ($id <= 0) {
            throw new DomainException('ID de rol inválido');
        }

        $role = $this->roles->findById($id);
        if ($role === null) {
            throw new DomainException('Rol no encontrado');
        }

        return [
            'success' => true,
            'data' => [
                'role' => $role,
                'permission_ids' => $this->roles->findPermissionIdsForRole($id),
                'is_system' => $this->roles->isSystemSlug((string)$role['slug_role']),
            ],
        ];
    }

    private function catalogo(): array
    {
        $this->sync->syncPermissionCatalog();

        $dbPermissions = $this->roles->findAllPermissions();
        $labels = [];
        foreach (PermissionRegistry::all() as $item) {
            $labels[$item['key']] = $item;
        }

        $permissions = [];
        foreach ($dbPermissions as $row) {
            $key = strtolower($row['module_permission'] . '.' . $row['action_permission']);
            $meta = $labels[$key] ?? null;
            $permissions[] = [
                'id_permission' => (int)$row['id_permission'],
                'module' => $row['module_permission'],
                'action' => $row['action_permission'],
                'key' => $key,
                'module_label' => $meta['module_label'] ?? $row['module_permission'],
                'action_label' => $meta['action_label'] ?? $row['action_permission'],
            ];
        }

        return [
            'success' => true,
            'data' => [
                'permissions' => $permissions,
                'grouped' => PermissionRegistry::grouped(),
            ],
        ];
    }

    private function crear(Request $request): array
    {
        $name = trim((string)$request->input('name_role', ''));
        $slug = $this->normalizeSlug((string)$request->input('slug_role', $name));

        if ($name === '' || $slug === '') {
            throw new DomainException('Nombre y slug del rol son obligatorios');
        }
        if ($this->roles->slugExists($slug)) {
            throw new DomainException('Ya existe un rol con ese identificador');
        }

        $id = $this->roles->create($name, $slug);

        return ['success' => true, 'id_role' => $id, 'message' => 'Rol creado'];
    }

    private function actualizar(Request $request): array
    {
        $id = (int)$request->input('id_role', 0);
        if ($id <= 0) {
            throw new DomainException('ID de rol inválido');
        }

        $role = $this->roles->findById($id);
        if ($role === null) {
            throw new DomainException('Rol no encontrado');
        }

        $name = trim((string)$request->input('name_role', (string)$role['name_role']));
        $slugInput = trim((string)$request->input('slug_role', ''));
        $slug = $slugInput !== ''
            ? $this->normalizeSlug($slugInput)
            : (string)$role['slug_role'];

        if ($name === '' || $slug === '') {
            throw new DomainException('Nombre y slug del rol son obligatorios');
        }
        if ($this->roles->isSystemSlug((string)$role['slug_role']) && $slug !== (string)$role['slug_role']) {
            throw new DomainException('No se puede cambiar el identificador de un rol del sistema');
        }
        if ($this->roles->slugExists($slug, $id)) {
            throw new DomainException('Ya existe otro rol con ese identificador');
        }

        $this->roles->update($id, $name, $slug);

        return ['success' => true, 'message' => 'Rol actualizado'];
    }

    private function eliminar(Request $request): array
    {
        $id = (int)$request->input('id_role', 0);
        if ($id <= 0) {
            throw new DomainException('ID de rol inválido');
        }

        $this->roles->delete($id);

        return ['success' => true, 'message' => 'Rol eliminado'];
    }

    private function asignarPermisos(Request $request): array
    {
        $id = (int)$request->input('id_role', 0);
        if ($id <= 0) {
            throw new DomainException('ID de rol inválido');
        }

        $role = $this->roles->findById($id);
        if ($role === null) {
            throw new DomainException('Rol no encontrado');
        }

        $raw = $request->input('permission_ids', '[]');
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $permissionIds = is_array($decoded) ? $decoded : [];
        } else {
            $permissionIds = is_array($raw) ? $raw : [];
        }

        $this->roles->syncPermissions($id, array_map('intval', $permissionIds));

        return ['success' => true, 'message' => 'Permisos actualizados'];
    }

    private function sincronizarCatalogo(): array
    {
        $result = $this->sync->syncCatalogAndDefaults();

        return [
            'success' => true,
            'message' => 'Catálogo sincronizado',
            'inserted_permissions' => $result['permissions'],
            'roles_initialized' => $result['roles_updated'],
            'roles_repaired' => $result['roles_repaired'],
            'descriptions_updated' => $result['descriptions_updated'],
        ];
    }

    private function normalizeSlug(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '_', $value) ?? '';
        $value = trim($value, '_');

        return $value;
    }
}
