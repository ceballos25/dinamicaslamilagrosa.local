<?php
declare(strict_types=1);

namespace App\Application\Auth;

use App\Infrastructure\Repository\PdoAdminRepository;
use App\Shared\Exception\DomainException;

final class AdminUserService
{
    public const MIN_PASSWORD_LENGTH = 8;

    public function __construct(
        private readonly PdoAdminRepository $admins
    ) {
    }

    public function assertValidPassword(string $password, bool $required = true): void
    {
        $password = trim($password);
        if ($password === '') {
            if ($required) {
                throw new DomainException('La contraseña es obligatoria');
            }

            return;
        }

        if (strlen($password) < self::MIN_PASSWORD_LENGTH) {
            throw new DomainException('La contraseña debe tener al menos ' . self::MIN_PASSWORD_LENGTH . ' caracteres');
        }
    }

    public function assertCanModifyUser(int $actorId, int $targetId, ?int $newRoleId, ?int $newStatus): void
    {
        if ($actorId <= 0 || $targetId <= 0) {
            throw new DomainException('Sesión inválida');
        }

        if ($targetId === $actorId) {
            if ($newStatus !== null && $newStatus === 0) {
                throw new DomainException('No puedes desactivar tu propia cuenta');
            }
            if ($newRoleId !== null) {
                $current = $this->admins->findById($actorId);
                $currentRoleId = (int)($current['id_role'] ?? 0);
                if ($currentRoleId > 0 && $newRoleId !== $currentRoleId) {
                    throw new DomainException('No puedes cambiar tu propio rol. Pide a otro administrador que lo haga.');
                }
            }
        }

        if ($newRoleId !== null) {
            $this->assertSuperadminRemains($targetId, $newRoleId);
        }
    }

    public function assertSuperadminRemains(int $targetId, int $newRoleId): void
    {
        $target = $this->admins->findById($targetId);
        if ($target === null) {
            throw new DomainException('Usuario no encontrado');
        }

        $currentSlug = strtolower((string)($target['slug_role'] ?? $target['rol_admin'] ?? ''));
        if (!str_contains($currentSlug, 'superadmin')) {
            return;
        }

        $newRole = null;
        foreach ($this->admins->findRoles() as $role) {
            if ((int)$role['id_role'] === $newRoleId) {
                $newRole = $role;
                break;
            }
        }

        $newSlug = strtolower((string)($newRole['slug_role'] ?? ''));
        if (str_contains($newSlug, 'superadmin')) {
            return;
        }

        if ($this->admins->countActiveByRoleSlug('superadmin') <= 1) {
            throw new DomainException('Debe quedar al menos un superadministrador activo');
        }
    }

    public function assertCanChangePassword(int $actorId, int $targetId, string $currentPassword): void
    {
        if ($actorId !== $targetId) {
            return;
        }

        $user = $this->admins->findById($targetId);
        if ($user === null) {
            throw new DomainException('Usuario no encontrado');
        }

        $hash = (string)($user['password_admin'] ?? '');
        if ($hash === '' || !password_verify($currentPassword, $hash)) {
            throw new DomainException('La contraseña actual no es correcta');
        }
    }
}
