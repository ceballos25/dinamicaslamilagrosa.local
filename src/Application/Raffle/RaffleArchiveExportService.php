<?php
declare(strict_types=1);

namespace App\Application\Raffle;

use App\Application\Export\CsvBuilder;
use App\Application\Export\TransferenciasWorkbookExporter;
use App\Infrastructure\Database\PdoFactory;
use App\Shared\Exception\DomainException;
use PDO;
use ZipArchive;

final class RaffleArchiveExportService
{
    private const MAX_COMPROBANTES = 5000;

    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? PdoFactory::get();
    }

    /**
     * @return array{
     *   path: string,
     *   filename: string,
     *   stats: array<string, int>
     * }
     */
    public function buildZip(int $raffleId): array
    {
        if (!class_exists(ZipArchive::class)) {
            throw new DomainException('La extensión ZIP no está disponible en el servidor', 'ZIP_UNAVAILABLE');
        }

        $raffle = $this->fetchRaffle($raffleId);
        if ($raffle === null) {
            throw new DomainException('Rifa no encontrada', 'RAFFLE_NOT_FOUND');
        }

        $sales = $this->fetchSales($raffleId);
        $transfers = $this->fetchTransfers($raffleId);
        $numbers = $this->fetchSoldNumbers($raffleId);

        $workDir = $this->createWorkDir();
        $comprobantesDir = $workDir . '/comprobantes';
        mkdir($comprobantesDir, 0700, true);

        $comprobanteStats = $this->copyComprobantes($transfers, $comprobantesDir);

        file_put_contents($workDir . '/ventas.csv', $this->buildSalesCsv($sales));
        (new TransferenciasWorkbookExporter())->write(
            $workDir . '/transferencias.xlsx',
            $transfers,
            $comprobanteStats['paths']
        );
        file_put_contents($workDir . '/numeros_vendidos.csv', $this->buildNumbersCsv($numbers));

        $zipFilename = $this->buildZipFilename($raffle);
        $zipPath = sys_get_temp_dir() . '/' . $zipFilename;

        if (is_file($zipPath)) {
            @unlink($zipPath);
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $this->removeDir($workDir);
            throw new DomainException('No se pudo crear el archivo ZIP', 'ZIP_CREATE_FAILED');
        }

        $this->addDirToZip($zip, $workDir, '');
        $zip->close();
        $this->removeDir($workDir);

        if (!is_file($zipPath)) {
            throw new DomainException('No se generó el archivo de respaldo', 'ZIP_MISSING');
        }

        return [
            'path' => $zipPath,
            'filename' => $zipFilename,
            'stats' => [
                'ventas' => count($sales),
                'transferencias' => count($transfers),
                'numeros' => count($numbers),
                'comprobantes' => $comprobanteStats['copied'],
                'comprobantes_omitidos' => $comprobanteStats['missing'],
            ],
        ];
    }

    /** @return array<string, mixed>|null */
    private function fetchRaffle(int $raffleId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id_raffle, title_raffle, description_raffle, price_raffle, digits_raffle,
                    date_raffle, status_raffle, type_raffle
             FROM raffles WHERE id_raffle = :id LIMIT 1'
        );
        $stmt->execute([':id' => $raffleId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    /** @return list<array<string, mixed>> */
    private function fetchSales(int $raffleId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT s.id_sale, s.code_sale, s.quantity_sale, s.total_sale, s.payment_method_sale,
                    s.status_sale, s.date_created_sale, s.source_sale, s.id_admin_sale,
                    c.name_customer, c.lastname_customer, c.phone_customer, c.email_customer,
                    COALESCE(a.email_admin, \'Sistema\') AS admin_email
             FROM sales s
             INNER JOIN customers c ON c.id_customer = s.id_customer_sale
             LEFT JOIN admins a ON a.id_admin = s.id_admin_sale
             WHERE s.id_raffle_sale = :r
             ORDER BY s.id_sale ASC'
        );
        $stmt->execute([':r' => $raffleId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<array<string, mixed>> */
    private function fetchTransfers(int $raffleId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT t.id_transfer, t.code_transfer, t.quantity_transfer, t.amount_transfer,
                    t.status_transfer, t.date_created_transfer, t.source_transfer, t.url_transfer,
                    t.proof_reference, t.proof_reference_norm, t.proof_bank,
                    c.name_customer, c.lastname_customer, c.phone_customer, c.email_customer
             FROM transfers t
             INNER JOIN customers c ON c.id_customer = t.id_customer_transfer
             WHERE t.id_raffle_transfer = :r
             ORDER BY t.id_transfer ASC'
        );
        $stmt->execute([':r' => $raffleId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map(fn (array $row): array => $this->enrichTransferRow($row), $rows);
    }

    /** @param array<string, mixed> $row */
    private function enrichTransferRow(array $row): array
    {
        $code = (string)($row['code_transfer'] ?? '');
        $status = (int)($row['status_transfer'] ?? 0);
        $row['sale_code'] = '';
        $row['sale_status'] = '';
        $row['numbers_label'] = '';

        if ($code === '' || !in_array($status, [2, 3], true)) {
            return $row;
        }

        $saleStmt = $this->pdo->prepare(
            'SELECT id_sale, status_sale FROM sales WHERE code_sale = :c ORDER BY id_sale DESC LIMIT 1'
        );
        $saleStmt->execute([':c' => $code]);
        $sale = $saleStmt->fetch(PDO::FETCH_ASSOC);
        if ($sale === false) {
            return $row;
        }

        $row['sale_code'] = $code;
        $row['sale_status'] = (int)$sale['status_sale'] === 1 ? 'Activa' : 'Anulada';

        $itemsStmt = $this->pdo->prepare(
            'SELECT number_ticket FROM sale_items WHERE id_sale = :s ORDER BY number_ticket ASC'
        );
        $itemsStmt->execute([':s' => (int)$sale['id_sale']]);
        $numbers = array_map(
            static fn (array $item): string => (string)$item['number_ticket'],
            $itemsStmt->fetchAll(PDO::FETCH_ASSOC) ?: []
        );
        $row['numbers_label'] = $numbers !== [] ? implode(', ', $numbers) : '';

        return $row;
    }

    /** @return list<array<string, mixed>> */
    private function fetchSoldNumbers(int $raffleId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT si.number_ticket, si.status_item, si.unit_price,
                    s.code_sale, s.date_created_sale, s.payment_method_sale, s.total_sale, s.status_sale,
                    c.name_customer, c.lastname_customer, c.phone_customer
             FROM sale_items si
             INNER JOIN sales s ON s.id_sale = si.id_sale
             INNER JOIN customers c ON c.id_customer = s.id_customer_sale
             WHERE s.id_raffle_sale = :r
             ORDER BY CAST(si.number_ticket AS UNSIGNED), si.number_ticket ASC'
        );
        $stmt->execute([':r' => $raffleId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @param list<array<string, mixed>> $transfers
     * @return array{copied: int, missing: int, paths: array<int, string>}
     */
    private function copyComprobantes(array $transfers, string $destDir): array
    {
        if (!class_exists(\TransfersController::class, false)) {
            require_once dirname(__DIR__, 3) . '/controllers/transfersController.php';
        }

        $copied = 0;
        $missing = 0;
        $paths = [];

        foreach ($transfers as $transfer) {
            if ($copied >= self::MAX_COMPROBANTES) {
                break;
            }

            $id = (int)($transfer['id_transfer'] ?? 0);
            $url = trim((string)($transfer['url_transfer'] ?? ''));
            if ($id <= 0 || $url === '') {
                continue;
            }

            $source = \TransfersController::resolveComprobanteFilesystemPath($url);
            if ($source === null || !is_readable($source)) {
                ++$missing;
                continue;
            }

            $zipName = $this->comprobanteZipName($transfer, $source);
            $target = $destDir . '/' . $zipName;
            if (@copy($source, $target)) {
                ++$copied;
                $paths[$id] = $target;
            } else {
                ++$missing;
            }
        }

        return ['copied' => $copied, 'missing' => $missing, 'paths' => $paths];
    }

    /** @param array<string, mixed> $transfer */
    private function comprobanteZipName(array $transfer, string $sourcePath): string
    {
        $code = preg_replace('/[^A-Za-z0-9_-]/', '', (string)($transfer['code_transfer'] ?? '')) ?: 'trf';
        $ref = preg_replace(
            '/[^A-Za-z0-9_-]/',
            '',
            (string)($transfer['proof_reference_norm'] ?? $transfer['proof_reference'] ?? '')
        );
        $suffix = $ref !== '' ? $ref : ('id' . (int)($transfer['id_transfer'] ?? 0));
        $ext = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION) ?: 'jpg');
        $ext = preg_replace('/[^a-z0-9]/', '', $ext) ?: 'jpg';

        return $code . '_' . $suffix . '.' . $ext;
    }

    /** @param list<array<string, mixed>> $sales */
    private function buildSalesCsv(array $sales): string
    {
        $builder = new CsvBuilder([
            'codigo_venta',
            'fecha',
            'cantidad',
            'total',
            'metodo_pago',
            'estado_venta',
            'origen',
            'cliente_nombre',
            'cliente_apellido',
            'celular',
            'email',
            'admin',
            'es_compra_web',
        ]);

        foreach ($sales as $sale) {
            $code = (string)($sale['code_sale'] ?? '');
            $builder->addRow([
                $code,
                (string)($sale['date_created_sale'] ?? ''),
                (int)($sale['quantity_sale'] ?? 0),
                (float)($sale['total_sale'] ?? 0),
                (string)($sale['payment_method_sale'] ?? ''),
                (int)($sale['status_sale'] ?? 0) === 1 ? 'Activa' : 'Anulada',
                $this->sourceLabel($sale['source_sale'] ?? null),
                (string)($sale['name_customer'] ?? ''),
                (string)($sale['lastname_customer'] ?? ''),
                (string)($sale['phone_customer'] ?? ''),
                (string)($sale['email_customer'] ?? ''),
                (string)($sale['admin_email'] ?? ''),
                strcasecmp((string)($sale['payment_method_sale'] ?? ''), 'Transferencia') === 0 ? 'Si' : 'No',
            ]);
        }

        return $builder->toString();
    }

    /** @param list<array<string, mixed>> $numbers */
    private function buildNumbersCsv(array $numbers): string
    {
        $builder = new CsvBuilder([
            'numero',
            'estado_numero',
            'codigo_venta',
            'fecha_venta',
            'metodo_pago',
            'total_venta',
            'precio_unitario',
            'estado_venta',
            'cliente_nombre',
            'cliente_apellido',
            'celular',
        ]);

        foreach ($numbers as $row) {
            $itemStatus = (string)($row['status_item'] ?? 'active');
            $builder->addRow([
                (string)($row['number_ticket'] ?? ''),
                $itemStatus === 'active' ? 'Vendido' : 'Anulado',
                (string)($row['code_sale'] ?? ''),
                (string)($row['date_created_sale'] ?? ''),
                (string)($row['payment_method_sale'] ?? ''),
                (float)($row['total_sale'] ?? 0),
                (float)($row['unit_price'] ?? 0),
                (int)($row['status_sale'] ?? 0) === 1 ? 'Activa' : 'Anulada',
                (string)($row['name_customer'] ?? ''),
                (string)($row['lastname_customer'] ?? ''),
                (string)($row['phone_customer'] ?? ''),
            ]);
        }

        return $builder->toString();
    }

    /** @param array<string, mixed> $raffle */
    private function buildZipFilename(array $raffle): string
    {
        $slug = $this->slugify((string)($raffle['title_raffle'] ?? 'evento'));

        return 'Respaldo_' . $slug . '_' . date('Y-m-d') . '.zip';
    }

    private function slugify(string $value): string
    {
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        $base = is_string($ascii) && $ascii !== '' ? $ascii : $value;
        $slug = preg_replace('/[^a-zA-Z0-9]+/', '_', $base) ?? 'evento';
        $slug = trim((string)$slug, '_');

        return $slug !== '' ? substr($slug, 0, 48) : 'evento';
    }

    private function sourceLabel(mixed $source): string
    {
        $value = trim((string)($source ?? ''));
        if ($value === '' || strcasecmp($value, 'null') === 0) {
            return 'Sin origen';
        }

        return $value;
    }

    private function createWorkDir(): string
    {
        $dir = sys_get_temp_dir() . '/raffle-archive-' . bin2hex(random_bytes(8));
        if (!@mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new DomainException('No se pudo preparar la carpeta temporal', 'TEMP_DIR_FAILED');
        }

        return $dir;
    }

    private function addDirToZip(ZipArchive $zip, string $dir, string $prefix): void
    {
        $items = scandir($dir);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            $zipPath = $prefix === '' ? $item : $prefix . '/' . $item;
            if (is_dir($path)) {
                $zip->addEmptyDir($zipPath);
                $this->addDirToZip($zip, $path, $zipPath);
                continue;
            }
            $zip->addFile($path, $zipPath);
        }
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = scandir($dir);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->removeDir($path);
                continue;
            }
            @unlink($path);
        }

        @rmdir($dir);
    }
}
