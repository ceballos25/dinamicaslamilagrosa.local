<?php
declare(strict_types=1);

namespace App\Application\Marketing;

final class MetaConversionsApi
{
    private const DEFAULT_API_VERSION = 'v20.0';
    private const FBC_MAX_AGE_SECONDS = 90 * 24 * 60 * 60;
    private const MATCH_COOKIE = '_cs_mm';
    private const SESSION_MATCH_KEY = 'meta_match_user_data';
    private const MATCH_COOKIE_TTL = 90 * 24 * 60 * 60;

    /** @var list<string> */
    private const MATCH_USER_DATA_KEYS = ['em', 'ph', 'fn', 'ln', 'ct', 'st', 'country', 'external_id'];

    /** Eventos permitidos en navegador (Pixel / meta.ajax.php). */
    public const BROWSER_TRACK_EVENTS = [
        'PageView',
        'ViewContent',
        'AddToCart',
        'InitiateCheckout',
        'Lead',
        'AddPaymentInfo',
        'Contact',
    ];

    /** Eventos enviados por CAPI en servidor. */
    public const CAPI_TRACK_EVENTS = [
        'PageView',
        'ViewContent',
        'AddToCart',
        'InitiateCheckout',
        'Lead',
        'AddPaymentInfo',
        'Contact',
        'Purchase',
    ];

    /** @var list<string> */
    public const STANDARD_EVENTS = [
        'PageView',
        'ViewContent',
        'AddToCart',
        'InitiateCheckout',
        'Lead',
        'AddPaymentInfo',
        'Contact',
        'Purchase',
    ];

    public static function isAllowedTrackEvent(string $eventName): bool
    {
        return in_array(trim($eventName), self::BROWSER_TRACK_EVENTS, true);
    }

    public static function isStandardEvent(string $eventName): bool
    {
        return in_array(trim($eventName), self::CAPI_TRACK_EVENTS, true);
    }

    public static function eventId(string $eventName, ?string $reference = null): string
    {
        $base = strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $eventName) ?: 'event');
        $suffix = $reference !== null && trim($reference) !== ''
            ? preg_replace('/[^A-Za-z0-9\-]+/', '-', trim($reference))
            : bin2hex(random_bytes(8));

        return trim($base . '-' . $suffix, '-');
    }

    /**
     * @param array<string, mixed> $customData
     * @param array<string, mixed> $userData
     * @return array{success: bool, event_id: string, sent: bool}
     */
    public static function trackStandardEvent(
        string $eventName,
        array $customData = [],
        ?string $eventRef = null,
        array $userData = [],
        bool $includeRequestUserData = true
    ): array {
        $eventName = trim($eventName);
        if (!self::isStandardEvent($eventName)) {
            return ['success' => false, 'event_id' => '', 'sent' => false];
        }

        $customData = self::sanitizeCustomData($customData);
        $eventRef = self::sanitizeEventReference($eventRef);

        $eventId = self::eventId($eventName, $eventRef);
        if ($includeRequestUserData) {
            $userData = array_filter(array_merge(self::storedMatchUserData(), $userData));
        }
        $sent = self::sendEvent($eventName, $customData, $userData, $eventId, null, $includeRequestUserData);

        return ['success' => true, 'event_id' => $eventId, 'sent' => $sent];
    }

    public static function sendPageView(?string $eventId = null): bool
    {
        return self::sendEvent('PageView', [], self::storedMatchUserData(), $eventId);
    }

    /**
     * @param array<string, mixed>|object $sale
     * @param array<string, mixed> $extraUserData
     */
    public static function sendPurchase(array|object $sale, ?string $eventId = null, array $extraUserData = []): bool
    {
        $sale = is_object($sale) ? get_object_vars($sale) : $sale;
        $idSale = (int)($sale['id_sale'] ?? 0);
        $codeSale = (string)($sale['code_sale'] ?? '');
        $customData = self::buildPurchaseCustomDataFromSale($sale);

        $userData = array_merge(
            self::userDataFromCustomer($sale),
            self::userDataFromStoredMeta($extraUserData)
        );

        return self::sendEvent(
            'Purchase',
            $customData,
            array_filter($userData),
            $eventId ?? self::purchaseEventId($idSale, $codeSale),
            null,
            false
        );
    }

    /**
     * Pago iniciado (checkout web con comprobante). Solo CAPI.
     *
     * @param array<string, mixed> $checkout
     */
    public static function sendInitiateCheckout(array $checkout, ?string $eventId = null): bool
    {
        $idTransfer = (int)($checkout['id_transfer'] ?? 0);
        $codeTransfer = trim((string)($checkout['code_transfer'] ?? ''));
        $customData = self::buildInitiateCheckoutCustomData($checkout);

        $userData = array_merge(
            self::userDataFromCustomer($checkout),
            self::userDataFromStoredMeta(self::buildCheckoutMetaContext($checkout))
        );

        return self::sendEvent(
            'InitiateCheckout',
            $customData,
            array_filter($userData),
            $eventId ?? self::initiateCheckoutEventId($idTransfer, $codeTransfer),
            null,
            false
        );
    }

    public static function initiateCheckoutEventId(int $idTransfer, string $codeTransfer = ''): string
    {
        $reference = $idTransfer > 0
            ? (string)$idTransfer
            : (trim($codeTransfer) !== '' ? trim($codeTransfer) : null);

        return self::eventId('InitiateCheckout', $reference);
    }

    /**
     * @param array<string, mixed> $checkout
     * @return array{currency: string, value: float, num_items: int}
     */
    public static function buildInitiateCheckoutCustomData(array $checkout): array
    {
        $value = (float)($checkout['value'] ?? $checkout['amount'] ?? $checkout['amount_transfer'] ?? 0);
        $numItems = (int)($checkout['num_items'] ?? $checkout['quantity'] ?? $checkout['quantity_transfer'] ?? 0);

        return [
            'currency' => 'COP',
            'value' => round($value, 2),
            'num_items' => max(1, $numItems),
        ];
    }

    public static function purchaseEventId(int $idSale, string $codeSale = ''): string
    {
        $reference = $idSale > 0
            ? (string)$idSale
            : (trim($codeSale) !== '' ? trim($codeSale) : null);

        return self::eventId('Purchase', $reference);
    }

    /**
     * @return array<string, mixed>
     */
    public static function buildPurchaseCustomData(string $orderId, float $value, int $quantity, int $idSale = 0): array
    {
        return self::buildPurchaseCustomDataFromSale([
            'code_sale' => $orderId,
            'total_sale' => $value,
            'quantity_sale' => $quantity,
            'id_sale' => $idSale,
        ]);
    }

    /**
     * Formato Meta estándar: solo currency + value.
     *
     * @param array<string, mixed> $sale
     * @return array{currency: string, value: float}
     */
    public static function buildPurchaseCustomDataFromSale(array $sale): array
    {
        $value = (float)($sale['total_sale'] ?? $sale['value'] ?? 0);

        return self::sanitizePurchaseCustomData([
            'currency' => 'COP',
            'value' => $value,
            'order_id' => (string)($sale['code_sale'] ?? $sale['order_id'] ?? ''),
        ]);
    }

    /**
     * @return array{currency: string, value: float, order_id?: string}
     */
    public static function sanitizePurchaseCustomData(array $customData): array
    {
        $currency = strtoupper(trim((string)($customData['currency'] ?? 'COP')));
        $clean = [
            'currency' => $currency !== '' ? $currency : 'COP',
            'value' => round((float)($customData['value'] ?? 0), 2),
        ];

        $orderId = strtoupper(preg_replace('/[^A-Za-z0-9\-]/', '', (string)($customData['order_id'] ?? '')) ?? '');
        if ($orderId !== '') {
            $clean['order_id'] = substr($orderId, 0, 64);
        }

        return $clean;
    }

    /**
     * Meta del checkout (IP, user-agent, cookies Meta) para CAPI Purchase posterior.
     *
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    public static function buildCheckoutMetaContext(array $input = []): array
    {
        $ip = self::normalizeClientIp($input['client_ip_address'] ?? null) ?? self::clientIp();
        $ua = trim((string)($input['client_user_agent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? '')));

        return array_filter([
            'fbp' => trim((string)($input['meta_fbp'] ?? $input['fbp'] ?? '')),
            'fbc' => trim((string)($input['meta_fbc'] ?? $input['fbc'] ?? '')),
            'client_ip_address' => $ip,
            'client_user_agent' => $ua !== '' ? $ua : null,
        ], static fn($value) => $value !== null && $value !== '');
    }

    /**
     * @param array<string, mixed> $backup
     * @return array<string, mixed>
     */
    public static function extractStoredMeta(array $row): array
    {
        foreach (['meta_transfer'] as $field) {
            $raw = (string)($row[$field] ?? '');
            if ($raw === '') {
                continue;
            }

            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                continue;
            }

            if (isset($decoded['meta']) && is_array($decoded['meta'])) {
                return $decoded['meta'];
            }

            if (isset($decoded['client_ip_address']) || isset($decoded['fbp'])) {
                return $decoded;
            }
        }

        return [];
    }

    /**
     * @param array<string, mixed> $meta
     * @return array<string, mixed>
     */
    public static function userDataFromStoredMeta(array $meta): array
    {
        return array_filter([
            'fbp' => trim((string)($meta['fbp'] ?? '')) ?: null,
            'fbc' => trim((string)($meta['fbc'] ?? '')) ?: null,
            'client_ip_address' => self::normalizeClientIp($meta['client_ip_address'] ?? null),
            'client_user_agent' => trim((string)($meta['client_user_agent'] ?? '')) ?: null,
        ], static fn($value) => $value !== null && $value !== '');
    }

    /**
     * @param array<string, mixed> $sale
     */
    public static function logPurchaseNotSent(array $sale, string $detail): void
    {
        self::logDebugResult('NO_ENVIADO', [
            'event_name' => 'Purchase',
            'event_id' => self::eventId('Purchase', (string)($sale['id_sale'] ?? $sale['code_sale'] ?? '')),
            'custom_data' => self::sanitizePurchaseCustomData([
                'currency' => 'COP',
                'value' => (float)($sale['total_sale'] ?? $sale['value'] ?? 0),
            ]),
        ], $detail);
    }

    /**
     * Sanitiza custom_data para Meta CAPI / Pixel.
     * Solo permite campos estándar de e-commerce seguros (currency, value, num_items, content_type, content_ids).
     * Elimina estrictamente cualquier texto libre (nombres, descripciones, términos de rifas/apuestas).
     *
     * @param array<string, mixed> $customData
     * @return array<string, mixed>
     */
    public static function sanitizeCustomData(array $customData): array
    {
        $currency = strtoupper(trim((string)($customData['currency'] ?? 'COP')));
        $clean = [
            'currency' => $currency !== '' ? $currency : 'COP',
        ];

        if (isset($customData['value']) && is_numeric($customData['value'])) {
            $clean['value'] = round((float)$customData['value'], 2);
        }

        if (isset($customData['num_items']) && is_numeric($customData['num_items'])) {
            $clean['num_items'] = max(1, (int)$customData['num_items']);
        }

        if (isset($customData['content_type']) && is_string($customData['content_type'])) {
            $clean['content_type'] = 'product';
        }

        if (isset($customData['content_ids']) && is_array($customData['content_ids'])) {
            $ids = [];
            foreach ($customData['content_ids'] as $id) {
                $itemClean = preg_replace('/[^0-9a-zA-Z_\-]/', '', (string)$id) ?? '';
                if ($itemClean !== '' && !self::containsBlockedMarketingTerms($itemClean)) {
                    $ids[] = $itemClean;
                }
            }
            if ($ids !== []) {
                $clean['content_ids'] = array_values(array_unique($ids));
            }
        }

        return $clean;
    }

    public static function sanitizeEventReference(?string $reference): ?string
    {
        if ($reference === null) {
            return null;
        }

        $reference = strtolower(trim($reference));
        if ($reference === '') {
            return null;
        }

        if (self::containsBlockedMarketingTerms($reference)) {
            $reference = (string)preg_replace(self::blockedTermsPattern(), '', $reference);
        }

        $reference = preg_replace('/[^a-z0-9\-]+/', '-', $reference) ?? '';
        $reference = preg_replace('/-+/', '-', $reference) ?? '';
        $reference = trim($reference, '-');

        return $reference !== '' ? $reference : null;
    }

    private static function blockedTermsPattern(): string
    {
        return '/sticker|stickers|rifa|rifas|raffle|raffles|sorteo|sorteos|loteria|loter[ií]a|lottery|'
            . 'ticket|tickets|suerte|boleta|boletas|paquete|paquetes|premio|premios|bingo|apuesta|'
            . 'ganador|ganadores|n[uú]mero|numeros|transferencia|compra[\s\-]?web/iu';
    }

    private static function containsBlockedMarketingTerms(string $value): bool
    {
        return (bool)preg_match(self::blockedTermsPattern(), $value);
    }

    /**
     * @param array<string, mixed> $customData
     * @param array<string, mixed> $userData
     */
    public static function sendEvent(
        string $eventName,
        array $customData = [],
        array $userData = [],
        ?string $eventId = null,
        ?int $eventTime = null,
        bool $includeRequestUserData = true
    ): bool {
        $customData = $eventName === 'Purchase'
            ? self::sanitizePurchaseCustomData($customData)
            : self::sanitizeCustomData($customData);
        if (!self::isEnabled()) {
            self::logDebugResult('NO_ENVIADO', [
                'event_name' => $eventName,
                'event_id' => $eventId ?? '',
                'custom_data' => $customData,
            ], 'META_CAPI_ENABLED=false');
            return false;
        }

        $pixelId = self::pixelId();
        $accessToken = self::accessToken();
        if ($pixelId === '' || $accessToken === '') {
            $missingCreds = [
                'event_name' => $eventName,
                'event_id' => $eventId ?? '',
                'custom_data' => $customData,
            ];
            if ($eventName === 'Purchase') {
                self::logResult('NO_ENVIADO', $missingCreds, 'Falta META_PIXEL_ID o META_ACCESS_TOKEN');
            } else {
                self::logDebugResult('NO_ENVIADO', $missingCreds, 'Falta META_PIXEL_ID o META_ACCESS_TOKEN');
            }
            return false;
        }

        $event = [
            'event_name' => $eventName,
            'event_time' => $eventTime ?? time(),
            'action_source' => 'website',
            'user_data' => self::sanitizeUserDataIdentifiers(array_filter(array_merge(
                $includeRequestUserData ? self::requestUserData() : [],
                $userData
            ))),
        ];

        // Meta exige event_source_url en todos los eventos con action_source=website
        // (incluye Purchase). Sin esto, atribución y optimización de campañas fallan.
        $sourceUrl = self::eventSourceUrl();
        if ($sourceUrl !== '') {
            $event['event_source_url'] = $sourceUrl;
        }

        if ($eventId !== null && trim($eventId) !== '') {
            $event['event_id'] = trim($eventId);
        }

        if ($customData !== []) {
            $event['custom_data'] = $customData;
        }

        $payload = ['data' => [$event]];
        $testEventCode = self::testEventCode();
        if ($testEventCode !== '') {
            $payload['test_event_code'] = $testEventCode;
        }

        return self::post($pixelId, $accessToken, $payload, $event);
    }

    public static function pixelId(): string
    {
        return defined('META_PIXEL_ID') ? trim((string)\META_PIXEL_ID) : '';
    }

    public static function isPixelConfigured(): bool
    {
        return self::pixelId() !== '';
    }

    public static function isCapiConfigured(): bool
    {
        return self::isPixelConfigured() && self::accessToken() !== '';
    }

    /** Credenciales presentes y CAPI activada (listo para enviar). */
    public static function isCapiReady(): bool
    {
        return self::isEnabled() && self::isCapiConfigured();
    }

    public static function isConfigured(): bool
    {
        return self::isCapiReady();
    }

    public static function isEnabled(): bool
    {
        return !defined('META_CAPI_ENABLED') || (bool)\META_CAPI_ENABLED;
    }

    private static function accessToken(): string
    {
        return defined('META_ACCESS_TOKEN') ? trim((string)\META_ACCESS_TOKEN) : '';
    }

    private static function apiVersion(): string
    {
        $version = defined('META_API_VERSION') ? trim((string)\META_API_VERSION) : self::DEFAULT_API_VERSION;

        return $version !== '' ? $version : self::DEFAULT_API_VERSION;
    }

    private static function testEventCode(): string
    {
        return defined('META_TEST_EVENT_CODE') ? trim((string)\META_TEST_EVENT_CODE) : '';
    }

    private static function isDebugEnabled(): bool
    {
        return defined('META_CAPI_DEBUG') && (bool)\META_CAPI_DEBUG;
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $event
     */
    private static function post(string $pixelId, string $accessToken, array $payload, array $event): bool
    {
        if (!function_exists('curl_init')) {
            self::logResult('NO_ENVIADO', $event, 'cURL no está disponible para enviar eventos a Meta.');
            return false;
        }

        $url = 'https://graph.facebook.com/' . rawurlencode(self::apiVersion())
            . '/' . rawurlencode($pixelId) . '/events?access_token=' . rawurlencode($accessToken);

        $ch = curl_init($url);
        if ($ch === false) {
            self::logResult('NO_ENVIADO', $event, 'No se pudo inicializar cURL.');
            return false;
        }

        curl_setopt_array($ch, [
            \CURLOPT_RETURNTRANSFER => true,
            \CURLOPT_POST => true,
            \CURLOPT_POSTFIELDS => json_encode($payload, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES),
            \CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            \CURLOPT_CONNECTTIMEOUT => self::connectTimeout(),
            \CURLOPT_TIMEOUT => self::requestTimeout(),
        ]);

        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, \CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        if ($response === false || $httpCode < 200 || $httpCode >= 300) {
            self::logResult('NO_ENVIADO', $event, 'HTTP ' . $httpCode . ': ' . ($error !== '' ? $error : (string)$response));
            return false;
        }

        if (in_array($event['event_name'] ?? '', ['Purchase', 'PageView', 'InitiateCheckout', 'Lead', 'AddToCart', 'ViewContent', 'AddPaymentInfo', 'Contact'], true)) {
            self::logResult('ENVIADO', $event, self::metaResponseDetail($httpCode, (string)$response));
        } else {
            self::logDebugResult('ENVIADO', $event, self::metaResponseDetail($httpCode, (string)$response));
        }

        return true;
    }

    private static function metaResponseDetail(int $httpCode, string $response): string
    {
        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            return 'HTTP ' . $httpCode . ' response=' . mb_substr($response, 0, 500);
        }

        $messages = $decoded['messages'] ?? [];
        if (is_array($messages)) {
            $messages = json_encode($messages, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
        }

        return sprintf(
            'HTTP %d events_received=%s fbtrace_id=%s messages=%s',
            $httpCode,
            isset($decoded['events_received']) ? (string)$decoded['events_received'] : '',
            (string)($decoded['fbtrace_id'] ?? ''),
            is_string($messages) ? $messages : ''
        );
    }

    /**
     * @param array<string, mixed> $event
     */
    private static function logDebugResult(string $status, array $event, string $detail): void
    {
        if (self::isDebugEnabled()) {
            self::logResult($status, $event, $detail);
        }
    }

    /**
     * @param array<string, mixed> $event
     */
    private static function logResult(string $status, array $event, string $detail): void
    {
        $customData = is_array($event['custom_data'] ?? null) ? $event['custom_data'] : [];

        self::log(sprintf(
            'Meta CAPI %s: %s event_id=%s value=%s detail=%s',
            $status,
            (string)($event['event_name'] ?? ''),
            (string)($event['event_id'] ?? ''),
            isset($customData['value']) ? (string)$customData['value'] : '',
            $detail
        ));
    }

    /**
     * @param array<string, mixed> $sale
     * @return array<string, mixed>
     */
    public static function userDataFromCustomer(array $sale): array
    {
        $firstName = self::firstName((string)($sale['name_customer'] ?? ''));
        $lastName = (string)($sale['lastname_customer'] ?? '');

        return array_filter([
            'em' => self::hash((string)($sale['email_customer'] ?? '')),
            'ph' => self::hashPhone((string)($sale['phone_customer'] ?? '')),
            'fn' => self::hash($firstName),
            'ln' => self::hash($lastName),
            'country' => self::hash('co'),
            'external_id' => self::hash((string)($sale['id_customer_sale'] ?? $sale['id_customer'] ?? '')),
        ]);
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public static function userDataFromInput(array $input): array
    {
        $email = (string)($input['email'] ?? $input['email_customer'] ?? $input['correo'] ?? '');
        $phone = (string)($input['phone'] ?? $input['phone_customer'] ?? $input['celular'] ?? '');
        $name = (string)($input['name'] ?? $input['name_customer'] ?? $input['nombre'] ?? '');
        $lastname = (string)($input['lastname'] ?? $input['lastname_customer'] ?? $input['apellido'] ?? '');
        $city = (string)($input['city'] ?? $input['city_customer'] ?? $input['ciudad'] ?? '');
        $state = (string)($input['state'] ?? $input['department_customer'] ?? $input['departamento'] ?? '');

        return array_filter([
            'em' => self::hash($email),
            'ph' => self::hashPhone($phone),
            'fn' => self::hash(self::firstName($name)),
            'ln' => self::hash($lastname),
            'ct' => self::hash(strtolower(trim($city))),
            'st' => self::hash(strtolower(trim($state))),
            'country' => self::hash('co'),
        ]);
    }

    /**
     * Guarda identificadores ya hasheados (em, ph, fn…) para PageView posteriores.
     * No guarda texto plano. No se llama desde aprobaciones de admin (Purchase).
     *
     * @param array<string, mixed> $userData
     */
    public static function rememberMatchUserData(array $userData): void
    {
        $clean = self::normalizeStoredMatchUserData($userData);
        if ($clean === [] || (!isset($clean['em']) && !isset($clean['ph']))) {
            return;
        }

        $merged = array_merge(self::storedMatchUserData(), $clean);

        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION[self::SESSION_MATCH_KEY] = $merged;
        }

        if (!headers_sent()) {
            setcookie(self::MATCH_COOKIE, json_encode($merged, JSON_UNESCAPED_SLASHES), [
                'expires' => time() + self::MATCH_COOKIE_TTL,
                'path' => '/',
                'secure' => self::isHttpsRequest(),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
    }

    /**
     * Identificadores hasheados de visitas anteriores (sesión o cookie HttpOnly).
     *
     * @return array<string, string>
     */
    public static function storedMatchUserData(): array
    {
        $fromCookie = [];
        $raw = trim((string)($_COOKIE[self::MATCH_COOKIE] ?? ''));
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $fromCookie = $decoded;
            }
        }

        $fromSession = [];
        if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION[self::SESSION_MATCH_KEY]) && is_array($_SESSION[self::SESSION_MATCH_KEY])) {
            $fromSession = $_SESSION[self::SESSION_MATCH_KEY];
        }

        return self::normalizeStoredMatchUserData(array_merge($fromCookie, $fromSession));
    }

    /**
     * @param array<string, mixed> $userData
     * @return array<string, string>
     */
    private static function normalizeStoredMatchUserData(array $userData): array
    {
        $clean = [];
        foreach (self::MATCH_USER_DATA_KEYS as $key) {
            $value = strtolower(trim((string)($userData[$key] ?? '')));
            if ($value !== '' && preg_match('/^[a-f0-9]{64}$/', $value) === 1) {
                $clean[$key] = $value;
            }
        }

        return $clean;
    }

    private static function isHttpsRequest(): bool
    {
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            return true;
        }

        return strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    }

    /**
     * @return array<string, mixed>
     */
    private static function requestUserData(): array
    {
        return array_filter([
            'client_ip_address' => self::clientIp(),
            'client_user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'fbp' => $_COOKIE['_fbp'] ?? null,
            'fbc' => $_COOKIE['_fbc'] ?? null,
        ]);
    }

    /**
     * @param array<string, mixed> $userData
     * @return array<string, mixed>
     */
    private static function sanitizeUserDataIdentifiers(array $userData): array
    {
        if (isset($userData['client_ip_address'])) {
            $ip = self::normalizeClientIp($userData['client_ip_address']);
            if ($ip === null) {
                unset($userData['client_ip_address']);
            } else {
                $userData['client_ip_address'] = $ip;
            }
        }

        if (isset($userData['fbc'])) {
            $fbc = trim((string)$userData['fbc']);
            if (!self::isValidFbc($fbc)) {
                unset($userData['fbc']);
            } else {
                $userData['fbc'] = $fbc;
            }
        }

        return $userData;
    }

    private static function isValidFbc(string $fbc): bool
    {
        if ($fbc === '') {
            return false;
        }

        if (!preg_match('/^fb\.1\.(\d{10,13})\.[A-Za-z0-9_-]+$/', $fbc, $matches)) {
            return false;
        }

        $rawTimestamp = (int)($matches[1] ?? 0);
        if ($rawTimestamp <= 0) {
            return false;
        }

        // Accept both seconds and milliseconds unix timestamps.
        $timestamp = strlen((string)$rawTimestamp) === 13
            ? (int)floor($rawTimestamp / 1000)
            : $rawTimestamp;

        $now = time();
        if ($timestamp > $now + 86400) {
            return false;
        }

        return ($now - $timestamp) <= self::FBC_MAX_AGE_SECONDS;
    }

    private static function clientIp(): ?string
    {
        return self::normalizeClientIp(null);
    }

    private static function normalizeClientIp(mixed $value): ?string
    {
        $candidates = [];
        if ($value !== null && trim((string)$value) !== '') {
            $candidates[] = (string)$value;
        }
        $candidates[] = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '';
        $candidates[] = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
        $candidates[] = $_SERVER['REMOTE_ADDR'] ?? '';

        foreach ($candidates as $candidate) {
            $ip = trim(explode(',', (string)$candidate)[0]);
            if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }

        return null;
    }

    private static function connectTimeout(): int
    {
        return defined('META_CAPI_CONNECT_TIMEOUT') ? (int)\META_CAPI_CONNECT_TIMEOUT : 5;
    }

    private static function requestTimeout(): int
    {
        return defined('META_CAPI_TIMEOUT') ? (int)\META_CAPI_TIMEOUT : 8;
    }

    /**
     * URL pública donde ocurrió el evento (requerida por Meta CAPI).
     * En aprobaciones desde admin/AJAX usa la URL del sitio, no la del panel.
     */
    private static function eventSourceUrl(): string
    {
        $base = defined('BASE_URL') ? rtrim((string)\BASE_URL, '/') : '';
        $uri = (string)($_SERVER['REQUEST_URI'] ?? '');
        $host = (string)($_SERVER['HTTP_HOST'] ?? '');

        $isBackendRequest = $uri !== '' && (bool)preg_match(
            '#/(front/|ajax/|controllers/|api/)|dash\.php#i',
            $uri
        );

        if (!$isBackendRequest && $host !== '') {
            $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
            $forwardedProto = strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
            $scheme = ($https || $forwardedProto === 'https') ? 'https' : 'http';
            if ($base !== '') {
                $baseScheme = parse_url($base, \PHP_URL_SCHEME);
                if (is_string($baseScheme) && $baseScheme !== '') {
                    $scheme = $baseScheme;
                }
            }

            return $scheme . '://' . $host . ($uri !== '' ? $uri : '/');
        }

        return $base !== '' ? $base . '/' : '';
    }

    private static function firstName(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $parts = preg_split('/\s+/', $value) ?: [];

        return (string)($parts[0] ?? $value);
    }

    private static function hash(string $value): ?string
    {
        $value = strtolower(trim($value));
        if ($value === '') {
            return null;
        }

        return hash('sha256', $value);
    }

    private static function hashPhone(string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';
        if (strlen($digits) === 10) {
            $digits = '57' . $digits;
        }

        return $digits !== '' ? hash('sha256', $digits) : null;
    }

    private static function log(string $message): void
    {
        writeAppLog('meta-capi.log', $message);
    }
}
