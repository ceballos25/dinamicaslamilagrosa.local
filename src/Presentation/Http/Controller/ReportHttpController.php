<?php
declare(strict_types=1);

namespace App\Presentation\Http\Controller;

use App\Application\Reporting\ReportColumnMeta;
use App\Application\Reporting\ReportDisplayFormatter;
use App\Application\Reporting\ReportExportService;
use App\Application\Reporting\ReportSchemaRegistry;
use App\Application\Reporting\SafeReportExecutor;
use App\Application\Reporting\SavedReportRepository;
use App\Presentation\Http\Middleware\PermissionMiddleware;
use App\Shared\Audit\AuditLogger;
use App\Shared\Http\Request;
use App\Shared\Http\Response;
use Throwable;

final class ReportHttpController
{
    private const PERMISSION_BY_ACTION = [
        'schema' => ['reportes.view'],
        'run' => ['reportes.view'],
        'presets' => ['reportes.view'],
        'saved_list' => ['reportes.view'],
        'saved_get' => ['reportes.view'],
        'saved_save' => ['reportes.view'],
        'saved_delete' => ['reportes.view'],
        'export_excel' => ['reportes.export'],
        'export_pdf' => ['reportes.export'],
    ];

    public function __construct(
        private readonly ReportSchemaRegistry $registry,
        private readonly SafeReportExecutor $executor,
        private readonly SavedReportRepository $saved,
        private readonly PermissionMiddleware $permissions,
        private readonly AuditLogger $audit,
        private readonly ReportExportService $exporter
    ) {
    }

    public function __invoke(Request $request): never
    {
        try {
            $action = trim((string)$request->input('action', ''));
            if (!isset(self::PERMISSION_BY_ACTION[$action])) {
                Response::json(['success' => false, 'message' => 'Accion invalida'], 422);
            }
            $this->permissions->authorize($action, self::PERMISSION_BY_ACTION);

            match ($action) {
                'schema' => $this->schema(),
                'presets' => $this->presets(),
                'run' => $this->run($request),
                'saved_list' => $this->savedList(),
                'saved_get' => $this->savedGet($request),
                'saved_save' => $this->savedSave($request),
                'saved_delete' => $this->savedDelete($request),
                'export_excel' => $this->exportExcel($request),
                'export_pdf' => $this->exportPdf($request),
                default => Response::json(['success' => false, 'message' => 'Accion invalida'], 422),
            };
        } catch (Throwable $e) {
            $this->audit->log('reports.error', ['error' => $e->getMessage()]);
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    private function schema(): never
    {
        $out = [];
        foreach ($this->registry->datasets() as $key => $meta) {
            $out[] = [
                'key' => $key,
                'label' => $meta['label'],
                'fields' => $this->registry->fieldDescriptors($key),
                'date_column' => $meta['date_column'],
            ];
        }
        Response::json(['success' => true, 'datasets' => $out, 'aggregates' => $this->registry->allowedAggregates()]);
    }

    private function presets(): never
    {
        $today = date('Y-m-d');
        $monthStart = date('Y-m-01');
        Response::json([
            'success' => true,
            'presets' => [
                [
                    'name' => 'Ventas por día y medio de pago (recaudo)',
                    'spec' => [
                        'dataset' => 'sales_detail',
                        'date_from' => $monthStart,
                        'date_to' => $today,
                        'dimensions' => [
                            ['field' => 'date_sale_day', 'alias' => 'dia'],
                            ['field' => 'payment_method_sale', 'alias' => 'medio_pago'],
                        ],
                        'measures' => [
                            ['fn' => 'SUM', 'field' => 'total_sale', 'alias' => 'total_recaudado'],
                            ['fn' => 'COUNT', 'field' => '*', 'alias' => 'num_ventas'],
                            ['fn' => 'SUM', 'field' => 'quantity_sale', 'alias' => 'tickets_vendidos'],
                        ],
                        'filters' => [
                            ['field' => 'status_sale', 'op' => 'eq', 'value' => '1'],
                        ],
                        'order_by' => 'total_recaudado',
                        'order_dir' => 'DESC',
                        'limit' => 5000,
                    ],
                ],
                [
                    'name' => 'Ventas por origen (recaudo)',
                    'spec' => [
                        'dataset' => 'sales_detail',
                        'date_from' => $monthStart,
                        'date_to' => $today,
                        'dimensions' => [
                            ['field' => 'source_sale', 'alias' => 'origen'],
                        ],
                        'measures' => [
                            ['fn' => 'SUM', 'field' => 'total_sale', 'alias' => 'total_recaudado'],
                            ['fn' => 'COUNT', 'field' => '*', 'alias' => 'num_ventas'],
                            ['fn' => 'SUM', 'field' => 'quantity_sale', 'alias' => 'tickets_vendidos'],
                        ],
                        'filters' => [
                            ['field' => 'status_sale', 'op' => 'eq', 'value' => '1'],
                        ],
                        'order_by' => 'total_recaudado',
                        'order_dir' => 'DESC',
                        'limit' => 500,
                    ],
                ],
            ],
        ]);
    }

    private function run(Request $request): never
    {
        $result = $this->executeSpec($request);
        $meta = ReportColumnMeta::fromSpec($result['spec']);
        $this->audit->log('reports.run', ['dataset' => $result['spec']['dataset'] ?? '']);
        Response::json([
            'success' => true,
            'columns' => $result['data']['columns'],
            'rows' => $result['data']['rows'],
            'column_labels' => $meta['labels'],
            'column_formats' => $meta['formats'],
        ]);
    }

    private function exportExcel(Request $request): never
    {
        $result = $this->executeSpec($request);
        $meta = ReportColumnMeta::fromSpec($result['spec']);
        $columns = $result['data']['columns'];
        $rows = $result['data']['rows'];
        $title = trim((string)$request->input('title', 'Reporte'));
        $filename = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $title) ?: 'reporte';
        $xml = $this->exporter->toSpreadsheetXml(
            $columns,
            $rows,
            $title,
            $meta['labels'],
            $meta['formats']
        );

        $this->audit->log('reports.export_excel', ['rows' => count($rows)]);
        header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
        header('Cache-Control: no-store');
        echo "\xEF\xBB\xBF" . $xml;
        exit;
    }

    private function exportPdf(Request $request): never
    {
        $result = $this->executeSpec($request);
        $meta = ReportColumnMeta::fromSpec($result['spec']);
        $columns = $result['data']['columns'];
        $rows = $result['data']['rows'];
        $title = trim((string)$request->input('title', 'Reporte'));
        $filename = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $title) ?: 'reporte';
        $html = $this->exporter->toPdfHtml(
            $columns,
            $rows,
            $title,
            $meta['labels'],
            $meta['formats']
        );
        $pdf = $this->exporter->renderPdf($html);

        $this->audit->log('reports.export_pdf', ['rows' => count($rows)]);
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '.pdf"');
        header('Cache-Control: no-store');
        echo $pdf;
        exit;
    }

    /**
     * @return array{spec: array<string, mixed>, data: array{columns: list<string>, rows: list<array<string, mixed>>}}
     */
    private function executeSpec(Request $request): array
    {
        $raw = trim((string)$request->input('spec', ''));
        if ($raw === '') {
            Response::json(['success' => false, 'message' => 'Spec JSON requerido'], 422);
        }
        $spec = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($spec)) {
            Response::json(['success' => false, 'message' => 'Spec invalido'], 422);
        }

        return ['spec' => $spec, 'data' => $this->executor->run($spec)];
    }

    private function savedList(): never
    {
        Response::json(['success' => true, 'data' => $this->saved->listAll()]);
    }

    private function savedGet(Request $request): never
    {
        $id = (int)$request->input('id', 0);
        if ($id <= 0) {
            Response::json(['success' => false, 'message' => 'ID requerido'], 422);
        }
        $row = $this->saved->get($id);
        if ($row === null) {
            Response::json(['success' => false, 'message' => 'No encontrado'], 404);
        }
        $spec = json_decode((string)$row['spec_report'], true);
        if (!is_array($spec)) {
            Response::json(['success' => false, 'message' => 'Reporte corrupto en base de datos'], 500);
        }
        Response::json(['success' => true, 'name' => $row['name_report'], 'spec' => $spec]);
    }

    private function savedSave(Request $request): never
    {
        $name = trim((string)$request->input('name', ''));
        $specRaw = (string)$request->input('spec', '');
        $id = (int)$request->input('id', 0);
        if ($name === '' || $specRaw === '') {
            Response::json(['success' => false, 'message' => 'Nombre y spec requeridos'], 422);
        }
        json_decode($specRaw, true, 512, JSON_THROW_ON_ERROR);
        $adminId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

        if ($id > 0) {
            $updated = $this->saved->update($id, $name, $specRaw, $adminId);
            if (!$updated) {
                Response::json(['success' => false, 'message' => 'No se pudo actualizar el reporte'], 404);
            }
            $this->audit->log('reports.saved', ['id' => $id, 'updated' => true]);
            Response::json(['success' => true, 'id_saved_report' => $id, 'updated' => true]);
        }

        $newId = $this->saved->save($name, $specRaw, $adminId);
        $this->audit->log('reports.saved', ['id' => $newId]);
        Response::json(['success' => true, 'id_saved_report' => $newId, 'updated' => false]);
    }

    private function savedDelete(Request $request): never
    {
        $id = (int)$request->input('id', 0);
        if ($id <= 0) {
            Response::json(['success' => false, 'message' => 'ID requerido'], 422);
        }
        $ok = $this->saved->delete($id);
        Response::json(['success' => $ok]);
    }
}
