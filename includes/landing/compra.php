<section id="compra" class="py-4 border-top">
        <div class="container">

            <h2 class="cr-section-title mb-3">Elige tus números</h2>

            <div class="row g-4">

                <div class="col-lg-8">
                    <?php
                    $crGrillaWrapperClass = 'card border-0 shadow-sm cr-grilla-panel';
                    include ROOT_PATH . '/includes/components/cr-grilla-manual.php';
                    ?>
                </div>

                <div class="col-lg-4 d-none d-lg-block">
                    <div class="card border-0 shadow-sm cr-checkout-sidebar sticky-top">
                        <div class="card-body p-4">
                            <h5 class="fw-bold mb-3">Tu compra</h5>

                            <div id="listaTicketsDesktop" class="cr-checkout-sidebar__nums mb-3"></div>

                            <div class="cr-checkout-sidebar__summary">
                                <div class="cr-checkout-sidebar__row">
                                    <span class="text-muted">Cantidad</span>
                                    <strong id="cantTicketsDesktop">0</strong>
                                </div>
                                <div class="cr-checkout-sidebar__row cr-checkout-sidebar__row--total">
                                    <span>Total</span>
                                    <strong class="cr-checkout-sidebar__total" id="totalDineroDesktop">$0</strong>
                                </div>
                                <p class="cr-checkout-promo-note cr-checkout-sidebar__promo d-none mb-0" id="sidebarPromoNote"></p>
                            </div>

                            <button type="button" class="btn btn-warning btn-cta-pago w-100 fw-bold mt-3" onclick="abrirCheckout()" id="btnPagarDesktop" disabled>
                                Continuar al pago
                            </button>

                            <p class="cr-checkout-sidebar__trust small text-muted text-center mt-3 mb-0">
                                <i class="ti ti-lock-square-rounded text-success"></i>
                                Pago seguro · Confirmación inmediata
                            </p>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </section>
