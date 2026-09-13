<?php
require_once "../config/config.php";
adminRequirePermission('transferencias', 'view');
$page_title = "Gestión de Transferencias";
include_once ROOT_PATH . "/includes/head.php";
?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div class="page-wrapper" id="main-wrapper" data-layout="vertical" data-sidebartype="full">
    <?php include_once ROOT_PATH . "/includes/sidebar.php" ?>

    <div class="body-wrapper">
        <?php include_once ROOT_PATH . "/includes/header.php" ?>

        <div class="body-wrapper-inner">
            <div class="container-fluid transfer-page py-3">

                <div class="transfer-page-header mb-3">
                    <div>
                        <h2 class="mb-1 fw-bold"><i class="ti ti-building-bank me-1"></i>Transferencias web</h2>
                        <p class="text-muted small mb-0 d-none d-md-block">Revisa comprobantes pendientes, aprueba compras y asigna números.</p>
                    </div>
                    <span id="transferTotalBadge" class="badge transfer-total-badge d-none">0</span>
                </div>

                <div class="card border-0 shadow-sm mb-3 transfer-filters-card">
                    <div class="card-body py-3">
                        <div class="transfer-status-tabs mb-3" role="tablist" aria-label="Estado de transferencias">
                            <button type="button" class="transfer-status-tab is-active" data-status="1" role="tab" aria-selected="true">
                                <i class="ti ti-clock me-1"></i> Pendientes
                            </button>
                            <button type="button" class="transfer-status-tab" data-status="2" role="tab" aria-selected="false">
                                <i class="ti ti-circle-check me-1"></i> Aprobadas
                            </button>
                            <button type="button" class="transfer-status-tab" data-status="3" role="tab" aria-selected="false">
                                <i class="ti ti-circle-x me-1"></i> Rechazadas
                            </button>
                        </div>
                        <select id="filterTransferStatus" class="d-none" aria-hidden="true" tabindex="-1">
                            <option value="1" selected>Pendientes</option>
                            <option value="2">Aprobadas</option>
                            <option value="3">Rechazadas</option>
                        </select>

                        <div class="row g-2 align-items-end" autocomplete="off">
                            <?php /* Señuelos: evitan que el gestor de contraseñas rellene el buscador con el usuario de login */ ?>
                            <div class="visually-hidden" aria-hidden="true">
                                <input type="text" name="fake_user" tabindex="-1" autocomplete="username">
                                <input type="password" name="fake_pass" tabindex="-1" autocomplete="current-password">
                            </div>
                            <div class="col-12 col-lg-5">
                                <label class="form-label small fw-bold mb-1" for="searchTransfer">Buscar</label>
                                <div class="input-group input-group-sm transfer-search-group">
                                    <span class="input-group-text"><i class="ti ti-search"></i></span>
                                    <input type="text" id="searchTransfer" name="admin_transfer_q"
                                        class="form-control transfer-search-input"
                                        placeholder="Nombre, celular, código o ref. comprobante"
                                        autocomplete="off" autocapitalize="off" autocorrect="off"
                                        spellcheck="false" enterkeyhint="search" readonly
                                        onfocus="this.removeAttribute('readonly')"
                                        data-lpignore="true" data-1p-ignore="true" data-form-type="other"
                                        role="searchbox">
                                </div>
                            </div>
                            <div class="col-6 col-lg-3">
                                <label class="form-label small fw-bold mb-1" for="fecha_inicio">Desde</label>
                                <input type="date" id="fecha_inicio" class="form-control form-control-sm transfer-date-input">
                            </div>
                            <div class="col-6 col-lg-3">
                                <label class="form-label small fw-bold mb-1" for="fecha_fin">Hasta</label>
                                <input type="date" id="fecha_fin" class="form-control form-control-sm transfer-date-input">
                            </div>
                            <div class="col-12 col-lg-4">
                                <label class="form-label small fw-bold mb-1 d-none d-lg-block">&nbsp;</label>
                                <div class="transfer-quick-actions d-grid d-lg-flex gap-2">
                                    <button type="button" class="btn btn-primary btn-sm flex-fill" onclick="filtrarTransferenciasHoy()">
                                        <i class="ti ti-calendar-event me-1"></i> Hoy
                                    </button>
                                    <button type="button" class="btn btn-outline-primary btn-sm flex-fill" onclick="actualizarTransferencias()" id="btnRefreshTransfer" title="Recargar listado">
                                        <i class="ti ti-refresh me-1"></i> Actualizar
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm flex-fill" onclick="limpiarFiltrosTransfer()">
                                        <i class="ti ti-filter-off me-1"></i> Limpiar
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="transferCola" class="transfer-cola">
                    <div class="text-center py-5 text-muted">Cargando…</div>
                </div>

                <div class="card border-0 shadow-sm mt-3">
                    <div class="card-footer bg-white border-top py-3 rounded-bottom">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <small class="text-muted" id="infoPaginacionTransfer"></small>
                            <nav><ul class="pagination pagination-sm mb-0" id="contenedorPaginacionTransfer"></ul></nav>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalComprobante" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="ti ti-photo me-1"></i> Comprobante</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body text-center p-2 bg-light" id="cuerpoComprobante"></div>
            <div class="modal-footer d-none" id="modalComprobanteActions"></div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalRecibo" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Recibo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body" id="cuerpoRecibo"></div>
        </div>
    </div>
</div>

<?php
$extra_js = '<script src="' . ASSETS_URL . '/js/admin-mobile.js?v=16"></script><script src="' . ASSETS_URL . '/js/transferencias.js?v=29"></script>';
include_once ROOT_PATH . "/includes/footer.php";
?>
