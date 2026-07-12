    <footer class="site-footer">
        <div class="container py-5">

            <div class="row g-4 text-center text-md-start">

                <div class="col-md-4 site-footer-brand-col">
                    <div class="site-footer-brand-block">
                        <h5 class="fw-bold site-footer-heading mb-2">Combo Stickers 🍀</h5>
                        <p class="small site-footer-text mb-3">
                            Participa fácil, rápido y seguro desde cualquier lugar de Colombia.
                            Transparencia y respaldo en cada dinámica.
                        </p>
                        <?php $footerLogo = defined('LOGO_NAV_URL') ? LOGO_NAV_URL : (ASSETS_URL . '/images/logo.png'); ?>
                        <a href="<?= htmlspecialchars(defined('BASE_URL') && BASE_URL !== '' ? rtrim((string) BASE_URL, '/') : '/', ENT_QUOTES, 'UTF-8') ?>" class="site-footer-logo" aria-label="Combo Stickers — inicio">
                            <img src="<?= htmlspecialchars($footerLogo, ENT_QUOTES, 'UTF-8') ?>" alt="Combo Stickers" data-site-logo-footer loading="lazy">
                        </a>
                    </div>
                </div>

                <div class="col-md-4">
                    <h6 class="fw-bold text-uppercase mb-3 site-footer-heading">Enlaces de interés</h6>
                    <ul class="list-unstyled site-footer-list small mb-0">
                        <li>
                            <a href="#compra" class="site-footer-link">
                                <i class="ti ti-shopping-cart" aria-hidden="true"></i>
                                <span>Comprar</span>
                            </a>
                        </li>
                        <li>
                            <a href="https://combostickers.com/docs/ptd.pdf" class="site-footer-link" target="_blank" rel="noopener noreferrer">
                                <i class="ti ti-shield-lock" aria-hidden="true"></i>
                                <span>Política de privacidad</span>
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
                        <li>
                            <i class="ti ti-phone site-footer-icon" aria-hidden="true"></i>
                            <span>313 631 9681</span>
                        </li>
                        <li>
                            <i class="ti ti-mail site-footer-icon" aria-hidden="true"></i>
                            <a href="mailto:<?= htmlspecialchars(MAIL_FROM, ENT_QUOTES, 'UTF-8') ?>" class="site-footer-link"><?= htmlspecialchars(MAIL_FROM, ENT_QUOTES, 'UTF-8') ?></a>
                        </li>
                        <li>
                            <i class="ti ti-map-pin site-footer-icon" aria-hidden="true"></i>
                            <span>Colombia</span>
                        </li>
                        <li>
                            <i class="ti ti-transfer site-footer-icon" aria-hidden="true"></i>
                            <span>Pago por transferencia Bre-B</span>
                        </li>
                    </ul>
                    <div class="mt-3 d-flex gap-2 justify-content-center justify-content-md-start">
                        <a href="https://www.instagram.com/martoplaya/" target="_blank" rel="noopener noreferrer" class="social-instagram btn btn-sm btn-outline-light" aria-label="Instagram">
                            <i class="ti ti-brand-instagram"></i>
                        </a>
                    </div>
                </div>

            </div>

            <hr class="site-footer-hr my-4">

            <div class="text-center small site-footer-copy pb-2">
                © <?= date('Y'); ?> Combo Stickers · Todos los derechos reservados
                <br>
                <span class="site-footer-credit">
                    Desarrollado por
                    <a href="https://cristianceballos.com/"
                        class="site-footer-dev"
                        target="_blank"
                        rel="noopener noreferrer"
                        title="Sitio web de Cristian Ceballos">
                        Cristian Ceballos
                        <i class="ti ti-external-link" aria-hidden="true"></i>
                    </a>
                </span>
            </div>

        </div>
    </footer>
