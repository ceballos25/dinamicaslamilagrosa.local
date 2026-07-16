<?php
require_once "../config/config.php";
adminRequirePermission('rifas', 'view');
$page_title = "Gestión de Números";
$extra_css = '
<style>
  .numeros-swal-html {
    margin: 0.5rem 0 0 !important;
    padding: 0 0 0.15rem !important;
    overflow: visible;
  }
  .numeros-swal-html p:last-of-type {
    margin-bottom: 0.5rem !important;
  }
  .numeros-swal-chip {
    display: flex;
    justify-content: center;
    margin: 0;
    padding: 0;
  }
  .numeros-swal-chip .cr-numero-chip {
    margin: 0 !important;
  }
  .numeros-swal-actions {
    margin-top: 0.75rem !important;
    padding-top: 0 !important;
  }
  .swal2-popup:has(.numeros-swal-html) {
    padding-bottom: 1.25rem !important;
  }
</style>';
include_once ROOT_PATH . "/includes/head.php";
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div class="page-wrapper" id="main-wrapper" data-layout="vertical" data-sidebartype="full">
    <?php include_once ROOT_PATH . "/includes/sidebar.php"; ?>

    <div class="body-wrapper">
        <?php include_once ROOT_PATH . "/includes/header.php"; ?>

        <div class="body-wrapper-inner">
            <div class="container-fluid" style="padding-top: 20px;">

                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="mb-0 fw-bold"><i class="ti ti-list-numbers me-1"></i>Gestión de Números</h2>
                </div>

                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body py-3">
                        <div class="row g-2 align-items-end">
                            <div class="col-md-3">
                                <label class="form-label small fw-bold">Rifa</label>
                                <select id="filterRifa" class="form-select form-select-sm">
                                    </select>
                            </div>

                            <div class="col-md-2">
                                <label class="form-label small fw-bold">Estado</label>
                                <select id="filterEstado" class="form-select form-select-sm">
                                    <option value="">Todos</option>
                                    <option value="0">Disponibles</option>
                                    <option value="1">Vendidos</option>
                                    <option value="2">Reservados</option>
                                </select>
                            </div>

                            <div class="col-md-2">
                                <label class="form-label small fw-bold">Premium</label>
                                <select id="filterPremium" class="form-select form-select-sm">
                                    <option value="">Todos</option>
                                    <option value="1">Solo premium</option>
                                    <option value="0">No premium</option>
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label small fw-bold" for="searchNumeros">Buscar número</label>
                                <div class="visually-hidden" aria-hidden="true">
                                    <input type="text" name="fake_user" tabindex="-1" autocomplete="username">
                                    <input type="password" name="fake_pass" tabindex="-1" autocomplete="current-password">
                                </div>
                                <input type="text" id="searchNumeros" name="admin_numeros_q"
                                    class="form-control form-control-sm"
                                    placeholder="Ej: 197" inputmode="numeric" pattern="[0-9]*" maxlength="10"
                                    autocomplete="off" autocapitalize="off" spellcheck="false"
                                    readonly onfocus="this.removeAttribute('readonly')"
                                    data-lpignore="true" data-1p-ignore="true" data-form-type="other"
                                    role="searchbox">
                            </div>

                            <div class="col-md-2">
                                <button class="btn btn-outline-secondary btn-sm w-100" onclick="limpiarFiltrosNumeros()">
                                    <i class="ti ti-refresh"></i> Recargar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm">
                    <div class="card-body p-0">
                        <div class="table-responsive admin-table-cards-wrap admin-table-numeros-wrap">
                            <table class="table table-hover align-middle mb-0 table-admin-cards table-numeros">
                                <thead class="table-light sticky-top" style="z-index: 10;">
                                    <tr>
                                        <th class="ps-3 text-start">Número</th>
                                        <th class="d-none d-lg-table-cell text-center">Estado</th>
                                        <th class="d-none d-lg-table-cell text-center">Premium</th>
                                        <th class="text-end pe-3">Acción</th>
                                    </tr>
                                </thead>
                                <tbody id="bodyTablaNumeros">
                                    <tr><td colspan="4" class="text-center py-5 text-muted">Cargando...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <div class="card-footer bg-white border-top py-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <small class="text-muted" id="infoPaginacion"></small>
                            <nav><ul class="pagination pagination-sm mb-0" id="contenedorPaginacion"></ul></nav>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<?php
$extra_js = '<script src="' . ASSETS_URL . '/js/admin-mobile.js?v=23"></script><script src="' . ASSETS_URL . '/js/numeros.js?v=12"></script>';
include_once ROOT_PATH . "/includes/footer.php";
?>