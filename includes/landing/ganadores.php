<?php
$resenas = [
    [
        'nombre' => 'Laura Méndez',
        'iniciales' => 'LM',
        'ciudad' => 'Bogotá',
        'estrellas' => 5,
        'fijada' => true,
        'texto' => 'Son serios: explican el pago, revisan el comprobante y sí entregan los premios. Estaría bueno que tiren otro carro.',
    ],
    [
        'nombre' => 'Camilo Herrera',
        'iniciales' => 'CH',
        'ciudad' => 'Medellín',
        'estrellas' => 5,
        'fijada' => false,
        'texto' => 'Me confirmaron el mismo día de la transferencia. Se nota que trabajan en orden y que los premios sí los entregan.',
    ],
    [
        'nombre' => 'Andrea Ríos',
        'iniciales' => 'AR',
        'ciudad' => 'Cali',
        'estrellas' => 5,
        'fijada' => false,
        'texto' => 'Tenía dudas y me respondieron por WhatsApp sin rodeos. Trato serio de principio a fin, y los premios salen.',
    ],
    [
        'nombre' => 'Julián Castaño',
        'iniciales' => 'JC',
        'ciudad' => 'Barranquilla',
        'estrellas' => 4,
        'fijada' => false,
        'texto' => 'El paquete quedó claro: precio y fecha. Cumplen lo que publican y entregan el premio.',
    ],
    [
        'nombre' => 'Valentina Ortiz',
        'iniciales' => 'VO',
        'ciudad' => 'Cartagena',
        'estrellas' => 5,
        'fijada' => false,
        'texto' => 'Ya he comprado varias veces. Son confiables: cuando sale el premio lo entregan, no se esconden.',
    ],
    [
        'nombre' => 'Santiago Peña',
        'iniciales' => 'SP',
        'ciudad' => 'Bucaramanga',
        'estrellas' => 5,
        'fijada' => false,
        'texto' => 'Gente seria. Subí el comprobante, lo validaron y quedó todo registrado. Los premios sí los pagan.',
    ],
    [
        'nombre' => 'Daniela Gómez',
        'iniciales' => 'DG',
        'ciudad' => 'Pereira',
        'estrellas' => 5,
        'fijada' => false,
        'texto' => 'Desde Pereira pagué y me confirmaron rápido. Cumplen. Ojalá tiren otro carro, la gente lo está pidiendo.',
    ],
    [
        'nombre' => 'Andrés Quintero',
        'iniciales' => 'AQ',
        'ciudad' => 'Manizales',
        'estrellas' => 4,
        'fijada' => false,
        'texto' => 'Me atendieron con calma y dejaron todo en el celular de la compra. Serios, y el premio sí se entrega.',
    ],
    [
        'nombre' => 'Mariana López',
        'iniciales' => 'ML',
        'ciudad' => 'Santa Marta',
        'estrellas' => 5,
        'fijada' => false,
        'texto' => 'Confiable. Pregunté por WhatsApp, pagué y no me dieron largas. Se ve que son serios con los premios.',
    ],
    [
        'nombre' => 'Felipe Duarte',
        'iniciales' => 'FD',
        'ciudad' => 'Cúcuta',
        'estrellas' => 5,
        'fijada' => false,
        'texto' => 'Pensé que por estar lejos se demoraban. No: revisaron el comprobante el mismo día. Son serios y sí entregan.',
    ],
    [
        'nombre' => 'Natalia Cruz',
        'iniciales' => 'NC',
        'ciudad' => 'Ibagué',
        'estrellas' => 5,
        'fijada' => false,
        'texto' => 'Todo lo publicado coincide. Responden y pagan los premios como dice la página. Faltaría otro carro.',
    ],
    [
        'nombre' => 'Juan Pablo Rojas',
        'iniciales' => 'JR',
        'ciudad' => 'Villavicencio',
        'estrellas' => 4,
        'fijada' => false,
        'texto' => 'La compra fue clara y quedó a mi nombre. Equipo organizado, y cuando hay premio lo entregan.',
    ],
    [
        'nombre' => 'Carolina Benítez',
        'iniciales' => 'CB',
        'ciudad' => 'Pasto',
        'estrellas' => 5,
        'fijada' => false,
        'texto' => 'Desde Pasto no tuve problema. Me explicaron el pago y, al validar el comprobante, quedó confirmado. Serios.',
    ],
    [
        'nombre' => 'Esteban Morales',
        'iniciales' => 'EM',
        'ciudad' => 'Montería',
        'estrellas' => 5,
        'fijada' => false,
        'texto' => 'Serios de verdad. Guardé el comprobante, confirmaron y el premio se entrega. No es cuento.',
    ],
    [
        'nombre' => 'Paula Jiménez',
        'iniciales' => 'PJ',
        'ciudad' => 'Neiva',
        'estrellas' => 5,
        'fijada' => false,
        'texto' => 'No cambian las reglas. Precio y fecha como está en la página, y los premios sí salen.',
    ],
    [
        'nombre' => 'Ricardo Osorio',
        'iniciales' => 'RO',
        'ciudad' => 'Armenia',
        'estrellas' => 4,
        'fijada' => false,
        'texto' => 'Compré sin enredo y la atención fue seria. Estaría bueno que tiren otro carro.',
    ],
    [
        'nombre' => 'Lucía Fernández',
        'iniciales' => 'LF',
        'ciudad' => 'Popayán',
        'estrellas' => 5,
        'fijada' => false,
        'texto' => 'Llevan el proceso con orden. Pagué, envié el soporte y quedó confirmado. Se ve que entregan los premios.',
    ],
];

$resenasTotal = count($resenas);
$resenasSuma = array_sum(array_column($resenas, 'estrellas'));
$resenasPromedio = $resenasTotal > 0 ? $resenasSuma / $resenasTotal : 0;
$resenasPromedioLabel = number_format($resenasPromedio, 1, ',', '');

$crEstrellas = static function (int $n): string {
    $n = max(0, min(5, $n));
    $html = '<span class="cr-resena__stars" aria-label="' . $n . ' de 5">';
    for ($i = 1; $i <= 5; $i++) {
        $html .= '<span' . ($i <= $n ? ' class="is-on"' : '') . ' aria-hidden="true">★</span>';
    }
    return $html . '</span>';
};

$ganadores = [
    [
        'video' => ASSETS_URL . '/videos/ganadora.mp4',
        'poster' => ASSETS_URL . '/videos/ganadora.jpg',
        'label' => 'Ganadora',
    ],
    [
        'video' => ASSETS_URL . '/videos/ganador.mp4',
        'poster' => ASSETS_URL . '/videos/ganador.jpg',
        'label' => 'Ganador',
    ],
];
?>
<section class="cr-resenas" aria-labelledby="crResenasTitle">
    <div class="container">
        <header class="cr-resenas__head">
            <p class="cr-resenas__kicker">Reseñas fijadas</p>
            <h2 id="crResenasTitle" class="cr-resenas__title">Lo que dicen nuestros clientes</h2>
            <div class="cr-resenas__score">
                <strong><?= htmlspecialchars($resenasPromedioLabel, ENT_QUOTES, 'UTF-8') ?></strong>
                <?= $crEstrellas((int)round($resenasPromedio)) ?>
                <span><?= (int)$resenasTotal ?> reseñas</span>
            </div>
        </header>

        <div id="crResenasSlider" class="splide cr-resenas-slider" aria-label="Reseñas de clientes">
            <div class="splide__track">
                <ul class="splide__list">
                    <?php foreach ($resenas as $resena): ?>
                    <li class="splide__slide">
                        <article class="cr-resena<?= !empty($resena['fijada']) ? ' cr-resena--fijada' : '' ?>">
                            <div class="cr-resena__top">
                                <span class="cr-resena__avatar" aria-hidden="true"><?= htmlspecialchars($resena['iniciales'], ENT_QUOTES, 'UTF-8') ?></span>
                                <div class="cr-resena__who">
                                    <p class="cr-resena__name"><?= htmlspecialchars($resena['nombre'], ENT_QUOTES, 'UTF-8') ?></p>
                                    <p class="cr-resena__city"><?= htmlspecialchars($resena['ciudad'], ENT_QUOTES, 'UTF-8') ?></p>
                                    <?= $crEstrellas((int)$resena['estrellas']) ?>
                                </div>
                                <?php if (!empty($resena['fijada'])): ?>
                                <span class="cr-resena__pin">Fijada</span>
                                <?php endif; ?>
                            </div>
                            <p class="cr-resena__text"><?= htmlspecialchars($resena['texto'], ENT_QUOTES, 'UTF-8') ?></p>
                        </article>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>

        <div class="cr-resenas__videos">
            <h3 class="cr-resenas__videos-title">Ganadores</h3>
            <div id="crVideosSlider" class="splide cr-videos-slider" aria-label="Videos de ganadores">
                <div class="splide__track">
                    <ul class="splide__list">
                        <?php foreach ($ganadores as $g): ?>
                        <li class="splide__slide">
                            <figure class="cr-resena-video">
                                <video class="cr-ganador-video" controls playsinline preload="metadata" poster="<?= htmlspecialchars($g['poster'], ENT_QUOTES, 'UTF-8') ?>" aria-label="<?= htmlspecialchars($g['label'], ENT_QUOTES, 'UTF-8') ?>">
                                    <source src="<?= htmlspecialchars($g['video'], ENT_QUOTES, 'UTF-8') ?>" type="video/mp4">
                                </video>
                            </figure>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</section>
