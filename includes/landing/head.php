<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['csrf_token'])): ?>
    <meta name="csrf-token" content="<?= htmlspecialchars((string)$_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>
    <?php
    $landingSiteName = $landingBrandName ?? (defined('SITE_NAME') && SITE_NAME ? (string)SITE_NAME : 'Dinámicas La Milagrosa');
    $landingPageTitle = $landingPageTitle ?? $landingSiteName;
    $landingMetaDesc = function_exists('landing_setting')
        ? landing_setting('landing_meta_description', $landingSiteName)
        : $landingSiteName;
    $heroPreload = ASSETS_URL . '/images/portada-palitos-sala-480.webp?v=6';
    ?>
    <title><?= htmlspecialchars($landingPageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <meta name="description"
        content="<?= htmlspecialchars($landingMetaDesc, ENT_QUOTES, 'UTF-8') ?>">
    <link rel="icon" type="image/png" href="<?= cr_site_favicon_href() ?>" data-site-favicon>
    <link rel="shortcut icon" type="image/png" href="<?= cr_site_favicon_href() ?>" data-site-favicon>

    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preload" as="image" type="image/webp" href="<?= htmlspecialchars($heroPreload, ENT_QUOTES, 'UTF-8') ?>" media="(max-width: 767px)">
    <link rel="preload" as="image" type="image/webp" href="<?= htmlspecialchars(ASSETS_URL . '/images/portada-palitos-sala.webp?v=6', ENT_QUOTES, 'UTF-8') ?>" media="(min-width: 768px)">
    <link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap" media="print" onload="this.media='all'">
    <noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap"></noscript>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <?php // URL correcta: /dist/... (la anterior 404). Font ~830KB → diferido, no bloquea paint. ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.34.0/dist/tabler-icons.min.css" crossorigin="anonymous" media="print" onload="this.media='all'">
    <noscript><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.34.0/dist/tabler-icons.min.css" crossorigin="anonymous"></noscript>
    <link href="https://cdn.jsdelivr.net/npm/@splidejs/splide@4.1.4/dist/css/splide.min.css" rel="stylesheet" crossorigin="anonymous" media="print" onload="this.media='all'">
    <noscript><link href="https://cdn.jsdelivr.net/npm/@splidejs/splide@4.1.4/dist/css/splide.min.css" rel="stylesheet" crossorigin="anonymous"></noscript>
    <link rel="stylesheet" href="<?= htmlspecialchars(ASSETS_URL . '/css/app.css', ENT_QUOTES, 'UTF-8') ?>?v=140">

    <?php cs_meta_pixel_head(); ?>
</head>
