<?php
$landingNavHome = $landingNavHome ?? (defined('BASE_URL') && BASE_URL !== '' ? rtrim((string) BASE_URL, '/') : '/');
$landingNavLogo = $landingNavLogo ?? (defined('LOGO_NAV_URL') ? LOGO_NAV_URL : (ASSETS_URL . '/images/logo.png'));
?>
<nav class="navbar navbar-expand-lg navbar-custom shadow-sm cr-landing-nav-sticky">
    <div class="container d-flex align-items-center justify-content-between gap-2">
        <a class="navbar-brand d-flex align-items-center gap-2 logo-principal" href="<?= htmlspecialchars($landingNavHome, ENT_QUOTES, 'UTF-8') ?>">
            <img src="<?= htmlspecialchars($landingNavLogo, ENT_QUOTES, 'UTF-8') ?>" alt="Combo Stickers" data-site-logo loading="lazy">
        </a>
        <span class="badge cr-nav-badge d-none" id="landingNavEventBadge">
            <i class="ti ti-calendar-event me-1"></i>
            <span id="landingNavEventText"></span>
        </span>
    </div>
</nav>
