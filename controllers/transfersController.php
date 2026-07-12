<?php

require_once __DIR__ . '/clientes.controller.php';
require_once __DIR__ . '/ventas.controller.php';
require_once __DIR__ . '/mail.controller.php';
require_once __DIR__ . '/../bootstrap/container.php';

use App\Application\Ticket\TicketConflictApiResponse;
use App\Shared\Exception\DomainException;

class TransfersController
{
    public const TABLE = 'transfers';

    public static function crearTransferencia(array $data)
    {
        if (
            !isset($data['id_raffle']) || trim((string)$data['id_raffle']) === ''
            || !isset($data['quantity']) || trim((string)$data['quantity']) === ''
        ) {
            return ['success' => false, 'message' => 'Datos incompletos'];
        }

        $phoneCheck = ClientesController::telefonoPermiteCompra((string)($data['phone_customer'] ?? ''));
        if (!$phoneCheck['allowed']) {
            return ['success' => false, 'message' => $phoneCheck['message']];
        }

        $cantidad = (int)$data['quantity'];
        $idRaffle = (int)$data['id_raffle'];

        $minError = VentasController::validarCantidadMinimaRifa($idRaffle, $cantidad);
        if ($minError !== null) {
            return $minError;
        }

        $countRow = Db::fetchOne(
            'SELECT COUNT(*) AS c FROM tickets WHERE id_raffle_ticket = :r AND status_ticket = 0',
            [':r' => (int)$data['id_raffle']]
        );
        $disponibles = (int)($countRow->c ?? 0);

        if ($disponibles < $cantidad) {
            return [
                'success' => false,
                'message' => $disponibles < 1 ? 'No hay nros disponibles' : 'No hay suficientes nros disponibles',
            ];
        }

        $calc = VentasController::calcularTotalPorPrecioRifa((int)$data['id_raffle'], $cantidad);
        if (!$calc['success']) {
            return $calc;
        }
        $montoEsperado = $calc['total'];
        if (isset($data['amount']) && trim((string)$data['amount']) !== '') {
            $montoCliente = (float)$data['amount'];
            if (!VentasController::montosEquivalentesCOP($montoCliente, $montoEsperado)) {
                return [
                    'success' => false,
                    'message' => 'El monto no coincide con el precio vigente. Recarga la página e intenta de nuevo.',
                ];
            }
        }

        $code = str_pad((string)random_int(0, 9999999999), 10, '0', STR_PAD_LEFT);

        try {
            $idCustomer = ClientesController::obtenerOCrearCliente([
                'name_customer' => $data['name_customer'],
                'lastname_customer' => $data['lastname_customer'],
                'phone_customer' => $data['phone_customer'],
                'email_customer' => $data['email_customer'],
            ]);
        } catch (Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }

        if (!$idCustomer) {
            return ['success' => false, 'message' => 'Error con cliente'];
        }

        $ticketIdsInput = null;
        if (!empty($data['ticket_ids']) && is_array($data['ticket_ids'])) {
            $ticketIdsInput = array_values(array_unique(array_map('intval', $data['ticket_ids'])));
        }

        try {
            $allocationService = AppContainer::get()->checkoutAllocation();
            if (!$allocationService->isManualRaffle((int)$data['id_raffle'])) {
                $ticketIdsInput = null;
            } elseif ($ticketIdsInput !== null && count($ticketIdsInput) !== $cantidad) {
                return ['success' => false, 'message' => 'La cantidad no coincide con los nros seleccionados'];
            }
        } catch (DomainException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }

        $ticketIds = null;
        try {
            $reservation = AppContainer::get()->checkoutAllocation()->reserveForPendingPayment(
                (int)$data['id_raffle'],
                $cantidad,
                $ticketIdsInput,
                true
            );
            $ticketIds = $reservation['ticket_ids'] !== [] ? $reservation['ticket_ids'] : null;
        } catch (DomainException $e) {
            return TicketConflictApiResponse::fromDomainException($e);
        } catch (Throwable) {
            return ['success' => false, 'message' => 'No se pudieron reservar los nros'];
        }

        $insert = [
            'code_transfer' => $code,
            'id_raffle_transfer' => (int)$data['id_raffle'],
            'id_customer_transfer' => $idCustomer,
            'quantity_transfer' => $cantidad,
            'amount_transfer' => $montoEsperado,
            'currency_transfer' => 'COP',
            'status_transfer' => 1,
            'source_transfer' => $data['source_transfer'] ?? null,
            'date_created_transfer' => date('Y-m-d H:i:s'),
        ];
        $metaContext = \App\Application\Marketing\MetaConversionsApi::buildCheckoutMetaContext($data);
        if ($metaContext !== []) {
            $insert['meta_transfer'] = json_encode($metaContext, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        if ($ticketIds !== null) {
            $insert['ticket_ids_transfer'] = json_encode($ticketIds);
        }

        $idTransfer = Db::insert(self::TABLE, $insert);

        if ($idTransfer <= 0) {
            if ($ticketIds !== null) {
                AppContainer::get()->tickets()->release((int)$data['id_raffle'], $ticketIds);
            }

            return ['success' => false, 'message' => 'Error creando transferencia'];
        }

        return [
            'success' => true,
            'id_transfer' => $idTransfer,
            'code_transfer' => $code,
        ];
    }

    public static function obtenerPorCode(string $code)
    {
        $code = trim($code);
        $row = Db::fetchOne(
            'SELECT * FROM transfers WHERE code_transfer = :c LIMIT 1',
            [':c' => $code]
        );

        return $row ? (array)$row : null;
    }

    public static function aprobarTransferencia(array $transfer)
    {
        $transfer = self::loadTransferForAction($transfer);
        if ($transfer === null) {
            return ['success' => false, 'message' => 'Transferencia no encontrada'];
        }

        if ((int)$transfer['status_transfer'] !== 1) {
            return ['success' => false, 'message' => 'Ya procesado'];
        }

        $cantidad = (int)$transfer['quantity_transfer'];
        $idRaffle = (int)$transfer['id_raffle_transfer'];

        if ($cantidad <= 0) {
            return ['success' => false, 'message' => 'Cantidad inválida'];
        }

        $reservedIds = \App\Application\Ticket\RaffleCheckoutAllocationService::decodeTicketIds(
            $transfer['ticket_ids_transfer'] ?? null
        );

        try {
            if (!AppContainer::get()->checkoutAllocation()->isManualRaffle($idRaffle) && $reservedIds !== []) {
                AppContainer::get()->tickets()->release($idRaffle, $reservedIds);
                $reservedIds = [];
            }
        } catch (DomainException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }

        if ($reservedIds === []) {
            $countRow = Db::fetchOne(
                'SELECT COUNT(*) AS c FROM tickets WHERE id_raffle_ticket = :r AND status_ticket = 0',
                [':r' => $idRaffle]
            );
            $disponibles = (int)($countRow->c ?? 0);

            if ($disponibles < $cantidad) {
                return ['success' => false, 'message' => 'No hay suficientes nros disponibles'];
            }
        } elseif (!self::verifyReservedTicketsStillHeld($idRaffle, $reservedIds)) {
            return [
                'success' => false,
                'message' => 'Algunos nros reservados ya no están disponibles. Rechace la solicitud para liberarlos o pida al cliente una nueva compra.',
            ];
        }

        $calc = VentasController::calcularTotalPorPrecioRifa((int)$transfer['id_raffle_transfer'], $cantidad);
        if (!$calc['success']) {
            return $calc;
        }
        $totalEsperado = $calc['total'];
        $montoReg = (float)($transfer['amount_transfer'] ?? 0);
        if (!VentasController::montosEquivalentesCOP($montoReg, $totalEsperado)) {
            return [
                'success' => false,
                'message' => 'El monto guardado no coincide con el precio vigente. Rechace la solicitud o actualice el precio y vuelva a intentar.',
            ];
        }

        try {
            $allocation = AppContainer::get()->checkoutAllocation()->resolveTicketIdsForSaleApproval(
                $idRaffle,
                $cantidad,
                $reservedIds !== [] ? $reservedIds : null
            );
            $ticketIdsForSale = $allocation['ticket_ids'];
        } catch (DomainException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }

        $resVenta = VentasController::crearVenta([
            'id_customer' => $transfer['id_customer_transfer'],
            'id_raffle' => $transfer['id_raffle_transfer'],
            'quantity_sale' => $cantidad,
            'total_sale' => $totalEsperado,
            'code_sale' => $transfer['code_transfer'],
            'payment_method_sale' => 'Transferencia',
            'id_admin' => $_SESSION['user_id'] ?? null,
            'source_sale' => $transfer['source_transfer'] ?? null,
            'meta_user_data' => \App\Application\Marketing\MetaConversionsApi::userDataFromStoredMeta(
                \App\Application\Marketing\MetaConversionsApi::extractStoredMeta($transfer)
            ),
        ], $ticketIdsForSale, true);

        if (empty($resVenta['success']) || empty($resVenta['id_sale'])) {
            return ['success' => false, 'message' => $resVenta['message'] ?? 'Error creando la venta'];
        }

        $n = Db::update(
            self::TABLE,
            ['status_transfer' => 2],
            'id_transfer = :id AND status_transfer = 1',
            [':id' => (int)$transfer['id_transfer']]
        );

        if ($n < 1) {
            return [
                'success' => false,
                'message' => 'La venta se creó pero no se pudo marcar la transferencia como aprobada. Revisar manualmente.',
                'id_sale' => (int)$resVenta['id_sale'],
            ];
        }

        return [
            'success' => true,
            'id_sale' => (int)$resVenta['id_sale'],
            'message' => 'Venta creada correctamente',
        ];
    }

    public static function rechazarTransferencia(array $transfer)
    {
        $transfer = self::loadTransferForAction($transfer);
        if ($transfer === null) {
            return ['success' => false, 'message' => 'Transferencia no encontrada'];
        }

        $status = (int)($transfer['status_transfer'] ?? 0);
        if ($status === 3) {
            return ['success' => false, 'message' => 'Esta transferencia ya está rechazada'];
        }

        if ($status === 2) {
            return self::rechazarTransferenciaConfirmada($transfer);
        }

        if ($status !== 1) {
            return ['success' => false, 'message' => 'Estado de transferencia no válido'];
        }

        $rawIds = $transfer['ticket_ids_transfer'] ?? null;
        if ($rawIds) {
            $decoded = is_string($rawIds) ? json_decode($rawIds, true) : $rawIds;
            if (is_array($decoded) && $decoded !== []) {
                try {
                    AppContainer::get()->tickets()->release(
                        (int)$transfer['id_raffle_transfer'],
                        array_map('intval', $decoded)
                    );
                } catch (Throwable) {
                }
            }
        }

        $n = Db::update(
            self::TABLE,
            ['status_transfer' => 3],
            'id_transfer = :id AND status_transfer = 1',
            [':id' => (int)$transfer['id_transfer']]
        );

        if ($n < 1) {
            return ['success' => false, 'message' => 'Error al rechazar'];
        }

        return ['success' => true, 'message' => 'Transferencia rechazada'];
    }

    /**
     * Anula la venta web asociada y marca la transferencia como rechazada.
     *
     * @param array<string, mixed> $transfer
     * @return array<string, mixed>
     */
    private static function rechazarTransferenciaConfirmada(array $transfer): array
    {
        $idTransfer = (int)($transfer['id_transfer'] ?? 0);
        $code = trim((string)($transfer['code_transfer'] ?? ''));
        if ($idTransfer <= 0 || $code === '') {
            return ['success' => false, 'message' => 'Transferencia inválida'];
        }

        $sale = Db::fetchOne(
            'SELECT id_sale, status_sale FROM sales WHERE code_sale = :c AND status_sale = 1 ORDER BY id_sale DESC LIMIT 1',
            [':c' => $code]
        );

        if ($sale) {
            if (!class_exists('VentasController', false)) {
                require_once __DIR__ . '/ventas.controller.php';
            }

            $cancel = VentasController::anularVenta((int)$sale->id_sale);
            if (empty($cancel['success'])) {
                return [
                    'success' => false,
                    'message' => (string)($cancel['message'] ?? 'No se pudo anular la venta asociada'),
                ];
            }
        }

        $n = Db::update(
            self::TABLE,
            ['status_transfer' => 3],
            'id_transfer = :id AND status_transfer = 2',
            [':id' => $idTransfer]
        );

        if ($n < 1) {
            return ['success' => false, 'message' => 'No se pudo marcar la transferencia como rechazada'];
        }

        return [
            'success' => true,
            'message' => $sale
                ? 'Venta anulada y transferencia rechazada. Los números quedaron disponibles.'
                : 'Transferencia rechazada',
            'id_sale' => $sale ? (int)$sale->id_sale : null,
        ];
    }

    public static function obtenerTransferencias(array $params = []): array
    {
        $page = max(1, (int)($params['page'] ?? $_POST['page'] ?? 1));
        $limit = min(50, max(1, (int)($params['limit'] ?? $_POST['limit'] ?? 10)));
        $offset = ($page - 1) * $limit;

        $statusRaw = $params['status'] ?? $_POST['status'] ?? '2';
        $status = trim((string)$statusRaw);
        $search = trim((string)($params['search'] ?? $_POST['search'] ?? ''));
        $fechaInicio = trim((string)($params['fecha_inicio'] ?? $_POST['fecha_inicio'] ?? ''));
        $fechaFin = trim((string)($params['fecha_fin'] ?? $_POST['fecha_fin'] ?? ''));

        $where = ['1=1'];
        $bind = [];

        if ($status !== '') {
            $where[] = 't.status_transfer = :status';
            $bind[':status'] = (int)$status;
        }

        if ($search !== '') {
            $like = '%' . $search . '%';
            $searchParts = [
                't.code_transfer LIKE :q_code',
                't.proof_reference LIKE :q_proof_ref',
                't.proof_reference_norm LIKE :q_proof_ref_norm',
                'c.name_customer LIKE :q_name',
                'c.lastname_customer LIKE :q_lastname',
                "CONCAT(TRIM(c.name_customer), ' ', TRIM(c.lastname_customer)) LIKE :q_fullname",
                'c.phone_customer LIKE :q_phone',
                'c.email_customer LIKE :q_email',
            ];
            $bind[':q_code'] = $like;
            $bind[':q_proof_ref'] = $like;
            $bind[':q_proof_ref_norm'] = $like;
            $bind[':q_name'] = $like;
            $bind[':q_lastname'] = $like;
            $bind[':q_fullname'] = $like;
            $bind[':q_phone'] = $like;
            $bind[':q_email'] = $like;

            $phoneDigits = preg_replace('/\D+/', '', $search);
            if ($phoneDigits !== '' && strlen($phoneDigits) >= 3) {
                $searchParts[] = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(c.phone_customer, ' ', ''), '-', ''), '+', ''), '(', ''), ')', '') LIKE :q_phone_digits";
                $bind[':q_phone_digits'] = '%' . $phoneDigits . '%';
            }

            $where[] = '(' . implode(' OR ', $searchParts) . ')';
        }

        if ($fechaInicio !== '' && $fechaFin !== '') {
            $where[] = 'DATE(t.date_created_transfer) BETWEEN :fi AND :ff';
            $bind[':fi'] = $fechaInicio;
            $bind[':ff'] = $fechaFin;
        }

        $whereSql = implode(' AND ', $where);
        $fromSql = ' FROM transfers t INNER JOIN customers c ON c.id_customer = t.id_customer_transfer';

        $selectSql = 'SELECT t.id_transfer,t.code_transfer,t.quantity_transfer,t.amount_transfer,t.status_transfer,
            t.date_created_transfer,t.source_transfer,t.url_transfer,t.id_raffle_transfer,t.id_customer_transfer,
            t.ticket_ids_transfer,t.expires_at_transfer,
            t.proof_reference,t.proof_reference_norm,t.proof_bank,
            c.name_customer,c.lastname_customer,c.phone_customer,c.email_customer,
            r.title_raffle,r.type_raffle'
            . $fromSql
            . ' INNER JOIN raffles r ON r.id_raffle = t.id_raffle_transfer'
            . ' WHERE ' . $whereSql
            . ' ORDER BY t.id_transfer DESC LIMIT ' . (int)$limit . ' OFFSET ' . (int)$offset;

        $lista = Db::fetchAll($selectSql, $bind);
        $lista = self::enrichTransfersWithReservedNumbers($lista);
        $lista = array_map(static fn (array $row): array => self::enrichTransferWithSaleInfo($row), $lista);

        $countSt = Db::pdo()->prepare('SELECT COUNT(*)' . $fromSql . ' WHERE ' . $whereSql);
        $countSt->execute($bind);
        $total = (int)$countSt->fetchColumn();

        return [
            'success' => true,
            'data' => $lista,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
        ];
    }

    /**
     * Venta asociada y números vendidos (transferencias ya confirmadas automáticamente).
     *
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private static function enrichTransferWithSaleInfo(array $item): array
    {
        $status = (int)($item['status_transfer'] ?? 0);
        if (!in_array($status, [2, 3], true)) {
            return $item;
        }

        $code = (string)($item['code_transfer'] ?? '');
        if ($code === '') {
            return $item;
        }

        $saleSql = $status === 2
            ? 'SELECT id_sale, status_sale FROM sales WHERE code_sale = :c AND status_sale = 1 ORDER BY id_sale DESC LIMIT 1'
            : 'SELECT id_sale, status_sale FROM sales WHERE code_sale = :c ORDER BY id_sale DESC LIMIT 1';

        $sale = Db::fetchOne($saleSql, [':c' => $code]);
        if (!$sale) {
            return $item;
        }

        $item['id_sale'] = (int)$sale->id_sale;
        $item['sale_cancelled'] = (int)($sale->status_sale ?? 1) === 0;

        $rows = Db::fetchAll(
            'SELECT number_ticket, status_item FROM sale_items WHERE id_sale = :s ORDER BY number_ticket ASC',
            [':s' => $item['id_sale']]
        );
        $numbers = [];
        foreach ($rows as $row) {
            $numbers[] = (string)$row->number_ticket;
        }

        if ($numbers !== []) {
            $item['reserved_numbers'] = $numbers;
            if ($status === 3 || !empty($item['sale_cancelled'])) {
                $item['reserved_numbers_label'] = implode(', ', $numbers) . ' (liberados)';
            } else {
                $item['reserved_numbers_label'] = implode(', ', $numbers);
            }
        }

        return $item;
    }

    /**
     * @param array<string, mixed> $transfer
     * @return array<string, mixed>|null
     */
    private static function loadTransferForAction(array $transfer): ?array
    {
        $id = (int)($transfer['id_transfer'] ?? 0);
        if ($id <= 0) {
            return null;
        }

        $row = Db::fetchOne(
            'SELECT * FROM transfers WHERE id_transfer = :id LIMIT 1',
            [':id' => $id]
        );

        if (!$row) {
            return null;
        }

        return (array)$row;
    }

    /**
     * @param list<int> $ticketIds
     */
    private static function verifyReservedTicketsStillHeld(int $raffleId, array $ticketIds): bool
    {
        if ($ticketIds === []) {
            return true;
        }

        $placeholders = implode(',', array_fill(0, count($ticketIds), '?'));
        $sql = "SELECT COUNT(*) FROM tickets
                WHERE id_raffle_ticket = ?
                  AND status_ticket = 2
                  AND id_ticket IN ({$placeholders})";
        $params = array_merge([$raffleId], $ticketIds);
        $st = Db::pdo()->prepare($sql);
        $st->execute($params);

        return (int)$st->fetchColumn() === count($ticketIds);
    }

    public static function comprobanteViewUrl(int $idTransfer): string
    {
        if ($idTransfer <= 0) {
            return '';
        }

        $base = defined('BASE_URL') ? rtrim((string) BASE_URL, '/') : '';

        return ($base !== '' ? $base : '') . '/front/comprobante.php?id_transfer=' . $idTransfer;
    }

    public static function resolveComprobanteFilesystemPath(?string $urlOrPath): ?string
    {
        $raw = trim((string) $urlOrPath);
        if ($raw === '') {
            return null;
        }

        $path = parse_url($raw, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            $path = $raw;
        }

        $path = ltrim(str_replace('\\', '/', $path), '/');
        if (!str_starts_with($path, 'uploads/comprobantes/')) {
            return null;
        }

        $root = defined('ROOT_PATH') ? (string) ROOT_PATH : '';
        if ($root === '') {
            return null;
        }

        $full = rtrim($root, '/\\') . '/' . $path;
        $real = realpath($full);
        $baseDir = realpath(rtrim($root, '/\\') . '/uploads/comprobantes');
        if ($real === false || $baseDir === false) {
            return null;
        }

        $basePrefix = rtrim(str_replace('\\', '/', $baseDir), '/') . '/';
        $realNorm = str_replace('\\', '/', $real);
        if (!str_starts_with($realNorm, $basePrefix)) {
            return null;
        }

        return is_file($real) ? $real : null;
    }

  /**
     * Bre-B es red de pagos, no banco. Normaliza campos para el panel.
     *
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private static function enrichProofDisplayFields(array $item): array
    {
        $bank = trim((string)($item['proof_bank'] ?? ''));
        if ($bank !== '' && self::isPaymentRailLabel($bank)) {
            $item['proof_bank'] = null;
        }

        return $item;
    }

    private static function isPaymentRailLabel(string $label): bool
    {
        return in_array(mb_strtolower(trim($label)), ['bre-b', 'breb'], true);
    }

    /**
     * @param list<object|array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private static function enrichTransfersWithReservedNumbers(array $rows): array
    {
        $allocation = AppContainer::get()->checkoutAllocation();
        $out = [];

        foreach ($rows as $row) {
            $item = (array)$row;
            $idRaffle = (int)($item['id_raffle_transfer'] ?? 0);
            $ticketIds = \App\Application\Ticket\RaffleCheckoutAllocationService::decodeTicketIds(
                $item['ticket_ids_transfer'] ?? null
            );
            $numbers = $ticketIds !== []
                ? $allocation->numbersForTicketIds($idRaffle, $ticketIds)
                : [];
            $type = (string)($item['type_raffle'] ?? 'automatic');
            $item['reserved_numbers'] = $numbers;
            $item['reserved_numbers_label'] = $numbers !== []
                ? implode(', ', $numbers)
                : ($type === 'manual' ? '—' : 'Automático al aprobar');
            if (!empty($item['url_transfer'])) {
                $item['comprobante_view_url'] = self::comprobanteViewUrl((int)($item['id_transfer'] ?? 0));
            }
            $item = self::enrichProofDisplayFields($item);
            $out[] = $item;
        }

        return $out;
    }

    public static function obtenerSettings()
    {
        $rows = Db::fetchAll('SELECT key_setting, value_setting FROM settings');

        $map = [];
        foreach ($rows as $item) {
            $map[$item->key_setting] = $item->value_setting;
        }

        return $map;
    }
}
