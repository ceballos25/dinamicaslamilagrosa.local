<?php
$ganadores = [
    ['img' => ASSETS_URL . 'url..', 'label' => ''],
    ['img' => ASSETS_URL . 'url..', 'label' => ''],
    ['img' => ASSETS_URL . 'url..', 'label' => ''],
    ['img' => ASSETS_URL . 'url..', 'label' => ''],
];
?>
<section class="texto-ganadores d-none">
    <div>
        <h2 class="title-ganadores text-center title-premios">Entregas</h2>
    </div>
</section>

<section class="container-ganadores position-relative overflow-hidden mb-5 d-none">

    <canvas id="confetti-canvas"></canvas>

    <div style="max-width: 600px; margin: 0 auto;">

    <div id="ganadores-carousel" class="splide">
        <div class="splide__track">
            <ul class="splide__list" id="ganadoresMainSlides">
                <?php foreach ($ganadores as $g): ?>
                <li class="splide__slide text-center">
                    <p class="ganador-one"><?= htmlspecialchars($g['label'], ENT_QUOTES, 'UTF-8') ?></p>
                    <img src="<?= htmlspecialchars($g['img'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($g['label'], ENT_QUOTES, 'UTF-8') ?>" loading="lazy" decoding="async">
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <div id="ganadores-thumbnails" class="splide mt-2">
        <div class="splide__track">
            <ul class="splide__list" id="ganadoresThumbSlides">
                <?php foreach ($ganadores as $g): ?>
                <li class="splide__slide">
                    <img src="<?= htmlspecialchars($g['img'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($g['label'], ENT_QUOTES, 'UTF-8') ?>" loading="lazy" decoding="async">
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    </div>

</section>
