<?php
declare(strict_types=1);

namespace App\Presentation\Http\Controller;

use App\Application\Auth\AdminUserService;
use App\Infrastructure\Repository\PdoAdminRepository;
use App\Infrastructure\Repository\PdoPermissionRepository;
use App\Presentation\Http\Middleware\CsrfMiddleware;
use App\Presentation\Http\Middleware\PermissionMiddleware;
use App\Shared\Audit\AuditLogger;
use App\Shared\Exception\DomainException;
use App\Shared\Http\Request;
use App\Shared\Http\Response;
use Throwable;

final class UserHttpController
{
    private const ACTIONS = [
        'me',
        'listar',
        'roles',
        'crear',
        'actualizar',
        'cambiar_mi_password',
    ];

    private const PERMS = [
        'me' => [],
        'listar' => ['usuarios.view'],
        'roles' => ['usuarios.view'],
        'crear' => ['usuarios.manage'],
        'actualizar' => ['usuarios.manage'],
        'cambiar_mi_password' => [],
    ];

    private const CSRF_ACTIONS = ['crear', 'actualizar', 'cambiar_mi_password'];

    public function __construct(
        private readonly PdoAdminRepository $admins,
        private readonly PdoPermissionRepository $permissions,
        private readonly AdminUserService $userService,
        private readonly PermissionMiddleware $permissionMiddleware,
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

            if ($action !== 'me' && $action !== 'cambiar_mi_password') {
                $this->permissionMiddleware->authorize($action, self::PERMS);
            } else {
                $this->assertAuthenticated();
            }

            $this->csrf->handle($request, self::CSRF_ACTIONS);

            $result = match ($action) {
                'me' => $this->me(),
                'listar' => ['success' => true, 'data' => $this->admins->findAll()],
                'roles' => ['success' => true, 'data' => $this->admins->findRoles()],
                'crear' => $this->crear($request),
                'actualizar' => $this->actualizar($request),
                'cambiar_mi_password' => $this->cambiarMiPassword($request),
                default => ['success' => false],
            };

            $this->audit->log('usuarios.' . $action, ['success' => (bool)($result['success'] ?? true)]);
            Response::json($result);
        } catch (Throwable $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    private function assertAuthenticated(): void
    {
        if ((int)($_SESSION['user_id'] ?? 0) <= 0) {
            Response::json(['success' => false, 'message' => 'No autenticado'], 401);
        }
    }

    private function me(): array
    {
        $adminId = (int)($_SESSION['user_id'] ?? 0);
        $user = $this->admins->findById($adminId);
        if ($user === null) {
            throw new DomainException('Usuario no encontrado');
        }

        unset($user['password_admin']);

        $perms = $this->permissions->getAdminPermissions($adminId);
        $role = strtolower((string)($_SESSION['user_role'] ?? ''));

        return [
            'success' => true,
            'data' => [
                'user' => [
                    'id_admin' => $adminId,
                    'email_admin' => $user['email_admin'] ?? $_SESSION['email_admin'] ?? '',
                    'id_role' => (int)($user['id_role'] ?? 0),
                    'rol_admin' => $user['rol_admin'] ?? $role,
                    'name_role' => $user['name_role'] ?? null,
                ],
                'permissions' => $perms,
                'can' => [
                    'usuarios_view' => $this->can('usuarios', 'view', $adminId, $perms, $role),
                    'usuarios_manage' => $this->can('usuarios', 'manage', $adminId, $perms, $role),
                    'roles_view' => $this->can('roles', 'view', $adminId, $perms, $role),
                    'roles_manage' => $this->can('roles', 'manage', $adminId, $perms, $role),
                ],
            ],
        ];
    }

    /**
     * @param list<string> $perms
     */
    private function can(string $module, string $action, int $adminId, array $perms, string $role): bool
    {
        if (in_array(strtolower($module . '.' . $action), $perms, true)) {
            return true;
        }

        if (str_contains($role, 'superadmin')) {
            return true;
        }
        if (str_contains($role, 'admin') && $action === 'view') {
            return true;
        }

        return false;
    }

    private function crear(Request $request): array
    {
        $email = trim((string)$request->input('email_admin', ''));
        $pass = trim((string)$request->input('password_admin', ''));
        $roleId = (int)$request->input('id_role', 0);

        if ($email === '') {
            throw new DomainException('El email es obligatorio');
        }
        $this->userService->assertValidPassword($pass, true);

        if ($this->admins->emailExists($email)) {
            throw new DomainException('El email ya está registrado');
        }

        $slug = $this->resolveRoleSlug($roleId);
        $id = $this->admins->create($email, $pass, $roleId, $slug);

        return ['success' => true, 'id_admin' => $id, 'message' => 'Usuario creado'];
    }

    private function actualizar(Request $request): array
    {
        $id = (int)$request->input('id_admin', 0);
        if ($id <= 0) {
            throw new DomainException('ID inválido');
        }

        $actorId = (int)($_SESSION['user_id'] ?? 0);
        $email = trim((string)$request->input('email_admin', ''));
        $pass = trim((string)$request->input('password_admin', ''));
        $roleId = $request->input('id_role') !== null && $request->input('id_role') !== ''
            ? (int)$request->input('id_role')
            : null;
        $status = $request->input('status_admin') !== null && $request->input('status_admin') !== ''
            ? (int)$request->input('status_admin')
            : null;

        $this->userService->assertCanModifyUser($actorId, $id, $roleId, $status);

        if ($email !== '' && $this->admins->emailExists($email, $id)) {
            throw new DomainException('El email ya está en uso');
        }
        if ($pass !== '') {
            $this->userService->assertValidPassword($pass, true);
        }

        $data = array_filter([
            'email_admin' => $email !== '' ? $email : null,
            'password_admin' => $pass !== '' ? $pass : null,
            'id_role' => $roleId,
            'status_admin' => $status,
        ], static fn ($v) => $v !== null);

        if (isset($data['id_role'])) {
            $data['rol_admin'] = $this->resolveRoleSlug((int)$data['id_role']);
        }

        $this->admins->update($id, $data);
        $this->permissions->clearCache($id);

        if ($pass !== '' && $id === $actorId) {
            $_SESSION['token_admin'] = $this->admins->rotateSessionToken($id);
        }

        return ['success' => true, 'message' => 'Usuario actualizado'];
    }

    private function cambiarMiPassword(Request $request): array
    {
        $adminId = (int)($_SESSION['user_id'] ?? 0);
        $current = trim((string)$request->input('current_password', ''));
        $newPass = trim((string)$request->input('new_password', ''));
        $confirm = trim((string)$request->input('confirm_password', ''));

        if ($current === '' || $newPass === '') {
            throw new DomainException('Completa la contraseña actual y la nueva');
        }
        if ($newPass !== $confirm) {
            throw new DomainException('La confirmación no coincide');
        }

        $this->userService->assertCanChangePassword($adminId, $adminId, $current);
        $this->userService->assertValidPassword($newPass, true);

        $this->admins->update($adminId, ['password_admin' => $newPass]);
        $_SESSION['token_admin'] = $this->admins->rotateSessionToken($adminId);

        return ['success' => true, 'message' => 'Contraseña actualizada'];
    }

    private function resolveRoleSlug(int $roleId): string
    {
        foreach ($this->admins->findRoles() as $r) {
            if ((int)$r['id_role'] === $roleId) {
                return (string)$r['slug_role'];
            }
        }

        return 'vendedor';
    }
}
