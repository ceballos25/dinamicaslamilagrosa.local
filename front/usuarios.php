<?php
require_once '../config/config.php';

$canUsers = adminCan('usuarios', 'view');
$canRoles = adminCan('roles', 'view');
if (!$canUsers && !$canRoles) {
    adminRequirePermission('usuarios', 'view');
}

$page_title = 'Usuarios y permisos';
include_once ROOT_PATH . '/includes/head.php';
?>
<div class="page-wrapper" id="main-wrapper" data-layout="vertical">
<?php include_once ROOT_PATH . '/includes/sidebar.php'; ?>
<div class="body-wrapper">
<?php include_once ROOT_PATH . '/includes/header.php'; ?>
<div class="body-wrapper-inner">
<div class="container-fluid py-3">

  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
      <h2 class="fw-bold mb-1"><i class="ti ti-shield-lock me-1"></i> Usuarios y permisos</h2>
    </div>
    <div class="d-flex gap-2" id="usuariosToolbar"></div>
  </div>

  <ul class="nav nav-tabs mb-3" id="authTabs" role="tablist">
    <?php if ($canUsers): ?>
    <li class="nav-item" role="presentation">
      <button class="nav-link active" id="tab-usuarios-btn" data-bs-toggle="tab" data-bs-target="#tabUsuarios" type="button">Usuarios</button>
    </li>
    <?php endif; ?>
    <?php if ($canRoles): ?>
    <li class="nav-item" role="presentation">
      <button class="nav-link <?= $canUsers ? '' : 'active' ?>" id="tab-roles-btn" data-bs-toggle="tab" data-bs-target="#tabRoles" type="button">Roles y permisos</button>
    </li>
    <?php endif; ?>
  </ul>

  <div class="tab-content">
    <?php if ($canUsers): ?>
    <div class="tab-pane fade show active" id="tabUsuarios" role="tabpanel">
      <div class="card border-0 shadow-sm">
        <div class="table-responsive admin-table-desktop">
          <table class="table table-hover mb-0">
            <thead class="table-light">
              <tr>
                <th>ID</th>
                <th>Email</th>
                <th>Rol</th>
                <th>Estado</th>
                <th>Creado</th>
                <th class="text-end">Acciones</th>
              </tr>
            </thead>
            <tbody id="bodyUsuarios">
              <tr><td colspan="6" class="text-center py-4 text-muted">Cargando...</td></tr>
            </tbody>
          </table>
        </div>
        <div class="admin-cards-mobile p-3" id="usuariosMobile"></div>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($canRoles): ?>
    <div class="tab-pane fade <?= $canUsers ? '' : 'show active' ?>" id="tabRoles" role="tabpanel">
      <div class="card border-0 shadow-sm auth-roles-card">
        <div class="row g-0 auth-roles-split">
          <div class="col-lg-4 col-xl-3 border-end auth-roles-col-nav">
            <div class="auth-roles-nav-head">
              <span class="fw-bold text-dark">Roles</span>
            </div>
            <nav class="auth-roles-nav" id="listaRoles" aria-label="Roles">
              <div class="auth-roles-nav__placeholder text-muted">Cargando...</div>
            </nav>
          </div>
          <div class="col-lg-8 col-xl-9 auth-roles-col-detail">
            <div class="auth-roles-detail-head" id="rolDetailHead">
              <div>
                <h5 class="mb-0 fw-bold" id="tituloRolSeleccionado">—</h5>
                <p class="mb-0 small text-muted" id="subtituloRolSeleccionado">Elige un rol a la izquierda.</p>
              </div>
              <div class="auth-roles-detail-meta">
                <span class="small text-muted" id="badgePermCount" style="display:none"></span>
                <span class="badge rounded-pill text-bg-light border" id="badgeRolSistema" style="display:none">Sistema</span>
              </div>
            </div>
            <div class="auth-roles-detail-body" id="panelPermisos">
              <p class="text-muted mb-0 py-4 text-center small">Selecciona un rol para ver sus permisos.</p>
            </div>
          </div>
        </div>
      </div>
    </div>
    <?php endif; ?>
  </div>

</div>
</div>
</div>
</div>

<!-- Modal usuario -->
<div class="modal fade" id="modalUsuario" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="tituloModalUsuario">Usuario</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="usuarioId">
        <div class="mb-3">
          <label class="form-label">Email</label>
          <input type="email" id="usuarioEmail" class="form-control" autocomplete="username">
        </div>
        <div class="mb-3">
          <label class="form-label">Contraseña</label>
          <input type="password" id="usuarioPass" class="form-control" autocomplete="new-password" placeholder="Mínimo 8 caracteres">
          <div class="form-text" id="usuarioPassHelp">Obligatoria al crear. Al editar, déjala vacía para no cambiar.</div>
        </div>
        <div class="mb-3">
          <label class="form-label">Rol</label>
          <select id="usuarioRol" class="form-select"></select>
        </div>
        <div class="mb-0">
          <label class="form-label">Estado</label>
          <select id="usuarioEstado" class="form-select">
            <option value="1">Activo</option>
            <option value="0">Inactivo</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-primary" id="btnGuardarUsuario">Guardar</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal rol -->
<div class="modal fade" id="modalRol" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="tituloModalRol">Rol</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="rolId">
        <div class="mb-3">
          <label class="form-label">Nombre</label>
          <input type="text" id="rolNombre" class="form-control" placeholder="Ej. Supervisor">
        </div>
        <div class="mb-0">
          <label class="form-label">Identificador (slug)</label>
          <input type="text" id="rolSlug" class="form-control" placeholder="supervisor">
          <div class="form-text">Solo letras minúsculas, números y guión bajo.</div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-primary" id="btnGuardarRol">Guardar</button>
      </div>
    </div>
  </div>
</div>

<?php
$extra_js = '<script>window.USUARIOS_PAGE = ' . json_encode([
    'canUsers' => $canUsers,
    'canManageUsers' => adminCan('usuarios', 'manage'),
    'canRoles' => $canRoles,
    'canManageRoles' => adminCan('roles', 'manage'),
    'currentAdminId' => adminId(),
], JSON_UNESCAPED_UNICODE) . ';</script>'
    . '<script src="' . ASSETS_URL . '/js/admin-mobile.js?v=18"></script>'
    . '<script src="' . ASSETS_URL . '/js/usuarios.js?v=12"></script>';
include_once ROOT_PATH . '/includes/footer.php';
?>
