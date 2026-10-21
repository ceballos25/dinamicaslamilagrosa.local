<?php
$footerBrand = $landingBrandName ?? 'Dinámicas La Milagrosa';
$footerTagline = function_exists('landing_setting') ? landing_setting('footer_tagline', '') : '';
if ($footerTagline !== '' && preg_match('/participa/iu', $footerTagline)) {
    $footerTagline = '';
}
$footerPhones = function_exists('landing_setting') ? landing_setting('footer_phones', '') : '';
$footerEmail = function_exists('landing_setting') ? landing_setting('contact_email', '') : '';
$footerLocation = function_exists('landing_setting') ? landing_setting('footer_location', 'Colombia') : 'Colombia';
$footerPayments = function_exists('landing_setting') ? landing_setting('footer_payments', '') : '';
$footerIg = function_exists('landing_setting') ? landing_setting('social_instagram_url', '') : '';
$footerFb = function_exists('landing_setting') ? landing_setting('social_facebook_url', '') : '';
$footerTk = function_exists('landing_setting') ? landing_setting('social_tiktok_url', '') : '';
$landingHome = defined('BASE_URL') && BASE_URL !== '' ? rtrim((string) BASE_URL, '/') : '';
$landingCompraHref = $landingHome . '/#compra';
$landingTerminosHref = $landingHome . '/terminos.php';
?>
    <footer class="site-footer">
        <div class="container py-5">

            <div class="row g-4 text-center text-md-start">

                <div class="col-md-4 site-footer-brand-col">
                    <div class="site-footer-brand-block">
                        <h5 class="fw-bold site-footer-heading mb-2" id="landingFooterBrand"><?= htmlspecialchars($footerBrand, ENT_QUOTES, 'UTF-8') ?></h5>
                        <p class="small site-footer-text mb-3<?= $footerTagline === '' ? ' d-none' : '' ?>" id="landingFooterTagline">
                            <?= htmlspecialchars($footerTagline, ENT_QUOTES, 'UTF-8') ?>
                        </p>
                        <?php $footerLogo = defined('LOGO_NAV_URL') ? LOGO_NAV_URL : (ASSETS_URL . '/images/logo-transparent.png'); ?>
                        <a href="<?= htmlspecialchars(defined('BASE_URL') && BASE_URL !== '' ? rtrim((string) BASE_URL, '/') : '/', ENT_QUOTES, 'UTF-8') ?>" class="site-footer-logo" aria-label="<?= htmlspecialchars($footerBrand . ' — inicio', ENT_QUOTES, 'UTF-8') ?>">
                            <img src="<?= htmlspecialchars($footerLogo, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($footerBrand, ENT_QUOTES, 'UTF-8') ?>" data-site-logo-footer loading="lazy">
                        </a>
                    </div>
                </div>

                <div class="col-md-4">
                    <h6 class="fw-bold text-uppercase mb-3 site-footer-heading">Enlaces de interés</h6>
                    <ul class="list-unstyled site-footer-list small mb-0">
                        <li>
                            <a href="<?= htmlspecialchars($landingCompraHref, ENT_QUOTES, 'UTF-8') ?>" class="site-footer-link">
                                <i class="ti ti-shopping-cart" aria-hidden="true"></i>
                                <span>Comprar</span>
                            </a>
                        </li>
                        <li>
                            <a href="<?= htmlspecialchars($landingTerminosHref, ENT_QUOTES, 'UTF-8') ?>" class="site-footer-link">
                                <i class="ti ti-file-text" aria-hidden="true"></i>
                                <span>Términos y condiciones</span>
                            </a>
                        </li>
                        <li id="consultarNumeros">
                            <button type="button" class="btn btn-warning btn-sm fw-bold site-footer-cta" data-bs-toggle="modal" data-bs-target="#modalBuscarTickets">
                                <i class="ti ti-ticket me-1" aria-hidden="true"></i>
                                Buscar mis nros
                            </button>
                        </li>
                    </ul>
                </div>

                <div class="col-md-4">
                    <h6 class="fw-bold text-uppercase mb-3 site-footer-heading">Contacto</h6>
                    <ul class="list-unstyled site-footer-list small mb-0">
                        <li id="landingFooterPhonesWrap" class="<?= $footerPhones === '' ? 'd-none' : '' ?>">
                            <i class="ti ti-phone site-footer-icon" aria-hidden="true"></i>
                            <span id="landingFooterPhones"><?= htmlspecialchars($footerPhones, ENT_QUOTES, 'UTF-8') ?></span>
                        </li>
                        <li id="landingFooterEmailWrap" class="<?= $footerEmail === '' ? 'd-none' : '' ?>">
                            <i class="ti ti-mail site-footer-icon" aria-hidden="true"></i>
                            <a href="mailto:<?= htmlspecialchars($footerEmail, ENT_QUOTES, 'UTF-8') ?>" class="site-footer-link" id="landingFooterEmail"><?= htmlspecialchars($footerEmail, ENT_QUOTES, 'UTF-8') ?></a>
                        </li>
                        <li id="landingFooterLocationWrap" class="<?= $footerLocation === '' ? 'd-none' : '' ?>">
                            <i class="ti ti-map-pin site-footer-icon" aria-hidden="true"></i>
                            <span id="landingFooterLocation"><?= htmlspecialchars($footerLocation, ENT_QUOTES, 'UTF-8') ?></span>
                        </li>
                        <li id="landingFooterPaymentsWrap" class="<?= $footerPayments === '' ? 'd-none' : '' ?>">
                            <i class="ti ti-transfer site-footer-icon" aria-hidden="true"></i>
                            <span id="landingFooterPayments"><?= htmlspecialchars($footerPayments, ENT_QUOTES, 'UTF-8') ?></span>
                        </li>
                    </ul>
                    <div class="mt-3 d-flex gap-2 justify-content-center justify-content-md-start d-none" aria-hidden="true" hidden>
                        <?php
                        $footerWa = function_exists('landing_setting') ? landing_setting('whatsapp_chat_url', '') : '';
                        if ($footerWa === '' && function_exists('landing_setting')) {
                            $waDigits = preg_replace('/\D+/', '', landing_setting('whatsapp', ''));
                            if ($waDigits !== '') {
                                $footerWa = 'https://wa.me/' . $waDigits;
                            }
                        }
                        ?>
                        <a href="<?= htmlspecialchars($footerWa, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" class="social-whatsapp btn btn-sm btn-outline-light<?= $footerWa === '' ? ' d-none' : '' ?>" aria-label="WhatsApp">
                            <i class="ti ti-brand-whatsapp"></i>
                        </a>
                        <a href="<?= htmlspecialchars($footerIg, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" class="social-instagram btn btn-sm btn-outline-light<?= $footerIg === '' ? ' d-none' : '' ?>" aria-label="Instagram">
                            <i class="ti ti-brand-instagram"></i>
                        </a>
                        <a href="<?= htmlspecialchars($footerFb, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" class="social-facebook btn btn-sm btn-outline-light<?= $footerFb === '' ? ' d-none' : '' ?>" aria-label="Facebook">
                            <i class="ti ti-brand-facebook"></i>
                        </a>
                        <a href="<?= htmlspecialchars($footerTk, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" class="social-tiktok btn btn-sm btn-outline-light<?= $footerTk === '' ? ' d-none' : '' ?>" aria-label="TikTok">
                            <i class="ti ti-brand-tiktok"></i>
                        </a>
                    </div>
                </div>

            </div>

            <hr class="site-footer-hr my-4">

            <div class="text-center small site-footer-copy pb-2">
                © <?= date('Y'); ?> <span id="landingFooterCopyBrand"><?= htmlspecialchars($footerBrand, ENT_QUOTES, 'UTF-8') ?></span> · Todos los derechos reservados
                <br>
                <p class="landing-footer__dev site-footer-credit small mb-0">
                    Desarrollado por
                    <a href="https://wa.me/573245894268"
                       target="_blank"
                       rel="noopener noreferrer"
                       class="landing-footer__dev-link site-footer-dev"
                       title="Escribir a Cristian Ceballos por WhatsApp"
                       aria-label="WhatsApp de Cristian Ceballos">
                        <em>Cristian Ceballos</em>
                        <i class="ti ti-brand-whatsapp" aria-hidden="true"></i>
                    </a>
                </p>
            </div>

        </div>
    </footer>
