<?php
declare(strict_types=1);

namespace App\Presentation\Http\Controller;

use App\Application\Audit\AuditActionLabels;
use App\Infrastructure\Repository\PdoAuditQueryRepository;
use App\Presentation\Http\Middleware\PermissionMiddleware;
use App\Shared\Http\Request;
use App\Shared\Http\Response;
use Throwable;

final class AuditHttpController
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

            $this->permissions->authorize($action, ['listar' => ['auditoria.view']]);

            $page = max(1, (int)$request->input('page', 1));
            $limit = min(200, max(1, (int)$request->input('limit', 50)));
            $scope = trim((string)$request->input('scope', ''));
            $filters = [
                'action' => trim((string)$request->input('search_action', '')),
                'entity_type' => trim((string)$request->input('entity_type', '')),
                'admin_id' => (int)$request->input('admin_id', 0) ?: null,
                'date_from' => trim((string)$request->input('date_from', '')),
                'date_to' => trim((string)$request->input('date_to', '')),
                'scope' => $scope === 'meta' ? 'meta' : '',
            ];

            $result = $this->audit->search($filters, $page, $limit);
            $rows = [];
            foreach ($result['data'] as $row) {
                $actionKey = (string)($row['action_audit'] ?? '');
                $row['action_label'] = AuditActionLabels::label($actionKey);
                $row['is_meta'] = AuditActionLabels::isMetaTraffic($actionKey);
                $rows[] = $row;
            }

            $funnelFilters = $filters;
            $funnelFilters['scope'] = 'meta';
            $funnelFilters['action'] = '';
            $counts = $this->audit->countByAction($funnelFilters);
            $funnel = [];
            foreach (AuditActionLabels::META_FUNNEL as $item) {
                $funnel[] = [
                    'action' => $item['action'],
                    'label' => $item['label'],
                    'hint' => $item['hint'],
                    'total' => (int)($counts[$item['action']] ?? 0),
                ];
            }

            Response::json([
                'success' => true,
                'data' => $rows,
                'total' => $result['total'],
                'funnel' => $funnel,
            ]);
        } catch (Throwable $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
