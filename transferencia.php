<?php
/**
 * Estado de transferencia — confirmación y comprobante.
 * Actualización de estado solo manual (sin polling).
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/controllers/transfersController.php';
require_once __DIR__ . '/controllers/ventas.controller.php';
require_once __DIR__ . '/includes/meta-pixel.php';

function transferenciaSuccessIconSvg(): string
{
    return '<svg class="accent" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6L9 17l-5-5"/></svg>';
}

function transferenciaComunidadHtml(): string
{
    $url = 'https://chat.whatsapp.com/DR1qJUQaype5hwbIe6EZdM?s=sh&p=i&mlu=4&ilr=4&iam=2';

    return '<div class="cr-comunidad">'
        . '<a class="cr-comunidad__btn" href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener noreferrer">'
        . '<span class="cr-comunidad__icon" aria-hidden="true"><i class="ti ti-brand-whatsapp"></i></span>'
        . '<span class="cr-comunidad__copy">'
        . '<strong>Únete a nuestra comunidad</strong>'
        . '<small>Únete a nuestras dinámicas diarias</small>'
        . '</span></a></div>';
}

function transferenciaAntifraudNoticeHtml(): string
{
    return '<div class="antifraud-notice" role="note">'
        . '<strong>Tu compra está sujeta a verificaciones.</strong> '
        . 'Si nuestro sistema antifraude detecta alguna inconsistencia, tu venta será anulada de inmediato y <strong>no tendrá validez</strong>.'
        . '</div>';
}

/** Teléfono WhatsApp solo dígitos (57 + celular). Vacío si no hay setting. */
function transferenciaWhatsappPhone(array $settings): string
{
    $digits = preg_replace('/\D/', '', (string)($settings['whatsapp'] ?? ''));

    if ($digits === '') {
        $chatUrl = (string)($settings['whatsapp_chat_url'] ?? '');
        if (preg_match('/(?:wa\.me\/|phone=)(\d{10,15})/i', $chatUrl, $m)) {
            $digits = $m[1];
        }
    }

    if ($digits === '') {
        return '';
    }

    if (strlen($digits) === 10) {
        return '57' . $digits;
    }

    return $digits;
}

/** @return array{status: string, message: string, html_recibo: string, error_code: string} */
function transferenciaBuildStatusResponse(string $code): array
{
    $code = trim($code);
    if ($code === '') {
        return [
            'status' => 'error',
            'message' => 'Enlace no válido.',
            'html_recibo' => '',
            'error_code' => 'invalid',
        ];
    }

    $transfer = TransfersController::obtenerPorCode($code);
    if (!$transfer) {
        return [
            'status' => 'error',
            'message' => 'Código no válido o expirado.',
            'html_recibo' => '',
            'error_code' => 'not_found',
        ];
    }

    $st = (int)($transfer['status_transfer'] ?? 0);

    if ($st === 3) {
        return [
            'status' => 'error',
            'message' => 'No pudimos validar tu comprobante. Escríbenos por WhatsApp con tu código de compra.',
            'html_recibo' => '',
            'error_code' => 'rejected',
        ];
    }

    if ($st === 2) {
        $sale = Db::fetchOne(
            'SELECT id_sale, code_sale, total_sale, quantity_sale FROM sales WHERE code_sale = :c ORDER BY id_sale DESC LIMIT 1',
            [':c' => $code]
        );
        if ($sale) {
            $detalle = VentasController::obtenerDetalleVenta((int)$sale->id_sale);
            if (!empty($detalle['success']) && !empty($detalle['html_recibo'])) {
                return [
                    'status' => 'ok',
                    'message' => 'Pago confirmado.',
                    'html_recibo' => (string)$detalle['html_recibo'],
                    'error_code' => '',
                ];
            }
        }

        return [
            'status' => 'pending',
            'message' => 'Preparando comprobante…',
            'html_recibo' => '',
            'error_code' => '',
        ];
    }

    return [
        'status' => 'pending',
        'message' => '',
        'html_recibo' => '',
        'error_code' => '',
    ];
}

$settings = TransfersController::obtenerSettings();
$whatsappPhone = transferenciaWhatsappPhone($settings);
$code = trim((string)($_GET['code'] ?? ''));
$fromCheckout = isset($_GET['from_checkout']) && (string)$_GET['from_checkout'] === '1';
$response = transferenciaBuildStatusResponse($code);
$status = $response['status'];
$htmlRecibo = $response['html_recibo'];
$statusMessage = $response['message'];
$errorCode = $response['error_code'];
$showRecibo = $status === 'ok' && $htmlRecibo !== '' && !$fromCheckout;
$isError = $status === 'error';
$isPending = !$showRecibo && !$isError;

$errorTitles = [
    'rejected' => 'Pago no validado',
    'not_found' => 'Código no encontrado',
    'invalid' => 'Enlace no válido',
    'generic' => 'No pudimos consultar',
];
$errorTitle = $errorTitles[$errorCode] ?? $errorTitles['generic'];

$siteName = defined('SITE_NAME') && SITE_NAME ? (string)SITE_NAME : 'Dinámicas La Milagrosa';
$homeUrl = defined('BASE_URL') && BASE_URL ? rtrim((string)BASE_URL, '/') : '/';

$pageTitle = $showRecibo
    ? 'Pago confirmado — ' . $siteName
    : ($isError ? $errorTitle . ' — ' . $siteName : 'Comprobante en revisión — ' . $siteName);

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#f2b500">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="icon" type="image/png" href="<?= cr_site_favicon_href() ?>" data-site-favicon>
    <link rel="shortcut icon" type="image/png" href="<?= cr_site_favicon_href() ?>" data-site-favicon>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css" crossorigin="anonymous">
    <link rel="stylesheet" href="<?= htmlspecialchars(ASSETS_URL . '/css/app.css', ENT_QUOTES, 'UTF-8') ?>?v=135">
    <style>
        :root {
            --cr-gold: #f2b500;
            --cr-gold-dark: #C99700;
            --cr-gold-muted: #fff3d6;
            --cr-gold-rgb: 242, 181, 0;
            --cr-dark: #0D0D0D;
            --cr-black: #000000;
            --cr-text: #333333;
            --cr-text-muted: #666666;
            --cr-body-bg: #F2F2F2;
            --cr-surface: #ffffff;
            --cr-border: #e6e6e6;
            --cr-accent-green: #39cb7f;
            --cr-accent-green-soft: #e8f9f0;
            --cr-accent-red: #dc3545;
            --cr-accent-red-soft: #fdecee;
            --cr-radius-md: 14px;
            --cr-radius-lg: 20px;
            --cr-font: 'Montserrat', system-ui, sans-serif;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html { background: var(--cr-body-bg); }
        body.transferencia-page {
            font-family: var(--cr-font);
            min-height: 100vh;
            min-height: 100dvh;
            background: var(--cr-body-bg);
            color: var(--cr-text);
            display: flex;
            flex-direction: column;
            align-items: stretch;
            padding: 0 0 calc(1.5rem + env(safe-area-inset-bottom, 0));
        }
        .transferencia-main {
            width: 100%;
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 1.25rem 1rem 0;
        }
        .shell { width: 100%; max-width: 440px; }
        .shell--wide { max-width: min(680px, 100%); }
        .card {
            background: var(--cr-surface);
            border-radius: var(--cr-radius-lg);
            border: 1px solid var(--cr-border);
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.06);
            overflow: hidden;
        }
        .card-body { padding: 1.75rem 1.25rem 1.5rem; }
        @media (min-width: 480px) { .card-body { padding: 2rem 1.75rem 1.75rem; } }
        .icon-wrap {
            width: 56px; height: 56px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 1.25rem;
            background: var(--cr-gold-muted);
            border: 2px solid var(--cr-gold);
        }
        .icon-wrap.success { background: var(--cr-accent-green-soft); border-color: var(--cr-accent-green); }
        .icon-wrap.error { background: var(--cr-accent-red-soft); border-color: var(--cr-accent-red); }
        .icon-wrap.warning { background: var(--cr-gold-muted); border-color: var(--cr-gold); }
        .icon-wrap svg { width: 28px; height: 28px; }
        .icon-wrap .accent { stroke: var(--cr-gold-dark); fill: none; }
        .icon-wrap.success .accent { stroke: var(--cr-accent-green); }
        .icon-wrap.error .accent { stroke: var(--cr-accent-red); }
        .icon-wrap.warning .accent { stroke: var(--cr-gold-dark); }
        h1 { font-size: 1.15rem; font-weight: 700; text-align: center; line-height: 1.4; margin-bottom: 0.65rem; color: var(--cr-dark); }
        .subtitle { text-align: center; color: var(--cr-text-muted); font-size: 0.95rem; line-height: 1.6; margin-bottom: 1.25rem; }
        .progress-block { margin-bottom: 1rem; }
        .txn-flow { position: relative; padding: 0.25rem 0.15rem 0; }
        .txn-rail {
            position: absolute; top: 19px;
            left: calc(16.66% + 14px); right: calc(16.66% + 14px);
            height: 5px;
            background: linear-gradient(90deg, #ececec, #e0e0e0, #ececec);
            border-radius: 999px; overflow: hidden;
            box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.06);
        }
        .txn-rail__fill {
            position: relative; height: 100%; width: 12%;
            border-radius: 999px;
            background: linear-gradient(90deg, #2aad62, var(--cr-accent-green), #6ee09a, #b8f5d4, var(--cr-accent-green));
            background-size: 220% 100%;
            animation: greenStream 2.2s linear infinite;
            transition: width 1.85s cubic-bezier(0.22, 1, 0.36, 1);
            box-shadow: 0 0 10px rgba(57, 203, 127, 0.55), 0 0 3px rgba(57, 203, 127, 0.35);
        }
        .txn-rail__fill.is-active::after {
            content: ''; position: absolute; top: 0; right: 0; width: 28px; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.7));
            animation: headGlow 1.4s ease-in-out infinite;
        }
        @keyframes greenStream { 0% { background-position: 100% 0; } 100% { background-position: -100% 0; } }
        @keyframes headGlow { 0%, 100% { opacity: 0.4; } 50% { opacity: 1; } }
        .steps { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.25rem; position: relative; z-index: 1; }
        .step { text-align: center; padding: 0 0.1rem; }
        .step-dot {
            width: 38px; height: 38px; margin: 0 auto 0.55rem; border-radius: 50%;
            background: var(--cr-surface); border: 2px solid var(--cr-border);
            display: flex; align-items: center; justify-content: center;
            transition: border-color 0.45s ease, background 0.45s ease, box-shadow 0.45s ease, transform 0.45s ease;
        }
        .step-dot svg { width: 17px; height: 17px; stroke: #bbb; fill: none; }
        .step-text { font-size: 0.7rem; color: #aaa; font-weight: 500; line-height: 1.4; }
        .step.active .step-dot {
            border-color: var(--cr-gold);
            background: linear-gradient(145deg, var(--cr-gold-muted), #ffffff);
            box-shadow: 0 0 0 4px rgba(var(--cr-gold-rgb), 0.22);
            transform: scale(1.06);
            animation: stepPulse 2s ease-in-out infinite;
        }
        .step.active .step-dot svg { stroke: var(--cr-gold-dark); }
        .step.active .step-text { color: var(--cr-gold-dark); font-weight: 700; }
        .step.done .step-dot { border-color: var(--cr-accent-green); background: var(--cr-accent-green); }
        .step.done .step-dot svg { stroke: #fff; }
        .step.done .step-text { color: var(--cr-accent-green); font-weight: 600; }
        @keyframes stepPulse {
            0%, 100% { box-shadow: 0 0 0 4px rgba(var(--cr-gold-rgb), 0.18); }
            50% { box-shadow: 0 0 0 7px rgba(var(--cr-gold-rgb), 0.12); }
        }
        .order-ref {
            text-align: center; font-size: 0.85rem; color: var(--cr-text-muted);
            margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--cr-border);
        }
        .order-ref code {
            display: inline-block; margin-top: 0.35rem; font-size: 0.8rem; font-weight: 600;
            background: var(--cr-gold-muted); padding: 0.35rem 0.65rem; border-radius: 8px;
            color: var(--cr-dark); border: 1px solid rgba(var(--cr-gold-rgb), 0.35);
            word-break: break-all;
        }
        .btn-row { display: flex; flex-direction: column; gap: 0.65rem; margin-top: 1.15rem; }
        .btn-gold {
            display: block; width: 100%; padding: 0.85rem 1rem; text-align: center;
            text-decoration: none; font-weight: 600; font-size: 0.9rem;
            color: var(--cr-dark); background: var(--cr-gold); border: none;
            border-radius: var(--cr-radius-md); cursor: pointer; transition: background 0.2s;
        }
        .btn-gold:hover { background: var(--cr-gold-dark); color: var(--cr-dark); }
        .btn-outline {
            display: block; width: 100%; padding: 0.85rem 1rem; text-align: center;
            text-decoration: none; font-weight: 600; font-size: 0.9rem;
            color: var(--cr-dark); background: transparent;
            border: 2px solid var(--cr-border); border-radius: var(--cr-radius-md);
            cursor: pointer; transition: border-color 0.2s, background 0.2s;
        }
        .btn-outline:hover { border-color: var(--cr-gold-dark); background: var(--cr-gold-muted); }
        .btn-whatsapp {
            display: flex; width: 100%; padding: 0.85rem 1rem; text-align: center;
            align-items: center; justify-content: center; gap: 0.45rem;
            text-decoration: none; font-weight: 600; font-size: 0.9rem; color: #fff !important;
            background: linear-gradient(45deg, #25D366, #128C7E);
            border: none; border-radius: var(--cr-radius-md); cursor: pointer;
        }
        .btn-whatsapp:hover { filter: brightness(1.05); color: #fff !important; }
        .btn-whatsapp .ti { font-size: 1.2rem; line-height: 1; }
        .recibo-wrap { margin-top: 1rem; animation: fadeUp 0.5s ease; }
        @keyframes fadeUp { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: translateY(0); } }
        .recibo-box {
            background: var(--cr-surface); border: 1px solid var(--cr-border);
            border-radius: var(--cr-radius-md); padding: 0.75rem; overflow-x: auto;
        }
        .recibo-box img { max-width: 100%; height: auto; display: block; }
        .recibo-box .cr-recibo-numeros-grid {
            margin-left: auto !important;
            margin-right: auto !important;
            border-collapse: separate;
            border-spacing: 6px;
        }
        .recibo-box table.cr-numero-chip--recibo {
            display: table !important;
            width: 72px !important;
            min-width: 72px !important;
            max-width: 72px !important;
            height: 36px !important;
            margin: 0 !important;
            padding: 0 !important;
            letter-spacing: 0 !important;
            border-collapse: collapse !important;
            table-layout: fixed !important;
            border: none !important;
            background: transparent !important;
        }
        .recibo-box table.cr-numero-chip--recibo td {
            display: block !important;
            width: 100% !important;
            height: 32px !important;
            padding: 0 6px !important;
            text-align: center !important;
            vertical-align: middle !important;
            font-family: Consolas, Monaco, "Courier New", monospace !important;
            font-size: 14px !important;
            font-weight: 700 !important;
            line-height: 32px !important;
            letter-spacing: 0 !important;
            box-sizing: border-box !important;
        }
        .recibo-box .cr-recibo-num {
            display: inline-block !important;
            font-family: Montserrat, Arial, Helvetica, sans-serif !important;
            font-weight: 700 !important;
            font-size: 0.875rem !important;
            line-height: 1.2 !important;
            letter-spacing: 0.02em !important;
            padding: 0.35rem 0.6rem !important;
            min-width: 2.75rem !important;
            margin: 0 !important;
            border-radius: 10px !important;
            box-sizing: border-box !important;
            white-space: nowrap !important;
            box-shadow: 0 1px 3px rgba(13, 13, 13, 0.06) !important;
        }
        .recibo-box .cr-recibo-num--libre {
            background-color: #ffffff !important;
            color: #0D0D0D !important;
            border: 2px solid #C99700 !important;
        }
        .recibo-box .cr-recibo-num--premiado {
            background-color: #f2b500 !important;
            color: #0D0D0D !important;
            border: 2px solid #C99700 !important;
        }
        .footer-note { text-align: center; font-size: 0.85rem; color: var(--cr-text-muted); margin-top: 1rem; line-height: 1.55; }
        .antifraud-notice {
            margin-top: 1rem;
            padding: 0.85rem 1rem;
            font-size: 0.85rem;
            line-height: 1.55;
            color: #7a5a00;
            background: #fff8e6;
            border: 1px solid rgba(var(--cr-gold-rgb), 0.45);
            border-radius: var(--cr-radius-md);
            text-align: center;
        }
        .antifraud-notice strong { color: #5c4300; }
        .wait-hint { text-align: center; font-size: 0.82rem; color: var(--cr-text-muted); margin-top: 0.85rem; line-height: 1.5; }
        .secure-badge {
            display: flex; align-items: center; justify-content: center; gap: 0.4rem;
            margin-top: auto; padding-top: 1.25rem; font-size: 0.75rem; color: var(--cr-text-muted);
        }
        .hidden { display: none !important; }
    </style>
    <?php cs_meta_pixel_head(); ?>
</head>
<body class="landing transferencia-page">
<?php include __DIR__ . '/includes/landing/navbar.php'; ?>

    <main class="transferencia-main">
    <div class="shell <?= $showRecibo ? 'shell--wide' : '' ?>" id="appShell">
        <?php if ($isError): ?>
        <div class="card">
            <div class="card-body">
                <div class="icon-wrap error">
                    <svg class="accent" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                </div>
                <h1><?= htmlspecialchars($errorTitle, ENT_QUOTES, 'UTF-8') ?></h1>
                <p class="subtitle"><?= htmlspecialchars($statusMessage, ENT_QUOTES, 'UTF-8') ?></p>
                <?php if ($code !== ''): ?>
                <div class="order-ref">Tu referencia<br><code><?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?></code></div>
                <?php endif; ?>
                <div class="btn-row">
                    <?php if ($errorCode === 'rejected' && $whatsappPhone !== ''): ?>
                    <button type="button" class="btn-whatsapp" onclick="enviarSoporte()">WhatsApp</button>
                    <?php endif; ?>
                    <a class="btn-gold" href="<?= htmlspecialchars($homeUrl, ENT_QUOTES, 'UTF-8') ?>">Volver al sitio</a>
                </div>
            </div>
        </div>

        <?php elseif ($showRecibo): ?>
        <div class="card">
            <div class="card-body">
                <div class="icon-wrap success"><?= transferenciaSuccessIconSvg() ?></div>
                <h1>¡Pago confirmado!</h1>
                <p class="subtitle">Aquí está tu comprobante.</p>
            </div>
        </div>
        <div class="recibo-wrap">
            <div class="recibo-box"><?= $htmlRecibo ?></div>
            <?= transferenciaAntifraudNoticeHtml() ?>
            <p class="footer-note">Guarda captura. También llegó a tu correo.</p>
            <div class="btn-row">
                <a class="btn-gold" href="<?= htmlspecialchars($homeUrl, ENT_QUOTES, 'UTF-8') ?>">Volver al sitio</a>
            </div>
        </div>

        <?php else: ?>
        <div class="card" id="stateCard">
            <div class="card-body">
                <div class="icon-wrap warning" aria-hidden="true">
                    <svg class="accent" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                </div>
                <h1>Comprobante recibido</h1>
                <p class="subtitle">Estamos revisando tu pago. Cuando lo aprueben, pulsa <strong>Actualizar estado</strong> para ver tus números.</p>

                <div class="progress-block">
                    <div class="txn-flow">
                        <div class="txn-rail" aria-hidden="true">
                            <div class="txn-rail__fill" style="width:50%"></div>
                        </div>
                        <div class="steps">
                            <div class="step done">
                                <div class="step-dot">
                                    <svg viewBox="0 0 24 24" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
                                </div>
                                <p class="step-text">Comprobante<br>recibido</p>
                            </div>
                            <div class="step active">
                                <div class="step-dot">
                                    <svg viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
                                </div>
                                <p class="step-text">En revisión<br>del equipo</p>
                            </div>
                            <div class="step">
                                <div class="step-dot">
                                    <svg viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><path d="M14 2v6h6"/><path d="M16 13H8"/><path d="M16 17H8"/></svg>
                                </div>
                                <p class="step-text">Entrega de<br>tus números</p>
                            </div>
                        </div>
                    </div>
                    <p class="wait-hint">Tu compra queda pendiente de aprobación. Escríbenos por WhatsApp o actualiza el estado cuando te confirmen.</p>
                </div>

                <?php if ($code !== ''): ?>
                <div class="order-ref">
                    Referencia<br>
                    <code><?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?></code>
                </div>
                <?php endif; ?>

                <div class="btn-row">
                    <?php if ($whatsappPhone !== ''): ?>
                    <button type="button" class="btn-whatsapp" onclick="enviarValidacion()">
                        <i class="ti ti-brand-whatsapp" aria-hidden="true"></i>
                        Obtener mis números
                    </button>
                    <?php endif; ?>
                    <button type="button" class="btn-gold" onclick="actualizarEstado()">
                        Actualizar estado
                    </button>
                    <a class="btn-outline" href="<?= htmlspecialchars($homeUrl, ENT_QUOTES, 'UTF-8') ?>">Volver al sitio</a>
                </div>
                <?= transferenciaComunidadHtml() ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="secure-badge">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
        Conexión segura
    </div>
    </main>

    <script src="<?= htmlspecialchars(ASSETS_URL . '/js/admin-brand.js', ENT_QUOTES, 'UTF-8') ?>?v=2"></script>
    <script>
    const DATA = {
        code: <?= json_encode($code, JSON_UNESCAPED_UNICODE) ?>,
        phone: <?= json_encode($whatsappPhone, JSON_UNESCAPED_UNICODE) ?>,
        homeUrl: <?= json_encode($homeUrl, JSON_UNESCAPED_UNICODE) ?>,
        msgValidacion: <?= json_encode(
            "¡Hola! 👋 Acabo de realizar una compra en su página.\n\n📋 *Código:* {CODE}\n\nPor favor, confirmen y aprueben mi compra. 🙂"
        ) ?>,
        msgSoporte: <?= json_encode(
            "Hola 👋\n\nMi código {CODE} tuvo un problema al confirmarse. Necesito ayuda con mi pago 🙏"
        ) ?>
    };

    function enviarValidacion() {
        abrirWA(DATA.msgValidacion.replace('{CODE}', DATA.code));
    }
    function enviarSoporte() {
        abrirWA(DATA.msgSoporte.replace('{CODE}', DATA.code));
    }
    function abrirWA(texto) {
        const phone = String(DATA.phone || '').replace(/\D/g, '');
        if (!phone) return;
        const url = 'https://api.whatsapp.com/send?phone=' + encodeURIComponent(phone)
            + '&text=' + encodeURIComponent(texto);
        window.open(url, '_blank', 'noopener,noreferrer');
    }
    function actualizarEstado() {
        // Sin from_checkout ni poll: una sola recarga manual.
        window.location.href = 'transferencia.php?code=' + encodeURIComponent(DATA.code) + '&_=' + Date.now();
    }
    </script>
</body>
</html>
