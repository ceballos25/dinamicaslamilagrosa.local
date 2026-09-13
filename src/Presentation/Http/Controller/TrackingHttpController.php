<?php
declare(strict_types=1);

namespace App\Presentation\Http\Controller;

use App\Application\Marketing\MetaTrafficAudit;
use App\Infrastructure\Repository\PdoAuditQueryRepository;
use App\Presentation\Http\Middleware\PermissionMiddleware;
use App\Shared\Http\Request;
use App\Shared\Http\Response;
use Throwable;

final class TrackingHttpController
{
    public function __construct(
        private readonly PdoAuditQueryRepository $audit,
        private readonly PermissionMiddleware $permissions
    ) {
    }

    public function __invoke(Request $request): never
    {
        try {
            $action = trim((string)$request->input('action', 'listar'));
            if ($action !== 'listar') {
                Response::json(['success' => false, 'message' => 'Acción no válida'], 422);
            }

            $this->permissions->authorize($action, ['listar' => ['dashboard.view', 'auditoria.view']]);

            $page = max(1, (int)$request->input('page', 1));
            $limit = min(100, max(1, (int)$request->input('limit', 25)));
            $event = trim((string)$request->input('event', ''));
            $landing = MetaTrafficAudit::LANDING_ACTIONS;
            $selected = in_array($event, $landing, true) ? [$event] : $landing;

            $filters = [
                'date_from' => trim((string)$request->input('date_from', '')),
                'date_to' => trim((string)$request->input('date_to', '')),
                'actions' => $selected,
            ];

            $result = $this->audit->search($filters, $page, $limit);
            $rows = [];
            foreach ($result['data'] as $row) {
                $actionKey = (string)($row['action_audit'] ?? '');
                $row['action_label'] = MetaTrafficAudit::label($actionKey);
                $rows[] = $row;
            }

            $countFilters = $filters;
            $countFilters['actions'] = $landing;
            $counts = $this->audit->countByAction($countFilters);
            $funnel = [];
            foreach (MetaTrafficAudit::FUNNEL as $item) {
                $funnel[] = [
                    'action' => $item['action'],
                    'label' => $item['label'],
                    'hint' => $item['hint'],
                    'icon' => $item['icon'],
                    'total' => (int)($counts[$item['action']] ?? 0),
                ];
            }

            $eventOptions = [];
            foreach ($landing as $actionKey) {
                $eventOptions[] = [
                    'action' => $actionKey,
                    'label' => MetaTrafficAudit::label($actionKey),
                ];
            }

            Response::json([
                'success' => true,
                'data' => $rows,
                'total' => $result['total'],
                'page' => $page,
                'limit' => $limit,
                'funnel' => $funnel,
                'events' => $eventOptions,
            ]);
        } catch (Throwable $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
