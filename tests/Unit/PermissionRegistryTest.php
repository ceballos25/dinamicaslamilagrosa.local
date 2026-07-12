<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Application\Auth\PermissionRegistry;
use PHPUnit\Framework\TestCase;

final class PermissionRegistryTest extends TestCase
{
    public function testCatalogIncludesUserAndRolePermissions(): void
    {
        $keys = array_map(static fn (array $item): string => $item['key'], PermissionRegistry::all());

        self::assertContains('usuarios.manage', $keys);
        self::assertContains('roles.manage', $keys);
        self::assertContains('transferencias.approve', $keys);
    }

    public function testDefaultSuperadminGetsAllPermissions(): void
    {
        $defaults = PermissionRegistry::defaultRolePermissions();

        self::assertSame('*', $defaults['superadmin']);
        self::assertNotContains('usuarios.manage', $defaults['admin']);
    }

    public function testAuditorIsReadOnly(): void
    {
        $auditor = PermissionRegistry::defaultRolePermissions()['auditor'];

        self::assertContains('auditoria.view', $auditor);
        self::assertContains('ventas.view', $auditor);
        self::assertNotContains('ventas.create', $auditor);
        self::assertNotContains('ventas.cancel', $auditor);
        self::assertNotContains('ventas.cancel_partial', $auditor);
    }

    public function testSystemRolesExcludeOperador(): void
    {
        self::assertNotContains('operador', PermissionRegistry::SYSTEM_ROLE_SLUGS);
        self::assertContains('auditor', PermissionRegistry::SYSTEM_ROLE_SLUGS);
    }

    public function testCatalogMatchesProductionPermissionKeys(): void
    {
        $keys = array_map(static fn (array $item): string => $item['key'], PermissionRegistry::all());

        self::assertContains('dashboard.view', $keys);
        self::assertContains('ventas.cancel_partial', $keys);
        self::assertContains('roles.view', $keys);
        self::assertContains('settings.manage', $keys);
    }
}
