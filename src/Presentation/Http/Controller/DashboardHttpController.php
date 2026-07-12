<?php
declare(strict_types=1);

namespace App\Presentation\Http\Controller;

use App\Application\Analytics\DashboardDbService;
use App\Presentation\Http\Middleware\PermissionMiddleware;
use App\Shared\Audit\AuditLogger;
use App\Shared\Http\Request;
use App\Shared\Http\Response;
use Throwable;

final class DashboardHttpController
{
    private const PERMISSION_BY_ACTION = [
        'obtener_dashboard' => ['dashboard.view', 'reportes.view', 'ventas.view', 'rifas.view', 'transferencias.view', 'clientes.view'],
        'obtener_rifas' => ['dashboard.view', 'reportes.view', 'ventas.view', 'rifas.view'],
    ];

    public function __construct(
        private readonly DashboardDbService $service,
        private readonly PermissionMiddleware $permissions,
        private readonly AuditLogger $audit
    ) {
    }

    public function __invoke(Request $request): never
    {
        try {
            $action = trim((string)$request->input('action', ''));
            if ($action === 'obtener_dashboard') {
                $this->permissions->authorize($action, self::PERMISSION_BY_ACTION);
                $desde = trim((string)$request->input('fechaDesde', date('Y-m-01')));
                $hasta = trim((string)$request->input('fechaHasta', date('Y-m-d')));
                $rifaRaw = $request->input('id_raffle', '');
                $idRaffle = ($rifaRaw === '' || $rifaRaw === null) ? null : (int)$rifaRaw;
                $result = $this->service->obtenerDashboard($desde, $hasta, $idRaffle && $idRaffle > 0 ? $idRaffle : null);
                $this->audit->log('dashboard.obtener', ['success' => true]);
                Response::json($result);
            }
            if ($action === 'obtener_rifas') {
                $this->permissions->authorize($action, self::PERMISSION_BY_ACTION);
                $result = $this->service->listarRifas();
                Response::json($result);
            }
            Response::json(['success' => false, 'message' => 'Accion invalida'], 422);
        } catch (Throwable $e) {
            $this->audit->log('dashboard.error', ['error' => $e->getMessage()]);
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
