<?php
declare(strict_types=1);

namespace App\Presentation\Http\Controller;

use App\Application\Ticketing\TicketSalesService;
use App\Presentation\Http\Middleware\CsrfMiddleware;
use App\Presentation\Http\Middleware\PermissionMiddleware;
use App\Shared\Audit\AuditLogger;
use App\Shared\Http\Request;
use App\Shared\Http\Response;
use Throwable;

final class TicketSalesController
{
    private const ALLOWED_ACTIONS = [
        'obtener',
        'obtener_rifas',
        'crear_venta',
        'crear_venta_mixta',
        'obtener_por_codigo',
        'obtener_disponibles',
        'detalle_venta',
        'obtener_por_celular',
        'numeros_vendidos',
        'obtener_admins',
        'anular',
        'anular_parcial',
        'obtener_origenes',
    ];

    private const CSRF_PROTECTED_ACTIONS = ['anular', 'anular_parcial'];

    private const PUBLIC_ACTIONS = [
        'obtener_por_celular',
    ];

    private const PERMISSION_BY_ACTION = [
        'obtener' => ['ventas.view'],
        'obtener_rifas' => ['ventas.create', 'ventas.view', 'rifas.view'],
        'crear_venta' => ['ventas.create'],
        'crear_venta_mixta' => ['ventas.create'],
        'obtener_por_codigo' => ['ventas.view'],
        'obtener_disponibles' => ['ventas.create', 'rifas.view'],
        'detalle_venta' => ['ventas.view'],
        'numeros_vendidos' => ['ventas.view'],
        'obtener_admins' => ['ventas.view'],
        'anular' => ['ventas.cancel'],
        'anular_parcial' => ['ventas.cancel_partial', 'ventas.cancel'],
        'obtener_origenes' => ['ventas.view'],
    ];

    public function __construct(
        private readonly TicketSalesService $service,
        private readonly CsrfMiddleware $csrfMiddleware,
        private readonly PermissionMiddleware $permissions,
        private readonly AuditLogger $audit
    ) {
    }

    public function __invoke(Request $request): never
    {
        try {
            $action = trim((string)$request->input('action', ''));
            if (!in_array($action, self::ALLOWED_ACTIONS, true)) {
                Response::json(['success' => false, 'message' => 'Accion no valida'], 422);
            }

            if (!in_array($action, self::PUBLIC_ACTIONS, true)) {
                $this->permissions->authorize($action, self::PERMISSION_BY_ACTION);
            }
            $this->csrfMiddleware->handle($request, self::CSRF_PROTECTED_ACTIONS);
            $payload = $this->sanitizePayload($request->all());
            $result = $this->service->execute($action, $payload);
            $this->audit->log('ticket_sales.' . $action, ['success' => (bool)($result['success'] ?? false)]);
            Response::json($result);
        } catch (Throwable $exception) {
            $this->audit->log('ticket_sales.error', ['error' => $exception->getMessage()]);
            Response::json(['success' => false, 'message' => $exception->getMessage()], 500);
        }
    }

    private function sanitizePayload(array $payload): array
    {
        $clean = [];
        foreach ($payload as $key => $value) {
            if ($key === 'ticket_ids' && is_array($value)) {
                $clean['ticket_ids'] = array_map('intval', $value);
                continue;
            }
            if (is_array($value)) {
                continue;
            }

            $clean[(string)$key] = trim((string)$value);
        }

        return $clean;
    }
}
