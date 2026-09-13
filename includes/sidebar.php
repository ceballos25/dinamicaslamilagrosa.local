<?php
$currentPage = basename(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

function isActive($fileName, $currentPage) {
  return $currentPage === $fileName ? 'active' : '';
}
function isOpen($files, $currentPage) {
  return in_array($currentPage, $files, true) ? 'in' : '';
}

$canVender = adminCan('ventas', 'create');
$canVerVentas = adminCan('ventas', 'view');
$canVerInventario = adminCan('rifas', 'view');
$canTransferencias = adminCan('transferencias', 'view');
$canClientes = adminCan('clientes', 'view');
$canRifas = adminCan('rifas', 'view');
$canUsuarios = adminCan('usuarios', 'view') || adminCan('roles', 'view');
$canAuditoria = adminCan('auditoria', 'view');
$canSettings = adminCan('settings', 'manage');
$canReportes = adminCan('reportes', 'view');

$ventasPages = ['ventas.php', 'numeros-vendidos.php', 'numeros.php'];
$showVentasMenu = $canVerVentas || $canVerInventario;
$showPrincipalSection = adminCan('dashboard', 'view') || $canVender || $canTransferencias;
$showTercerosSection = $canClientes;
$showConfigSection = $canRifas || $canUsuarios || $canAuditoria || $canSettings || $canReportes;
?>

<aside class="left-sidebar">
  <div class="sidebar-shell">
    <div class="brand-logo d-flex align-items-center justify-content-between">
      <a href="dashboard.php" class="text-nowrap logo-img" style="display:flex; justify-content:center; width:100%;">
        <img class="d-flex sidebar-brand-logo" data-site-logo src="<?= htmlspecialchars(LOGO_NAV_URL, ENT_QUOTES, 'UTF-8') ?>" alt="<?php echo SITE_NAME; ?>" loading="lazy" />
      </a>
      <div class="close-btn d-xl-none d-block sidebartoggler cursor-pointer" id="sidebarCollapse">
        <i class="ti ti-x fs-6"></i>
      </div>
    </div>

    <nav class="sidebar-nav scroll-sidebar">
      <ul id="sidebarnav">

        <?php if ($showPrincipalSection): ?>
        <li class="nav-small-cap">
          <iconify-icon icon="solar:menu-dots-linear" class="nav-small-cap-icon fs-4"></iconify-icon>
          <span class="hide-menu">Principal</span>
        </li>

        <?php if (adminCan('dashboard', 'view')): ?>
        <li class="sidebar-item <?= isActive('dashboard.php', $currentPage); ?>">
          <a class="sidebar-link" href="dashboard.php" aria-expanded="false">
            <i class="ti ti-home"></i>
            <span class="hide-menu">Dashboard</span>
          </a>
        </li>
        <?php endif; ?>

        <?php if ($canVender): ?>
        <li class="sidebar-item <?= isActive('vender.php', $currentPage); ?>">
          <a class="sidebar-link" href="vender.php" aria-expanded="false">
            <i class="ti ti-shopping-cart"></i>
            <span class="hide-menu">Vender</span>
          </a>
        </li>
        <?php endif; ?>

        <?php if ($canTransferencias): ?>
        <li class="sidebar-item <?= isActive('transferencias.php', $currentPage); ?>">
          <a class="sidebar-link" href="transferencias.php" aria-expanded="false">
            <i class="ti ti-building-bank"></i>
            <span class="hide-menu">Transferencias</span>
          </a>
        </li>
        <?php endif; ?>

        <?php if (adminCan('dashboard', 'view')): ?>
        <li class="sidebar-item <?= isActive('tracking.php', $currentPage); ?>">
          <a class="sidebar-link" href="tracking.php" aria-expanded="false">
            <i class="ti ti-activity"></i>
            <span class="hide-menu">Tracking Meta</span>
          </a>
        </li>
        <?php endif; ?>
        <?php endif; ?>

        <?php if ($showTercerosSection): ?>
        <li><span class="sidebar-divider lg"></span></li>

        <li class="nav-small-cap">
          <iconify-icon icon="solar:menu-dots-linear" class="nav-small-cap-icon fs-4"></iconify-icon>
          <span class="hide-menu">Terceros</span>
        </li>

        <li class="sidebar-item <?= isActive('clientes.php', $currentPage); ?>">
          <a class="sidebar-link" href="clientes.php" aria-expanded="false">
            <i class="ti ti-users"></i>
            <span class="hide-menu">Clientes</span>
          </a>
        </li>
        <?php endif; ?>

        <?php if ($showVentasMenu): ?>
        <li><span class="sidebar-divider lg"></span></li>

        <li class="nav-small-cap">
          <iconify-icon icon="solar:menu-dots-linear" class="nav-small-cap-icon fs-4"></iconify-icon>
          <span class="hide-menu">Ventas y Números</span>
        </li>

        <li class="sidebar-item">
          <a class="sidebar-link justify-content-between has-arrow" href="javascript:void(0)" aria-expanded="false">
            <div class="d-flex align-items-center gap-3">
              <span class="d-flex"><i class="ti ti-box"></i></span>
              <span class="hide-menu">Ventas & Números</span>
            </div>
          </a>

          <ul aria-expanded="false" class="collapse first-level <?= isOpen($ventasPages, $currentPage); ?>">
            <?php if ($canVerVentas): ?>
            <li class="sidebar-item <?= isActive('ventas.php', $currentPage); ?>">
              <a class="sidebar-link" href="ventas.php">
                <div class="round-16 d-flex align-items-center justify-content-center"><i class="ti ti-circle"></i></div>
                <span class="hide-menu">Ventas</span>
              </a>
            </li>

            <li class="sidebar-item <?= isActive('numeros-vendidos.php', $currentPage); ?>">
              <a class="sidebar-link" href="numeros-vendidos.php">
                <div class="round-16 d-flex align-items-center justify-content-center"><i class="ti ti-circle"></i></div>
                <span class="hide-menu">Números Vendidos</span>
              </a>
            </li>
            <?php endif; ?>

            <?php if ($canVerInventario): ?>
            <li class="sidebar-item <?= isActive('numeros.php', $currentPage); ?>">
              <a class="sidebar-link" href="numeros.php">
                <div class="round-16 d-flex align-items-center justify-content-center"><i class="ti ti-circle"></i></div>
                <span class="hide-menu">Números Disponibles</span>
              </a>
            </li>
            <?php endif; ?>
          </ul>
        </li>
        <?php endif; ?>

        <?php if ($showConfigSection): ?>
        <li><span class="sidebar-divider lg"></span></li>

        <li class="nav-small-cap">
          <iconify-icon icon="solar:menu-dots-linear" class="nav-small-cap-icon fs-4"></iconify-icon>
          <span class="hide-menu">Configuración</span>
        </li>

        <?php if ($canRifas): ?>
        <li class="sidebar-item <?= isActive('rifas.php', $currentPage); ?>">
          <a class="sidebar-link" href="rifas.php" aria-expanded="false">
            <i class="ti ti-ticket"></i>
            <span class="hide-menu">Rifas</span>
          </a>
        </li>
        <?php endif; ?>

        <?php if ($canUsuarios): ?>
        <li class="sidebar-item <?= isActive('usuarios.php', $currentPage); ?>">
          <a class="sidebar-link" href="usuarios.php" aria-expanded="false">
            <i class="ti ti-shield-lock"></i>
            <span class="hide-menu">Usuarios</span>
          </a>
        </li>
        <?php endif; ?>

        <?php if ($canAuditoria): ?>
        <li class="sidebar-item <?= isActive('auditoria.php', $currentPage); ?>">
          <a class="sidebar-link" href="auditoria.php" aria-expanded="false">
            <i class="ti ti-history"></i>
            <span class="hide-menu">Auditoría</span>
          </a>
        </li>
        <?php endif; ?>

        <?php if ($canSettings): ?>
        <li class="sidebar-item <?= isActive('settings.php', $currentPage); ?>">
          <a class="sidebar-link" href="settings.php" aria-expanded="false">
            <i class="ti ti-settings"></i>
            <span class="hide-menu">Ajustes</span>
          </a>
        </li>
        <?php endif; ?>

        <?php if ($canReportes): ?>
        <li class="sidebar-item <?= isActive('reportes.php', $currentPage); ?>">
          <a class="sidebar-link" href="reportes.php" aria-expanded="false">
            <i class="ti ti-file-report"></i>
            <span class="hide-menu">Reportería</span>
          </a>
        </li>
        <?php endif; ?>
        <?php endif; ?>

        <li><span class="sidebar-divider lg"></span></li>

      </ul>
    </nav>
  </div>
</aside>
