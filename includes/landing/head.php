<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['csrf_token'])): ?>
    <meta name="csrf-token" content="<?= htmlspecialchars((string)$_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>
    <title>Combo Stickers 🍀</title>
    <meta name="description"
        content="Combo Stickers — Gana $4.000.000 con las 3 últimas de Medellín. 3 cifras, $10.000 c/u. Compra segura por transferencia desde Colombia.">
    <link rel="icon" type="image/png" href="<?= cr_site_favicon_href() ?>" data-site-favicon>
    <link rel="shortcut icon" type="image/png" href="<?= cr_site_favicon_href() ?>" data-site-favicon>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/@splidejs/splide@4.1.4/dist/css/splide.min.css" rel="stylesheet" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css" crossorigin="anonymous">
    <link rel="stylesheet" href="<?= htmlspecialchars(ASSETS_URL . '/css/app.css', ENT_QUOTES, 'UTF-8') ?>?v=77">

    <?php cs_meta_pixel_head(); ?>
</head>
