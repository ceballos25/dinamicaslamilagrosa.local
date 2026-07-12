<?php
$heroTitle = function_exists('landing_setting')
    ? landing_setting('landing_hero_title', '¡Gana una Yamaha NMAX!')
    : '¡Gana una Yamaha NMAX!';
$premioMayorTitulo = function_exists('landing_setting')
    ? landing_setting('landing_premio_mayor_titulo', 'Premio mayor')
    : 'Premio mayor';
$premioMayorDesc = function_exists('landing_setting')
    ? landing_setting('landing_premio_mayor_desc', '')
    : '';
$premioExtraTitulo = function_exists('landing_setting')
    ? landing_setting('landing_premio_extra_titulo', 'Premios adicionales')
    : 'Premios adicionales';
$premioExtraLabel = function_exists('landing_setting')
    ? landing_setting('landing_premio_extra_label', '')
    : '';
$premioExtraDesc = function_exists('landing_setting')
    ? landing_setting('landing_premio_extra_desc', '')
    : '';
$heroBrand = $landingBrandName ?? 'Dinámicas La Milagrosa';
?>
<section class="cr-hero-section py-3">
    <div class="container">
        <div class="cr-hero-layout">

            <div class="cr-hero-title-wrap">
                <h2 class="hero-title mb-0" id="landingHeroTitle">
                    <?= htmlspecialchars($heroTitle, ENT_QUOTES, 'UTF-8') ?>
                </h2>
            </div>

            <div class="cr-hero-photo">
                <div class="card border-0 bg-transparent shadow-none rounded-4 overflow-hidden text-center">
                    <section id="main-carousel" class="splide">
                        <div class="splide__track">
                            <ul class="splide__list" id="heroMainSlides">
                                <li class="splide__slide">
                                    <img class="premios-primer-sorteo" src="<?= htmlspecialchars(ASSETS_URL . '/images/hero-evento-principal.png', ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($heroBrand . ' — premio Yamaha NMAX', ENT_QUOTES, 'UTF-8') ?>" data-site-hero-banner loading="eager">
                                </li>
                                <li class="splide__slide">
                                    <img class="premios-primer-sorteo" src="<?= htmlspecialchars(ASSETS_URL . '/images/premio-nmax.jpeg', ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($heroBrand . ' — Yamaha NMAX', ENT_QUOTES, 'UTF-8') ?>" data-site-hero-gallery-2 loading="lazy">
                                </li>
                            </ul>
                        </div>
                    </section>

                    <section id="thumbnail-carousel" class="splide mt-3 mx-auto" style="max-width: 420px;" aria-label="Miniaturas del premio">
                        <div class="splide__track">
                            <ul class="splide__list" id="heroThumbSlides">
                                <li class="splide__slide">
                                    <img src="<?= htmlspecialchars(ASSETS_URL . '/images/hero-evento-principal.png', ENT_QUOTES, 'UTF-8') ?>" alt="" data-site-hero-banner loading="lazy">
                                </li>
                                <li class="splide__slide">
                                    <img src="<?= htmlspecialchars(ASSETS_URL . '/images/premio-nmax.jpeg', ENT_QUOTES, 'UTF-8') ?>" alt="" data-site-hero-gallery-2 loading="lazy">
                                </li>
                            </ul>
                        </div>
                    </section>
                </div>
            </div>

            <div class="cr-hero-puestos">
                <div class="cr-hero-premio">
                    <div class="card border-0 shadow-sm premio-mayor-card">
                        <div class="card-body d-flex align-items-start gap-3">
                            <div class="premio-mayor-icon">
                                <i class="ti ti-trophy fs-4"></i>
                            </div>
                            <div>
                                <p class="premio-mayor-label mb-1" id="landingPremioMayorTitulo"><?= htmlspecialchars($premioMayorTitulo, ENT_QUOTES, 'UTF-8') ?></p>
                                <p class="premio-mayor-desc mb-0" id="landingPremioMayorDesc"><?= htmlspecialchars($premioMayorDesc, ENT_QUOTES, 'UTF-8') ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="cr-hero-extras">
                    <div class="row g-3">
                        <div class="col-12">
                            <div class="card border-0 shadow-sm text-center w-100 cr-puesto-card">
                                <div class="card-body py-3">
                                    <p class="cr-puesto-label mb-1" id="landingPremioExtraTitulo"><?= htmlspecialchars($premioExtraTitulo, ENT_QUOTES, 'UTF-8') ?></p>
                                    <h3 class="fw-bold mb-1">
                                        <span class="color-dinero-premio" id="landingPremioExtraLabel"><?= htmlspecialchars($premioExtraLabel, ENT_QUOTES, 'UTF-8') ?></span>
                                    </h3>
                                    <small class="fw-bold text-muted d-block mb-2" id="landingPremioExtraDesc"><?= htmlspecialchars($premioExtraDesc, ENT_QUOTES, 'UTF-8') ?></small>
                                    <div id="bendecidosCardsContainer" class="cr-bendecidos-chips d-flex flex-wrap justify-content-center gap-1">
                                        <?php if (!empty($bendecidosCards)): ?>
                                            <?php foreach ($bendecidosCards as $c): ?>
                                                <div class="bendecidos-numeros<?= !empty($c['premiado_vendido']) ? ' premiado' : '' ?>"><?= htmlspecialchars((string)$c['number'], ENT_QUOTES, 'UTF-8') ?></div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <p class="small text-muted mb-0 w-100">Los nros bendecidos se publican aquí al marcarlos premium en Inventario.</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="cr-hero-progress">
                <div class="card border-0 shadow-sm cr-progress-card">
                    <div class="card-body py-3">
                        <div class="cr-progress-label">
                            <span>Ventas realizadas</span>
                            <span id="porcentajeTexto">0%</span>
                        </div>
                        <div class="cr-progress-race my-1">
                            <div class="progress">
                                <div id="barraProgreso" class="progress-bar progress-bar-striped progress-bar-animated" style="width: 0%" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                            <span id="caballoProgreso" class="cr-progress-caballo" aria-hidden="true"></span>
                            <span class="cr-progress-meta" aria-hidden="true">🏁</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="cr-hero-precio">
                <div class="card border-0 shadow-sm text-center w-100 cr-hero-precio-card">
                    <div class="card-body py-3">
                        <p class="cr-hero-precio-label mb-2">Precios</p>
                        <div id="precioBoletaDisplay" class="cr-hero-precio-tiers">
                            <span class="spinner-border spinner-border-sm text-warning"></span>
                        </div>
                        <p class="cr-hero-promo-note mb-0 d-none" id="precioPromoNote"></p>
                    </div>
                </div>
            </div>

        </div>
    </div>
</section>
