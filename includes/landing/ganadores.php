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
        'poster_webp' => ASSETS_URL . '/videos/ganadora.webp',
        'label' => 'Ganadora',
    ],
    [
        'video' => ASSETS_URL . '/videos/ganador.mp4',
        'poster' => ASSETS_URL . '/videos/ganador.jpg',
        'poster_webp' => ASSETS_URL . '/videos/ganador.webp',
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

        <div class="cr-comunidad">
            <a class="cr-comunidad__btn" href="https://chat.whatsapp.com/GMyvIbj5fNbHNsremVNgAB?s=sh&amp;p=i&amp;mlu=4&amp;ilr=4&amp;iam=2" target="_blank" rel="noopener noreferrer">
                <span class="cr-comunidad__icon" aria-hidden="true">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.435 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                </span>
                <span class="cr-comunidad__copy">
                    <strong>Únete a nuestra comunidad</strong>
                    <small>Únete a nuestras dinámicas diarias</small>
                </span>
            </a>
        </div>

        <div class="cr-resenas__videos">
            <h3 class="cr-resenas__videos-title">Ganadores</h3>
            <div id="crVideosSlider" class="splide cr-videos-slider" aria-label="Videos de ganadores">
                <div class="splide__track">
                    <ul class="splide__list">
                        <?php foreach ($ganadores as $g): ?>
                        <li class="splide__slide">
                            <figure class="cr-resena-video">
                                <video
                                    class="cr-ganador-video"
                                    controls
                                    playsinline
                                    preload="none"
                                    data-poster="<?= htmlspecialchars($g['poster'], ENT_QUOTES, 'UTF-8') ?>?v=3"
                                    data-src="<?= htmlspecialchars($g['video'], ENT_QUOTES, 'UTF-8') ?>?v=3"
                                    aria-label="<?= htmlspecialchars($g['label'], ENT_QUOTES, 'UTF-8') ?>"
                                ></video>
                            </figure>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</section>
