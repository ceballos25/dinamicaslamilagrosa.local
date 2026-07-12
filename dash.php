<?php
require_once "config/config.php";

$error = $_GET['error'] ?? '';
$detail = $_GET['detail'] ?? '';

$messages = [
    'missing' => '⚠️ Completa usuario y contraseña.',
    'bad_credentials' => '❌ Usuario o contraseña incorrectos.',
    'session_expired' => '⏱️ Tu sesión ha expirado. Ingresa nuevamente.',
];

$msg = $messages[$error] ?? '';
if ($detail) {
    $msg .= "<br><small class='text-muted'>" . htmlspecialchars($detail) . "</small>";
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - <?= SITE_NAME ?></title> 
    <link rel="shortcut icon" type="image/png" href="<?= htmlspecialchars(LOGO_NAV_URL, ENT_QUOTES, 'UTF-8') ?>" />
    <link rel="stylesheet" href="<?= htmlspecialchars(ASSETS_URL, ENT_QUOTES, 'UTF-8') ?>/css/brand.css?v=40" />
    <link rel="stylesheet" href="<?= htmlspecialchars(ASSETS_URL, ENT_QUOTES, 'UTF-8') ?>/css/app.css?v=72" />
    <link rel="stylesheet" href="<?= htmlspecialchars(ASSETS_URL, ENT_QUOTES, 'UTF-8') ?>/css/styles.min.css" />
</head>
<body>
    <div class="page-wrapper" id="main-wrapper" data-layout="vertical" data-navbarbg="skin6" data-sidebartype="full"
        data-sidebar-position="fixed" data-header-position="fixed">
        <div class="position-relative overflow-hidden text-bg-light min-vh-100 d-flex align-items-center justify-content-center">
            <div class="d-flex align-items-center justify-content-center w-100">
                <div class="row justify-content-center w-100">
                    <div class="col-md-8 col-lg-6 col-xxl-3">
                        <div class="card mb-0">
                            <div class="card-body">
                                <div class="text-center py-3 mb-2">
                                    <a href="/" class="logo-img d-inline-block">
                                        <img src="<?= htmlspecialchars(LOGO_NAV_URL, ENT_QUOTES, 'UTF-8') ?>" width="200" alt="<?= SITE_NAME ?>" class="img-fluid">
                                    </a>
                                </div>
                                <?php if ($msg): ?>
                                    <div class="alert alert-danger" role="alert">
                                        <?= $msg ?>
                                    </div>
                                <?php endif; ?>
                                <form action="functions/login.php" method="POST" autocomplete="off">
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Usuario</label>
                                        <input type="text" value="" class="form-control" id="email" name="email" required>
                                    </div>

                                    <div class="mb-4">
                                        <label for="password" class="form-label">Contraseña</label>
                                        <div class="auth-password-wrap">
                                            <input type="password" value="" class="form-control" id="password" name="password" required autocomplete="current-password">
                                            <button type="button" class="auth-password-wrap__btn" id="togglePassword" aria-label="Mostrar contraseña" aria-pressed="false" aria-controls="password">
                                                <svg class="auth-password-wrap__icon auth-password-wrap__icon--show" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                                    <circle cx="12" cy="12" r="3"></circle>
                                                </svg>
                                                <svg class="auth-password-wrap__icon auth-password-wrap__icon--hide d-none" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                    <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"></path>
                                                    <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"></path>
                                                    <line x1="1" y1="1" x2="23" y2="23"></line>
                                                </svg>
                                            </button>
                                        </div>
                                    </div>

                                    <button type="submit" class="btn btn-primary w-100 py-8 fs-4 mb-4 rounded-2">
                                        Ingresar
                                    </button>
                                </form>

                                <span class="d-flex justify-content-center"><small>Version 6.0.0</small></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="<?= htmlspecialchars(ASSETS_URL, ENT_QUOTES, 'UTF-8') ?>/libs/jquery/dist/jquery.min.js"></script>
    <script src="<?= htmlspecialchars(ASSETS_URL, ENT_QUOTES, 'UTF-8') ?>/libs/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    (function () {
        const input = document.getElementById('password');
        const btn = document.getElementById('togglePassword');
        if (!input || !btn) return;

        const iconShow = btn.querySelector('.auth-password-wrap__icon--show');
        const iconHide = btn.querySelector('.auth-password-wrap__icon--hide');

        btn.addEventListener('click', function () {
            const visible = input.type === 'text';
            input.type = visible ? 'password' : 'text';
            btn.setAttribute('aria-pressed', visible ? 'false' : 'true');
            btn.setAttribute('aria-label', visible ? 'Mostrar contraseña' : 'Ocultar contraseña');
            iconShow.classList.toggle('d-none', !visible);
            iconHide.classList.toggle('d-none', visible);
        });
    })();
    </script>
</body>
</html>