<?php
declare(strict_types=1);

namespace App\Application\Auth;

final class PermissionRegistry
{
    /** @var array<string, array{label: string, actions: array<string, string>}> */
    private const MODULES = [
        'dashboard' => [
            'label' => 'Dashboard',
            'actions' => [
                'view' => 'Ver dashboard',
            ],
        ],
        'usuarios' => [
            'label' => 'Usuarios',
            'actions' => [
                'view' => 'Ver usuarios',
                'manage' => 'Gestionar usuarios',
            ],
        ],
        'roles' => [
            'label' => 'Roles y permisos',
            'actions' => [
                'view' => 'Ver roles',
                'manage' => 'Gestionar roles y permisos',
            ],
        ],
        'rifas' => [
            'label' => 'Rifas',
            'actions' => [
                'view' => 'Ver rifas',
                'create' => 'Crear rifas',
                'edit' => 'Editar rifas',
                'delete' => 'Eliminar rifas',
                'pause' => 'Pausar/reanudar rifas',
            ],
        ],
        'transferencias' => [
            'label' => 'Transferencias',
            'actions' => [
                'view' => 'Ver transferencias',
                'approve' => 'Aprobar transferencias',
            ],
        ],
        'clientes' => [
            'label' => 'Clientes',
            'actions' => [
                'view' => 'Ver clientes',
                'edit' => 'Editar clientes',
            ],
        ],
        'reportes' => [
            'label' => 'Reportes',
            'actions' => [
                'view' => 'Ver reportes',
                'export' => 'Exportar reportes',
            ],
        ],
        'configuracion' => [
            'label' => 'Configuración',
            'actions' => [
                'view' => 'Ver configuración',
                'manage' => 'Gestionar configuración',
            ],
        ],
        'auditoria' => [
            'label' => 'Auditoría',
            'actions' => [
                'view' => 'Ver auditoría',
            ],
        ],
        'ventas' => [
            'label' => 'Ventas',
            'actions' => [
                'view' => 'Ver ventas',
                'create' => 'Crear ventas',
                'cancel' => 'Anular ventas',
                'cancel_partial' => 'Anulación parcial',
            ],
        ],
        'settings' => [
            'label' => 'Ajustes',
            'actions' => [
                'manage' => 'Gestionar ajustes del sistema',
            ],
        ],
    ];

    /** @var list<string> */
    public const SYSTEM_ROLE_SLUGS = ['superadmin', 'admin', 'vendedor', 'auditor'];

    /**
     * @return list<array{module: string, action: string, key: string, module_label: string, action_label: string}>
     */
    public static function all(): array
    {
        $items = [];
        foreach (self::MODULES as $module => $meta) {
            foreach ($meta['actions'] as $action => $actionLabel) {
                $items[] = [
                    'module' => $module,
                    'action' => $action,
                    'key' => $module . '.' . $action,
                    'module_label' => $meta['label'],
                    'action_label' => $actionLabel,
                ];
            }
        }

        return $items;
    }

    /**
     * @return array<string, array{label: string, actions: array<string, string>}>
     */
    public static function grouped(): array
    {
        return self::MODULES;
    }

    public static function isValidKey(string $key): bool
    {
        foreach (self::all() as $item) {
            if ($item['key'] === strtolower($key)) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, list<string>|string> */
    public static function defaultRolePermissions(): array
    {
        $allKeys = array_map(static fn (array $item): string => $item['key'], self::all());

        $adminKeys = array_values(array_filter(
            $allKeys,
            static fn (string $key): bool => !in_array($key, ['usuarios.manage', 'roles.manage'], true)
        ));

        $vendedorKeys = [
            'dashboard.view',
            'rifas.view',
            'clientes.view',
            'clientes.edit',
            'transferencias.view',
            'ventas.view',
            'ventas.create',
            'reportes.view',
        ];

        $auditorKeys = [
            'dashboard.view',
            'auditoria.view',
            'ventas.view',
            'transferencias.view',
            'clientes.view',
            'rifas.view',
            'reportes.view',
        ];

        return [
            'superadmin' => '*',
            'admin' => $adminKeys,
            'vendedor' => $vendedorKeys,
            'auditor' => $auditorKeys,
        ];
    }
}
