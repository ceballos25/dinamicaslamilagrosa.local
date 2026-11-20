<?php
$heroTitleDefault = 'DINÁMICA NAVIDEÑA';
$heroTitle = function_exists('landing_setting')
    ? landing_setting('landing_hero_title', $heroTitleDefault)
    : $heroTitleDefault;
$heroBrand = $landingBrandName ?? 'Dinámicas La Milagrosa';
$heroSalaJpg = ASSETS_URL . '/images/portada-palitos-sala.jpg';
$heroSalaWebp = ASSETS_URL . '/images/portada-palitos-sala.webp';
$heroSalaWebpSm = ASSETS_URL . '/images/portada-palitos-sala-480.webp';
$heroTerrazaJpg = ASSETS_URL . '/images/portada-palitos-terraza.jpg';
$heroTerrazaWebp = ASSETS_URL . '/images/portada-palitos-terraza.webp';
$heroTerrazaWebpSm = ASSETS_URL . '/images/portada-palitos-terraza-480.webp';
$heroAltSala = $heroBrand . ' — 20 palitos. Cada miércoles $500.000. Código por $8.000. Compra mínima 2 códigos.';
$heroAltTerraza = $heroBrand . ' — 20.000.000 palitos. Cada miércoles $500.000. Código por $8.000. Compra mínima 2 códigos.';
?>
<section class="cr-hero-section py-3">
    <div class="container">
        <div class="cr-hero-layout">

            <h2 class="visually-hidden" id="landingHeroTitle"><?= htmlspecialchars($heroTitle, ENT_QUOTES, 'UTF-8') ?></h2>

            <div class="cr-hero-photo">
                <div id="crHeroSlider" class="splide cr-hero-slider" aria-label="Portadas de la dinámica">
                    <div class="splide__track">
                        <ul class="splide__list">
                            <li class="splide__slide">
                                <picture>
                                    <source media="(max-width: 767px)" type="image/webp" srcset="<?= htmlspecialchars($heroSalaWebpSm, ENT_QUOTES, 'UTF-8') ?>?v=6">
                                    <source type="image/webp" srcset="<?= htmlspecialchars($heroSalaWebp, ENT_QUOTES, 'UTF-8') ?>?v=6">
                                    <img class="cr-hero-cover" src="<?= htmlspecialchars($heroSalaJpg, ENT_QUOTES, 'UTF-8') ?>?v=6" alt="<?= htmlspecialchars($heroAltSala, ENT_QUOTES, 'UTF-8') ?>" width="768" height="1024" fetchpriority="high" decoding="async">
                                </picture>
                            </li>
                            <li class="splide__slide">
                                <picture>
                                    <source media="(max-width: 767px)" type="image/webp" data-srcset="<?= htmlspecialchars($heroTerrazaWebpSm, ENT_QUOTES, 'UTF-8') ?>?v=6">
                                    <source type="image/webp" data-srcset="<?= htmlspecialchars($heroTerrazaWebp, ENT_QUOTES, 'UTF-8') ?>?v=6">
                                    <img class="cr-hero-cover" data-src="<?= htmlspecialchars($heroTerrazaJpg, ENT_QUOTES, 'UTF-8') ?>?v=6" alt="<?= htmlspecialchars($heroAltTerraza, ENT_QUOTES, 'UTF-8') ?>" width="768" height="1024" decoding="async">
                                </picture>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="cr-hero-progress" style="display: none">
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

            <div class="cr-hero-cta">
                <a href="#compra" class="cr-hero-scroll-cta">
                    ¿Cuál deseas?
                </a>
            </div>

        </div>
    </div>
</section>
