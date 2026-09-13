<?php
require_once "../config/config.php";
adminRequirePermission('dashboard', 'view');
$page_title = "Tracking";
include_once ROOT_PATH . "/includes/head.php";
?>
<div class="page-wrapper" id="main-wrapper" data-layout="vertical">
<?php include_once ROOT_PATH . "/includes/sidebar.php" ?>
<div class="body-wrapper">
<?php include_once ROOT_PATH . "/includes/header.php" ?>
<div class="body-wrapper-inner"><div class="container-fluid py-3">
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
  <div>
    <h2 class="fw-bold mb-1"><i class="ti ti-activity me-1"></i> Tracking</h2>
    <p class="text-muted small mb-0">Qué hace la gente en la landing: visitas, carrito y si llenó el formulario.</p>
  </div>
</div>

<div class="row g-2 mb-3 row-cols-2 row-cols-lg-5" id="funnelTracking"></div>

<div class="card border-0 shadow-sm mb-3"><div class="card-body">
  <div class="row g-2 align-items-end">
    <div class="col-6 col-md-2">
      <label class="form-label small fw-bold mb-1" for="filtroPeriodo">Periodo</label>
      <select id="filtroPeriodo" class="form-select form-select-sm">
        <option value="hoy">Hoy</option>
        <option value="ayer">Ayer</option>
        <option value="semana" selected>Esta semana</option>
        <option value="mes">Este mes</option>
        <option value="rango">Rango</option>
      </select>
    </div>
    <div class="col-6 col-md-3">
      <label class="form-label small fw-bold mb-1" for="filtroEvento">Evento</label>
      <select id="filtroEvento" class="form-select form-select-sm">
        <option value="">Todos los eventos</option>
      </select>
    </div>
    <div class="col-md-4">
      <label class="form-label small fw-bold mb-1">Desde — Hasta</label>
      <div class="input-group input-group-sm">
        <input type="date" id="filtroDesde" class="form-control">
        <input type="date" id="filtroHasta" class="form-control">
      </div>
    </div>
    <div class="col-md-2">
      <button class="btn btn-primary btn-sm w-100" type="button" onclick="cargarTracking(1)">Filtrar</button>
    </div>
  </div>
</div></div>

<div class="card border-0 shadow-sm">
  <div class="table-responsive admin-table-desktop">
    <table class="table table-sm table-hover mb-0">
      <thead class="table-light"><tr><th>Fecha</th><th>Evento</th><th>Detalle</th></tr></thead>
      <tbody id="bodyTracking"></tbody>
    </table>
  </div>
  <div class="admin-cards-mobile p-3" id="cardsTracking"></div>
  <div class="card-footer bg-white d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2">
    <small class="text-muted" id="infoTracking"></small>
    <nav><ul class="pagination pagination-sm mb-0" id="paginacionTracking"></ul></nav>
  </div>
</div>
</div></div></div></div>
<?php $extra_js = '<script src="' . ASSETS_URL . '/js/admin-mobile.js?v=4"></script><script src="' . ASSETS_URL . '/js/tracking.js?v=3"></script>'; include_once ROOT_PATH . "/includes/footer.php"; ?>
