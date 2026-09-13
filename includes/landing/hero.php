<?php
$heroTitleDefault = "💑 ¡Combo Pareja!\n🛵 NMAX 2027 + 🏍️ MT-15";
$heroTitle = function_exists('landing_setting')
    ? landing_setting('landing_hero_title', $heroTitleDefault)
    : $heroTitleDefault;
$heroEsCombo = (bool) preg_match('/combo\s*pareja/iu', $heroTitle)
    || ((bool) preg_match('/\bnmax\b/iu', $heroTitle) && (bool) preg_match('/\bmt-?15\b/iu', $heroTitle));
if ($heroEsCombo && !preg_match('/💑/u', $heroTitle)) {
    $heroTitle = $heroTitleDefault;
}
$premioMayorDescCanon = "NMAX 2027 + MT-15 2027\nPor la de Boyacá para este 24 de Octubre\n19 de sep y 10 oct · 2 entregas de \$4 Millones en tecnología";
$premioMayorDesc = $premioMayorDescCanon;
$premioMayorTitulo = function_exists('landing_setting')
    ? landing_setting('landing_premio_mayor_titulo', '3era Dinámica · COMBO PAREJA')
    : '3era Dinámica · COMBO PAREJA';
$premioExtraTitulo = function_exists('landing_setting')
    ? landing_setting('landing_premio_extra_titulo', 'Bendecidos')
    : 'Bendecidos';
$premioExtraLabel = function_exists('landing_setting')
    ? landing_setting('landing_premio_extra_label', '6 nros · $500.000 c/u')
    : '6 nros · $500.000 c/u';
$premioExtraDesc = function_exists('landing_setting')
    ? landing_setting('landing_premio_extra_desc', '')
    : '';
$heroBrand = $landingBrandName ?? 'Dinámicas La Milagrosa';

$premioMayorFacts = [
    'NMAX 2027 + MT-15 2027',
    'Por la de Boyacá para este 24 de Octubre',
    '19 de sep y 10 oct · 2 entregas de $4 Millones en tecnología',
];
?>
<section class="cr-hero-section py-3">
    <div class="container">
        <div class="cr-hero-layout">

            <div class="cr-hero-title-wrap">
                <h2 class="hero-title mb-0" id="landingHeroTitle">
                    <?= nl2br(htmlspecialchars($heroTitle, ENT_QUOTES, 'UTF-8'), false) ?>
                </h2>
            </div>

            <div class="cr-hero-countdown" id="heroCountdown" data-target="2026-09-19T22:00:00-05:00">
                <div class="cr-countdown">
                    <p class="cr-countdown__kicker">Primera entrega · $4 Millones en Tecnología</p>
                    <p class="cr-countdown__date">19 de septiembre</p>
                    <div class="cr-countdown__units" role="timer" aria-live="off" aria-label="Cuenta regresiva a la primera entrega">
                        <div class="cr-countdown__unit">
                            <span class="cr-countdown__num" data-unit="days">00</span>
                            <span class="cr-countdown__lbl">días</span>
                        </div>
                        <span class="cr-countdown__sep" aria-hidden="true">:</span>
                        <div class="cr-countdown__unit">
                            <span class="cr-countdown__num" data-unit="hours">00</span>
                            <span class="cr-countdown__lbl">horas</span>
                        </div>
                        <span class="cr-countdown__sep" aria-hidden="true">:</span>
                        <div class="cr-countdown__unit">
                            <span class="cr-countdown__num" data-unit="mins">00</span>
                            <span class="cr-countdown__lbl">min</span>
                        </div>
                        <span class="cr-countdown__sep" aria-hidden="true">:</span>
                        <div class="cr-countdown__unit cr-countdown__unit--secs">
                            <span class="cr-countdown__num" data-unit="secs">00</span>
                            <span class="cr-countdown__lbl">seg</span>
                        </div>
                    </div>
                    <p class="cr-countdown__done">¡Hoy es la primera entrega de $4 Millones!</p>
                </div>
            </div>

            <div class="cr-hero-photo">
                <div class="card border-0 bg-transparent shadow-none rounded-4 overflow-hidden text-center">
                    <section id="main-carousel" class="splide">
                        <div class="splide__track">
                            <ul class="splide__list" id="heroMainSlides">
                                <li class="splide__slide">
                                    <img class="premios-primer-sorteo" src="<?= htmlspecialchars(ASSETS_URL . '/images/combo-pareja-frente.jpg', ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($heroBrand . ' — Combo Pareja NMAX y MT-15', ENT_QUOTES, 'UTF-8') ?>" loading="eager">
                                </li>
                                <li class="splide__slide">
                                    <img class="premios-primer-sorteo" src="<?= htmlspecialchars(ASSETS_URL . '/images/combo-pareja-nmax.jpg', ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($heroBrand . ' — NMAX 2027 Combo Pareja', ENT_QUOTES, 'UTF-8') ?>" loading="lazy">
                                </li>
                                <li class="splide__slide">
                                    <img class="premios-primer-sorteo" src="<?= htmlspecialchars(ASSETS_URL . '/images/combo-pareja-entrega.jpg', ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($heroBrand . ' — Combo Pareja listo para entregar', ENT_QUOTES, 'UTF-8') ?>" loading="lazy">
                                </li>
                                <li class="splide__slide">
                                    <img class="premios-primer-sorteo" src="<?= htmlspecialchars(ASSETS_URL . '/images/premio-nmax-mt15-2027.jpeg', ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($heroBrand . ' — NMAX 2027 y MT-15 2027', ENT_QUOTES, 'UTF-8') ?>" data-site-hero-gallery-2 loading="lazy">
                                </li>
                            </ul>
                        </div>
                    </section>

                    <section id="thumbnail-carousel" class="splide mt-2 mx-auto" style="max-width: 520px;" aria-label="Miniaturas del premio">
                        <div class="splide__track">
                            <ul class="splide__list" id="heroThumbSlides">
                                <li class="splide__slide">
                                    <img src="<?= htmlspecialchars(ASSETS_URL . '/images/combo-pareja-frente.jpg', ENT_QUOTES, 'UTF-8') ?>" alt="" loading="lazy">
                                </li>
                                <li class="splide__slide">
                                    <img src="<?= htmlspecialchars(ASSETS_URL . '/images/combo-pareja-nmax.jpg', ENT_QUOTES, 'UTF-8') ?>" alt="" loading="lazy">
                                </li>
                                <li class="splide__slide">
                                    <img src="<?= htmlspecialchars(ASSETS_URL . '/images/combo-pareja-entrega.jpg', ENT_QUOTES, 'UTF-8') ?>" alt="" loading="lazy">
                                </li>
                                <li class="splide__slide">
                                    <img src="<?= htmlspecialchars(ASSETS_URL . '/images/premio-nmax-mt15-2027.jpeg', ENT_QUOTES, 'UTF-8') ?>" alt="" data-site-hero-gallery-2 loading="lazy">
                                </li>
                            </ul>
                        </div>
                    </section>
                </div>
            </div>

            <div class="cr-hero-puestos">
                <div class="cr-hero-premio">
                    <div class="card border-0 shadow-sm premio-mayor-card">
                        <div class="card-body py-3">
                            <p class="premio-mayor-label mb-2" id="landingPremioMayorTitulo"><?= htmlspecialchars($premioMayorTitulo, ENT_QUOTES, 'UTF-8') ?></p>
                            <ul class="premio-mayor-facts mb-0" id="landingPremioMayorDesc" aria-label="Detalle del premio">
                                <?php if ($premioMayorFacts === []): ?>
                                    <li class="premio-mayor-facts__item">Premio mayor por confirmar</li>
                                <?php else: ?>
                                    <?php foreach ($premioMayorFacts as $fact): ?>
                                        <?php
                                        $factShow = $fact;
                                        if (preg_match('/\bnmax\b/iu', $factShow) && !str_contains($factShow, '🛵')) {
                                            $factShow = preg_replace('/\bNMAX\b/iu', '🛵 NMAX', $factShow) ?? $factShow;
                                        }
                                        if (preg_match('/\bmt-?15\b/iu', $factShow) && !str_contains($factShow, '🏍️') && !str_contains($factShow, '🏍')) {
                                            $factShow = preg_replace('/\bMT-?15\b/iu', '🏍️ MT-15', $factShow) ?? $factShow;
                                        }
                                        ?>
                                        <li class="premio-mayor-facts__item"><?= htmlspecialchars($factShow, ENT_QUOTES, 'UTF-8') ?></li>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="cr-hero-extras">
                    <div class="card border-0 shadow-sm text-center w-100 cr-puesto-card">
                        <div class="card-body py-3">
                            <p class="cr-puesto-label mb-1" id="landingPremioExtraTitulo"><?= htmlspecialchars($premioExtraTitulo, ENT_QUOTES, 'UTF-8') ?></p>
                            <p class="fw-bold mb-2 color-dinero-premio" id="landingPremioExtraLabel"><?= htmlspecialchars($premioExtraLabel, ENT_QUOTES, 'UTF-8') ?></p>
                            <small class="fw-semibold text-muted d-block mb-2<?= trim((string)$premioExtraDesc) === '' ? ' d-none' : '' ?>" id="landingPremioExtraDesc"><?= htmlspecialchars($premioExtraDesc, ENT_QUOTES, 'UTF-8') ?></small>
                            <div id="bendecidosCardsContainer" class="cr-bendecidos-chips d-flex flex-wrap justify-content-center gap-1">
                                <?php if (!empty($bendecidosCards)): ?>
                                    <?php foreach ($bendecidosCards as $c): ?>
                                        <div class="bendecidos-numeros<?= !empty($c['premiado_vendido']) ? ' premiado' : '' ?>"><?= htmlspecialchars((string)$c['number'], ENT_QUOTES, 'UTF-8') ?></div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="small text-muted mb-0 w-100">Pronto publicamos los nros bendecidos.</p>
                                <?php endif; ?>
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

            <div class="cr-hero-cta">
                <a href="#compra" class="cr-hero-scroll-cta">
                    ¿Cuál deseas?
                </a>
            </div>

        </div>
    </div>
</section>
