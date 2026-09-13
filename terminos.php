<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/landing/init.php';

$landingDir = __DIR__ . '/includes/landing';
$landingPageTitle = 'Términos y condiciones — ' . ($landingBrandName ?? 'Dinámicas La Milagrosa');
$terminosBrand = $landingBrandName ?? 'Dinámicas La Milagrosa';
$terminosPhones = function_exists('landing_setting') ? landing_setting('footer_phones', '313 399 2903 · 320 259 5184') : '313 399 2903 · 320 259 5184';
$terminosEmail = function_exists('landing_setting') ? landing_setting('contact_email', '') : '';
$terminosHome = defined('BASE_URL') && BASE_URL !== '' ? rtrim((string) BASE_URL, '/') : '/';
$terminosCcmLogo = defined('ASSETS_URL')
    ? ASSETS_URL . '/images/logos/logo-ccm-software-light.png'
    : '/assets/images/logos/logo-ccm-software-light.png';
?>
<!doctype html>
<html lang="es">
<?php include $landingDir . '/head.php'; ?>
<body class="landing">
<?php
include $landingDir . '/banners.php';
include $landingDir . '/social-rail.php';
include $landingDir . '/navbar.php';
?>
<main class="cr-legal">
    <div class="container py-4 py-md-5">
        <div class="cr-legal__card">
            <p class="cr-legal__kicker mb-2">Dinámicas La Milagrosa</p>
            <h1 class="cr-legal__title">Términos y condiciones</h1>
            <p class="cr-legal__updated">3era Dinámica · Combo Pareja · Actualizado septiembre 2026</p>

            <h2>1. Quién organiza</h2>
            <p>
                Esta campaña es organizada por <strong><?= htmlspecialchars($terminosBrand, ENT_QUOTES, 'UTF-8') ?></strong>.
                Al comprar un paquete aceptas estas condiciones.
            </p>

            <h2>2. Cómo funciona</h2>
            <p>
                Eliges un paquete, pagas el valor publicado y, cuando confirmamos tu pago,
                quedan asignados tus nros. La compra mínima es el paquete de 10.
                Esta campaña se rige por la de Boyacá.
                La fecha tentativa es el <strong>24 de octubre</strong>,
                si hemos alcanzado el 70%.
            </p>

            <h2>3. Combo Pareja y entregas</h2>
            <p>
                El Combo Pareja de esta campaña es <strong>NMAX 2027 + MT-15 2027</strong>.
                Hay dos entregas de $4 millones en tecnología: 19 de septiembre y 10 de octubre,
                según lo publicado en la portada.
            </p>
            <p>
                Los organizadores se reservan el derecho de modificar la fecha del evento
                o de las entregas cuando sea necesario por razones operativas o de fuerza mayor.
                Cualquier cambio se avisará por la página o por los canales de contacto.
            </p>

            <h2>4. Pago y confirmación</h2>
            <p>
                El pago se hace por transferencia (Bre-B, Bancolombia o Nequi) o por WhatsApp.
                Tu lugar queda en firme cuando validamos el comprobante. Si el pago no llega,
                llega incompleto o no se puede verificar, la compra no se confirma.
            </p>

            <h2>5. Consulta de tu compra</h2>
            <p>
                Puedes buscar tu compra en la página con el celular que registraste.
                Guarda tu comprobante y tus datos. Si hay un error, escríbenos por WhatsApp
                o a los teléfonos de contacto.
            </p>

            <h2>6. Política de habeas data</h2>
            <p>
                Tratamos tus datos personales de acuerdo con la Ley 1581 de 2012 y normas
                que la complementan (habeas data). Recogemos nombre, apellido, celular,
                correo, ciudad y el comprobante de pago para confirmar tu compra, asignar
                tus nros, contactarte y coordinar las entregas.
            </p>
            <p>
                Eres titular de tu información: puedes pedir que la consultemos, corrijamos
                o retiremos escribiendo a los teléfonos o al correo de contacto.
                No vendemos ni alquilamos tus datos a terceros.
            </p>
            <div class="cr-legal__partner">
                <a href="https://ccmsoftware.com.co" target="_blank" rel="noopener noreferrer" class="cr-legal__partner-logo" title="Visitar CCM Software">
                    <img src="<?= htmlspecialchars($terminosCcmLogo, ENT_QUOTES, 'UTF-8') ?>"
                         alt="CCM Software"
                         width="220"
                         height="44"
                         decoding="async">
                </a>
                <p>
                    <strong>CCM Software</strong> es nuestro aliado tecnológico, encargado de
                    procesar y administrar la información bajo estrictos contratos de
                    confidencialidad y respeto de la información.
                </p>
                <p class="mb-0">
                    CCM Software respeta tus datos. El software y el sitio son operados con
                    medidas razonables de seguridad para que tu información no se use fuera
                    de esta campaña. Más información en
                    <a href="https://ccmsoftware.com.co" target="_blank" rel="noopener noreferrer">ccmsoftware.com.co</a>.
                </p>
            </div>

            <h2>7. Contacto</h2>
            <p>
                Teléfonos: <?= htmlspecialchars($terminosPhones, ENT_QUOTES, 'UTF-8') ?>.
                <?php if ($terminosEmail !== ''): ?>
                    Correo: <a href="mailto:<?= htmlspecialchars($terminosEmail, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($terminosEmail, ENT_QUOTES, 'UTF-8') ?></a>.
                <?php endif; ?>
            </p>

            <p class="cr-legal__back mb-0">
                <a href="<?= htmlspecialchars($terminosHome, ENT_QUOTES, 'UTF-8') ?>">← Volver al inicio</a>
            </p>
        </div>
    </div>
</main>
<?php
include $landingDir . '/footer.php';
include $landingDir . '/modal-buscar-tickets.php';
include __DIR__ . '/includes/preloader.php';
include $landingDir . '/scripts.php';
?>
</body>
</html>
