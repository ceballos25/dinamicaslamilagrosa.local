<?php
declare(strict_types=1);

namespace App\Application\Web;

use App\Application\Comprobante\CheckoutUserMessages;
use App\Application\Comprobante\ComprobanteFilenameBuilder;
use App\Application\Comprobante\ComprobanteValidationRequest;
use App\Application\Comprobante\ComprobanteValidator;
use App\Application\Comprobante\ComprobanteValidatorFactory;
use App\Application\Maintenance\MaintenanceService;
use App\Application\Marketing\MetaConversionsApi;
use App\Application\Marketing\MetaPixelGuard;
use App\Infrastructure\Repository\PdoRaffleRepository;
use App\Infrastructure\Repository\PdoSiteImageRepository;
use App\Shared\Config\DynamicConfig;
use App\Shared\Date\EventDateFormatter;

final class WebCheckoutService
{
    private const SETTING_WEB_COMPRAS = 'web_compras_habilitadas';

    private const READ_ACTIONS = [
        'bootstrap_landing',
        'rifas_activas', 'config_publica',
        'inventario', 'progreso', 'buscar_cliente_checkout', 'buscar_numeros',
    ];

    private ?ComprobanteValidator $comprobanteValidator = null;

    public function __construct(
        private readonly DynamicConfig $dynamicConfig,
        ?ComprobanteValidator $comprobanteValidator = null,
    ) {
        $this->comprobanteValidator = $comprobanteValidator;
    }

    public function execute(string $action, array $payload, array $files): array
    {
        if (!in_array($action, self::READ_ACTIONS, true) && !$this->areWebPurchasesEnabled()) {
            return [
                'success' => false,
                'message' => 'Las compras en línea están temporalmente deshabilitadas.',
            ];
        }

        if (!in_array($action, self::READ_ACTIONS, true)) {
            $customerBlock = $this->assertCustomerCanPurchase($payload);
            if ($customerBlock !== null) {
                return $customerBlock;
            }
        }

        return match ($action) {
            'crear_transferencia_completa' => $this->createTransfer($payload, $files),
            'bootstrap_landing' => $this->bootstrapLanding($payload),
            'rifas_activas' => $this->getActiveRaffles(),
            'config_publica' => $this->getPublicConfig(),
            'inventario' => $this->getPublicInventory($payload),
            'progreso' => $this->getPublicProgress($payload),
            'buscar_cliente_checkout' => $this->lookupCustomerByPhone($payload),
            'buscar_numeros' => $this->searchPublicNumbers($payload),
            default => ['success' => false, 'message' => 'Accion no valida'],
        };
    }

    public function arePurchasesAllowed(): bool
    {
        return $this->areWebPurchasesEnabled();
    }

    private function createTransfer(array $payload, array $files): array
    {
        if (empty($files['comprobante']) || !is_array($files['comprobante'])) {
            return CheckoutUserMessages::missingProof();
        }

        $file = $files['comprobante'];
        $err = (int)($file['error'] ?? \UPLOAD_ERR_OK);
        if ($err !== \UPLOAD_ERR_OK) {
            return CheckoutUserMessages::uploadError($err);
        }

        $tmp = (string)($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            return CheckoutUserMessages::invalidUpload();
        }

        if (!self::isAllowedComprobanteMime($tmp, (string)($file['type'] ?? ''))) {
            return CheckoutUserMessages::invalidFormat();
        }

        if (((int)($file['size'] ?? 0)) > (5 * 1024 * 1024)) {
            return CheckoutUserMessages::fileTooLarge();
        }

        $extension = ComprobanteFilenameBuilder::extensionFromMime($tmp, (string)($file['type'] ?? ''));
        $expectedAmount = $this->resolveExpectedTransferAmount($payload);
        if ($expectedAmount === null) {
            return CheckoutUserMessages::totalCalculationFailed();
        }

        $proofValidation = $this->comprobanteValidator()->validate(
            new ComprobanteValidationRequest($tmp, $extension, 0, $expectedAmount)
        );
        if (!$proofValidation->accepted) {
            return array_merge(
                ['success' => false],
                $proofValidation->toUserErrorPayload(),
            );
        }

        $transfer = \TransfersController::crearTransferencia(array_merge($payload, [
            'ticket_ids' => $payload['ticket_ids'] ?? ($_POST['ticket_ids'] ?? null),
        ]));
        if (empty($transfer['success']) || empty($transfer['id_transfer'])) {
            return $transfer;
        }
        $idTransfer = (int)$transfer['id_transfer'];

        $name = ComprobanteFilenameBuilder::build(
            $proofValidation->referenceNorm,
            $extension,
            $idTransfer
        );
        $relativePath = 'uploads/comprobantes/' . $name;
        $absolutePath = self::resolveComprobanteAbsolutePath($relativePath);
        if (is_file($absolutePath)) {
            $name = ComprobanteFilenameBuilder::build(null, $extension, $idTransfer);
            $relativePath = 'uploads/comprobantes/' . $name;
            $absolutePath = self::resolveComprobanteAbsolutePath($relativePath);
        }
        $dir = dirname($absolutePath);

        if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
            \Db::delete('transfers', 'id_transfer = :id', [':id' => $idTransfer]);

            return ['success' => false, 'message' => 'No se pudo crear la carpeta de comprobantes: ' . $dir];
        }

        if (!is_writable($dir)) {
            \Db::delete('transfers', 'id_transfer = :id', [':id' => $idTransfer]);

            return ['success' => false, 'message' => 'Sin permiso de escritura en ' . $dir];
        }

        if (!move_uploaded_file($tmp, $absolutePath)) {
            \Db::delete('transfers', 'id_transfer = :id', [':id' => $idTransfer]);

            return ['success' => false, 'message' => 'No se pudo guardar el archivo'];
        }

        $base = defined('BASE_URL') ? rtrim((string)\BASE_URL, '/') : '';
        $fileUrl = ($base !== '' ? $base . '/' : '/') . $relativePath;

        $n = \Db::update(
            'transfers',
            array_merge(['url_transfer' => $fileUrl], $proofValidation->toProofColumns()),
            'id_transfer = :id',
            [':id' => $idTransfer]
        );
        if ($n < 1) {
            @unlink($absolutePath);
            \Db::delete('transfers', 'id_transfer = :id', [':id' => $idTransfer]);

            return ['success' => false, 'message' => 'Error actualizando transferencia'];
        }

        $this->sendInitiateCheckoutCapi($payload, $transfer, (float)$expectedAmount);

        return [
            'success' => true,
            'code_transfer' => $transfer['code_transfer'] ?? null,
            'approved' => false,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $transfer
     */
    private function sendInitiateCheckoutCapi(array $payload, array $transfer, float $amount): void
    {
        try {
            $checkoutCustomer = [
                'name_customer' => $payload['name_customer'] ?? '',
                'lastname_customer' => $payload['lastname_customer'] ?? '',
                'phone_customer' => $payload['phone_customer'] ?? '',
                'email_customer' => $payload['email_customer'] ?? '',
                'city_customer' => $payload['city_customer'] ?? '',
                'department_customer' => $payload['department_customer'] ?? '',
            ];
            MetaConversionsApi::rememberMatchUserData(MetaConversionsApi::userDataFromInput($checkoutCustomer));

            if (!MetaConversionsApi::isCapiReady()) {
                return;
            }

            $codeTransfer = trim((string)($transfer['code_transfer'] ?? ''));
            $idTransfer = (int)($transfer['id_transfer'] ?? 0);
            if ($codeTransfer === '' || $idTransfer <= 0) {
                return;
            }

            $guard = new MetaPixelGuard();
            if (!$guard->shouldSendInitiateCheckout($codeTransfer)) {
                return;
            }

            $eventId = MetaConversionsApi::initiateCheckoutEventId($idTransfer, $codeTransfer);
            $sent = MetaConversionsApi::sendInitiateCheckout(array_merge($checkoutCustomer, [
                'id_transfer' => $idTransfer,
                'code_transfer' => $codeTransfer,
                'value' => $amount,
                'quantity' => (int)($payload['quantity'] ?? 0),
                'meta_fbp' => $payload['meta_fbp'] ?? '',
                'meta_fbc' => $payload['meta_fbc'] ?? '',
            ]), $eventId);

            if (!$sent) {
                return;
            }

            $guard->markInitiateCheckoutSent($codeTransfer);

            if (class_exists('AppContainer')) {
                try {
                    \AppContainer::get()->audit()->record(
                        'meta.initiate_checkout.sent',
                        'transfer',
                        $idTransfer,
                        null,
                        ['code_transfer' => $codeTransfer]
                    );
                } catch (\Throwable) {
                }
            }
        } catch (\Throwable) {
        }
    }

    private function areWebPurchasesEnabled(): bool
    {
        $raw = strtolower(trim((string)$this->dynamicConfig->get(self::SETTING_WEB_COMPRAS, '1')));

        return !in_array($raw, ['0', 'false', 'no', 'off'], true);
    }

    private function comprobanteValidator(): ComprobanteValidator
    {
        return $this->comprobanteValidator ??= ComprobanteValidatorFactory::fromConfig($this->dynamicConfig);
    }

    private function resolveExpectedTransferAmount(array $payload): ?float
    {
        if (!class_exists('VentasController')) {
            require_once dirname(__DIR__, 3) . '/controllers/ventas.controller.php';
        }

        $idRaffle = (int)($payload['id_raffle'] ?? 0);
        $quantity = (int)($payload['quantity'] ?? 0);
        if ($idRaffle <= 0 || $quantity <= 0) {
            return null;
        }

        $calc = \VentasController::calcularTotalPorPrecioRifa($idRaffle, $quantity);
        if (empty($calc['success'])) {
            return null;
        }

        return (float)($calc['total'] ?? 0);
    }

    private static function isAllowedComprobanteMime(string $tmp, string $browserType): bool
    {
        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/jpg', 'image/pjpeg'];
        $browserType = strtolower(trim($browserType));
        if ($browserType !== '' && in_array($browserType, $allowed, true)) {
            return true;
        }
        if (!is_file($tmp) || !is_readable($tmp) || !function_exists('finfo_open')) {
            return false;
        }
        $f = finfo_open(\FILEINFO_MIME_TYPE);
        if ($f === false) {
            return false;
        }
        $mime = strtolower((string)finfo_file($f, $tmp));
        finfo_close($f);

        return in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true);
    }

    private static function resolveComprobanteAbsolutePath(string $relativePath): string
    {
        $bases = [];
        if (defined('ROOT_PATH') && \ROOT_PATH !== false && \ROOT_PATH !== '') {
            $bases[] = rtrim((string)\ROOT_PATH, '/');
        }
        $doc = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
        if ($doc !== '') {
            $bases[] = $doc;
        }
        $bases = array_values(array_unique(array_filter($bases)));

        foreach ($bases as $base) {
            $full = $base . '/' . $relativePath;
            $parent = dirname($full);
            if (is_dir($parent) && is_writable($parent)) {
                return $full;
            }
            if (is_writable($base)) {
                return $full;
            }
        }

        return ($bases[0] ?? '') . '/' . $relativePath;
    }

    private function getActiveRaffles(): array
    {
        $rows = (new PdoRaffleRepository())->findAllActive();

        $data = [];
        foreach ($rows as $r) {
            $data[] = [
                'id_raffle' => (int)$r['id_raffle'],
                'title_raffle' => $r['title_raffle'],
                'description_raffle' => $r['description_raffle'],
                'price_raffle' => (float)$r['price_raffle'],
                'digits_raffle' => (int)$r['digits_raffle'],
                'type_raffle' => $r['type_raffle'] ?? 'automatic',
                'min_quantity_raffle' => (int)($r['min_quantity_raffle'] ?? 1),
                'date_raffle' => $r['date_raffle'] ?? null,
                'event_date_label' => EventDateFormatter::formatDayMonth($r['date_raffle'] ?? null),
            ];
        }

        return ['success' => true, 'data' => $data];
    }

    private function getPublicConfig(): array
    {
        return [
            'success' => true,
            'data' => $this->buildPublicConfigData(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPublicConfigData(): array
    {
        $maintenance = new MaintenanceService($this->dynamicConfig);

        return [
            'maintenance_mode' => $maintenance->isPublicBlocked(),
            'maintenance_message' => $maintenance->getMaintenanceMessage(),
            'sales_blocked' => $maintenance->areSalesBlocked(),
            'sales_blocked_message' => $maintenance->getSalesBlockedMessage(),
            'images' => (new PdoSiteImageRepository())->asKeyMap(),
            'web_id_raffle' => $this->dynamicConfig->get('web_id_raffle', ''),
            'pricing' => $this->publicPricingConfig(),
            'contact' => [
                'whatsapp' => trim((string)$this->dynamicConfig->get('whatsapp', '')),
                'whatsapp_chat_url' => trim((string)$this->dynamicConfig->get('whatsapp_chat_url', '')),
                'social_instagram_url' => trim((string)$this->dynamicConfig->get('social_instagram_url', '')),
                'social_facebook_url' => trim((string)$this->dynamicConfig->get('social_facebook_url', '')),
                'social_tiktok_url' => trim((string)$this->dynamicConfig->get('social_tiktok_url', '')),
                'transfer_breb_key' => trim((string)$this->dynamicConfig->get('transfer_breb_key', '')),
                'transfer_breb_name' => trim((string)$this->dynamicConfig->get('transfer_breb_name', '')),
                'transfer_bancolombia' => trim((string)$this->dynamicConfig->get('transfer_bancolombia', '')),
                'transfer_bancolombia_name' => trim((string)$this->dynamicConfig->get('transfer_bancolombia_name', '')),
                'transfer_nequi' => trim((string)$this->dynamicConfig->get('transfer_nequi', '')),
                'transfer_nequi_name' => trim((string)$this->dynamicConfig->get('transfer_nequi_name', '')),
            ],
            'settings' => $this->landingSettingsMap(),
        ];
    }

    /** @return array<string, string> */
    private function landingSettingsMap(): array
    {
        return [
            'web_id_raffle' => trim((string)$this->dynamicConfig->get('web_id_raffle', '')),
            'web_compras_habilitadas' => trim((string)$this->dynamicConfig->get('web_compras_habilitadas', '1')),
            'web_mensaje_compras_bloqueadas' => trim((string)$this->dynamicConfig->get('web_mensaje_compras_bloqueadas', '')),
            'barra' => trim((string)$this->dynamicConfig->get('barra', '')),
            'barra_modo' => trim((string)$this->dynamicConfig->get('barra_modo', 'sumar')),
            'barra_fija' => trim((string)$this->dynamicConfig->get('barra_fija', '')),
            'pricing_packages_enabled' => trim((string)$this->dynamicConfig->get('pricing_packages_enabled', '0')),
            'pricing_tiered_enabled' => trim((string)$this->dynamicConfig->get('pricing_tiered_enabled', '0')),
            'pricing_first_unit' => trim((string)$this->dynamicConfig->get('pricing_first_unit', '1500')),
            'pricing_tier1_unit' => trim((string)$this->dynamicConfig->get('pricing_tier1_unit', '1500')),
            'pricing_tier2_unit' => trim((string)$this->dynamicConfig->get('pricing_tier2_unit', '1200')),
            'pricing_bulk_threshold' => trim((string)$this->dynamicConfig->get('pricing_bulk_threshold', '40')),
            'social_instagram_url' => trim((string)$this->dynamicConfig->get('social_instagram_url', '')),
            'social_facebook_url' => trim((string)$this->dynamicConfig->get('social_facebook_url', '')),
            'social_tiktok_url' => trim((string)$this->dynamicConfig->get('social_tiktok_url', '')),
            'whatsapp' => trim((string)$this->dynamicConfig->get('whatsapp', '')),
            'whatsapp_chat_url' => trim((string)$this->dynamicConfig->get('whatsapp_chat_url', '')),
            'whatsapp_secondary' => trim((string)$this->dynamicConfig->get('whatsapp_secondary', '')),
            'transfer_breb_key' => trim((string)$this->dynamicConfig->get('transfer_breb_key', '')),
            'transfer_breb_name' => trim((string)$this->dynamicConfig->get('transfer_breb_name', '')),
            'transfer_bancolombia' => trim((string)$this->dynamicConfig->get('transfer_bancolombia', '')),
            'transfer_bancolombia_name' => trim((string)$this->dynamicConfig->get('transfer_bancolombia_name', '')),
            'transfer_nequi' => trim((string)$this->dynamicConfig->get('transfer_nequi', '')),
            'transfer_nequi_name' => trim((string)$this->dynamicConfig->get('transfer_nequi_name', '')),
            'nombre_rifa' => trim((string)$this->dynamicConfig->get('nombre_rifa', '')),
            'landing_meta_description' => trim((string)$this->dynamicConfig->get('landing_meta_description', '')),
            'landing_hero_title' => trim((string)$this->dynamicConfig->get('landing_hero_title', '')),
            'landing_premio_mayor_titulo' => trim((string)$this->dynamicConfig->get('landing_premio_mayor_titulo', '')),
            'landing_premio_mayor_desc' => trim((string)$this->dynamicConfig->get('landing_premio_mayor_desc', '')),
            'landing_premio_extra_titulo' => trim((string)$this->dynamicConfig->get('landing_premio_extra_titulo', '')),
            'landing_premio_extra_label' => trim((string)$this->dynamicConfig->get('landing_premio_extra_label', '')),
            'landing_premio_extra_desc' => trim((string)$this->dynamicConfig->get('landing_premio_extra_desc', '')),
            'footer_tagline' => trim((string)$this->dynamicConfig->get('footer_tagline', '')),
            'footer_phones' => trim((string)$this->dynamicConfig->get('footer_phones', '')),
            'contact_email' => trim((string)$this->dynamicConfig->get('contact_email', '')),
            'footer_location' => trim((string)$this->dynamicConfig->get('footer_location', '')),
            'footer_payments' => trim((string)$this->dynamicConfig->get('footer_payments', '')),
        ];
    }

    private function bootstrapLanding(array $payload): array
    {
        $config = $this->buildPublicConfigData();
        $rifas = $this->getActiveRaffles()['data'] ?? [];
        $idRaffle = $this->resolveLandingRaffleId($config, $rifas, (int)($payload['id_raffle'] ?? 0));

        $progreso = null;
        if ($idRaffle > 0) {
            $progress = $this->getPublicProgress(['id_raffle' => $idRaffle]);
            $progreso = !empty($progress['success']) ? $progress : null;
        }

        return [
            'success' => true,
            'data' => [
                'config' => $config,
                'rifas' => $rifas,
                'id_raffle_resolved' => $idRaffle > 0 ? $idRaffle : null,
                'progreso' => $progreso,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $config
     * @param list<array<string, mixed>> $rifas
     */
    private function resolveLandingRaffleId(array $config, array $rifas, int $requestedId): int
    {
        if ($requestedId > 0) {
            foreach ($rifas as $r) {
                if ((int)($r['id_raffle'] ?? 0) === $requestedId) {
                    return $requestedId;
                }
            }
        }

        $idCfg = (int)trim((string)($config['web_id_raffle'] ?? ''));
        if ($idCfg > 0) {
            foreach ($rifas as $r) {
                if ((int)($r['id_raffle'] ?? 0) === $idCfg) {
                    return $idCfg;
                }
            }
        }

        if (count($rifas) > 0) {
            return (int)($rifas[0]['id_raffle'] ?? 0);
        }

        return 0;
    }

    /**
     * @param array<string, mixed>|null $inventario
     * @return array<string, mixed>|null
     */
    private function progressFromInventory(?array $inventario, int $idRaffle): ?array
    {
        if (is_array($inventario) && !empty($inventario['success'])) {
            $stats = $inventario['stats'] ?? null;
            if (is_array($stats) && (int)($stats['total'] ?? 0) > 0) {
                $total = (int)$stats['total'];
                $vendidos = (int)($stats['vendidos'] ?? 0);

                return [
                    'success' => true,
                    'total' => $total,
                    'vendidos' => $vendidos,
                    'porcentaje' => round(($vendidos * 100) / $total, 2),
                ];
            }
        }

        $progress = $this->getPublicProgress(['id_raffle' => $idRaffle]);

        return !empty($progress['success']) ? $progress : null;
    }

    private function getPublicInventory(array $payload): array
    {
        if (!class_exists('NumerosController')) {
            require_once dirname(__DIR__, 3) . '/controllers/numeros.controller.php';
        }

        $_POST['id_raffle'] = (int)($payload['id_raffle'] ?? 0);
        $_POST['grilla'] = '1';
        $_POST['status'] = '0';

        return \NumerosController::obtenerInventario();
    }

    private function searchPublicNumbers(array $payload): array
    {
        if (!class_exists('NumerosController')) {
            require_once dirname(__DIR__, 3) . '/controllers/numeros.controller.php';
        }

        $_POST['id_raffle'] = (int)($payload['id_raffle'] ?? 0);
        $_POST['search'] = trim((string)($payload['search'] ?? ''));
        $_POST['status'] = '0';

        $result = \NumerosController::obtenerInventario();
        if (!empty($result['success']) && is_array($result['data'] ?? null)) {
            $result['data'] = array_slice($result['data'], 0, 150);
        }

        return $result;
    }

    private function getPublicProgress(array $payload): array
    {
        if (!class_exists('NumerosController')) {
            require_once dirname(__DIR__, 3) . '/controllers/numeros.controller.php';
        }

        return \NumerosController::obtenerProgreso((int)($payload['id_raffle'] ?? 0));
    }

    private function lookupCustomerByPhone(array $payload): array
    {
        $phone = \ClientesController::normalizarTelefono((string)($payload['phone_customer'] ?? ''));
        if (strlen($phone) !== 10) {
            return ['success' => false, 'message' => 'Teléfono inválido'];
        }

        $check = \ClientesController::telefonoPermiteCompra($phone);
        if (!$check['allowed']) {
            return [
                'success' => false,
                'blocked' => true,
                'message' => $check['message'],
            ];
        }

        $row = \Db::fetchOne(
            'SELECT name_customer, lastname_customer, email_customer, department_customer, city_customer
             FROM customers WHERE phone_customer = :p AND status_customer = 1 LIMIT 1',
            [':p' => $phone]
        );

        if (!$row) {
            return ['success' => true, 'data' => null];
        }

        MetaConversionsApi::rememberMatchUserData(MetaConversionsApi::userDataFromInput([
            'email' => (string)($row->email_customer ?? ''),
            'phone' => $phone,
            'name' => (string)($row->name_customer ?? ''),
            'lastname' => (string)($row->lastname_customer ?? ''),
            'city' => (string)($row->city_customer ?? ''),
            'state' => (string)($row->department_customer ?? ''),
        ]));

        return [
            'success' => true,
            'data' => [
                'name_customer' => (string)($row->name_customer ?? ''),
                'lastname_customer' => (string)($row->lastname_customer ?? ''),
                'email_customer' => (string)($row->email_customer ?? ''),
                'department_customer' => (string)($row->department_customer ?? ''),
                'city_customer' => (string)($row->city_customer ?? ''),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function publicPricingConfig(): array
    {
        $packagePricing = \App\Application\Pricing\RafflePackagePricing::fromConfig($this->dynamicConfig);
        if ($packagePricing->isEnabled()) {
            return $packagePricing->toPublicArray();
        }

        return \App\Application\Pricing\RaffleQuantityPricing::fromConfig($this->dynamicConfig)->toPublicArray();
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>|null
     */
    private function assertCustomerCanPurchase(array $payload): ?array
    {
        $phone = (string)($payload['phone_customer'] ?? '');
        if (trim($phone) === '') {
            return null;
        }

        $check = \ClientesController::telefonoPermiteCompra($phone);
        if (!$check['allowed']) {
            return [
                'success' => false,
                'blocked' => true,
                'message' => $check['message'],
            ];
        }

        return null;
    }
}

