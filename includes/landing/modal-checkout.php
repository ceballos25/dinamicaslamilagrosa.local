<?php
$config = AppContainer::get()->config();
$checkoutTransferKey = trim((string)$config->get('transfer_breb_key', ''));
$checkoutTransferName = trim((string)$config->get('transfer_breb_name', ''));
$checkoutBancolombia = trim((string)$config->get('transfer_bancolombia', ''));
$checkoutBancolombiaName = trim((string)$config->get('transfer_bancolombia_name', $checkoutTransferName));
$checkoutNequi = trim((string)$config->get('transfer_nequi', ''));
$checkoutNequiName = trim((string)$config->get('transfer_nequi_name', $checkoutTransferName));
?>
    <div class="modal cr-checkout-sheet" id="modalCheckout" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-scrollable">
            <div class="modal-content border-0 overflow-hidden">

                <div class="cr-sheet-handle-wrap">
                    <div class="cr-sheet-handle" aria-hidden="true"></div>
                </div>

                <div class="cr-checkout-head">
                    <div class="cr-checkout-head__top">
                        <h5 class="cr-checkout-head__title">Completa tu compra</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>

                    <div class="cr-checkout-ticket" id="modalCheckoutTicket">
                        <div class="cr-checkout-ticket__row">
                            <div class="cr-checkout-ticket__meta">
                                <div class="cr-checkout-ticket__meta-top">
                                    <span class="cr-checkout-ticket__qty" id="modalCheckoutCantidad">0 nums</span>
                                    <span class="cr-checkout-promo-note d-none" id="checkoutPromoNote"></span>
                                </div>
                                <div class="cr-checkout-ticket__nums" id="checkoutNumerosChips"></div>
                            </div>
                            <span class="cr-checkout-ticket__total-value" id="resumenTotal">$0</span>
                        </div>
                    </div>
                </div>

                <div class="modal-body pt-0 flex-grow-1">
                    <form id="formCheckout" class="cr-checkout-form">
                        <input type="hidden" id="totalPagarInput" name="totalPagar">
                        <span class="d-none" id="resumenNumeros" aria-hidden="true"></span>

                        <section class="cr-checkout-panel">
                            <h6 class="cr-checkout-panel__title">
                                <span class="cr-checkout-panel__step">1</span>
                                Tus datos
                            </h6>

                            <div class="form-floating mb-2">
                                <input type="tel" class="form-control" id="celularCliente" required placeholder="Celular" autocomplete="tel" inputmode="numeric" pattern="[0-9]*" maxlength="10">
                                <label for="celularCliente">Celular (10 dígitos)</label>
                            </div>

                            <div class="row g-2 mb-2">
                                <div class="col-sm-6">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="nombreCliente" required placeholder="Nombre" autocomplete="given-name" autocapitalize="words">
                                        <label for="nombreCliente">Nombre</label>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="apellidoCliente" required placeholder="Apellido" autocomplete="family-name" autocapitalize="words">
                                        <label for="apellidoCliente">Apellido</label>
                                    </div>
                                </div>
                            </div>

                            <div class="form-floating mb-2">
                                <input type="email" class="form-control" id="emailCliente" required placeholder="Correo" autocomplete="email" inputmode="email" autocapitalize="none">
                                <label for="emailCliente">Correo electrónico</label>
                            </div>

                            <div class="row g-2">
                                <div class="col-sm-6">
                                    <div class="select-floating-label-group">
                                        <select class="form-select select2-ubicacion" id="departamento" required>
                                            <option value="">Departamento...</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="select-floating-label-group">
                                        <select class="form-select select2-ubicacion" id="ciudad" required>
                                            <option value="">Ciudad...</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section class="cr-checkout-panel">
                            <h6 class="cr-checkout-panel__title">
                                <span class="cr-checkout-panel__step">2</span>
                                Pago por transferencia
                            </h6>

                            <div id="contenedorMetodoPago" class="cr-checkout-pay-details">
                                <div id="metodoTransferencia" class="metodo-pago">
                                    <p class="cr-checkout-pay-hint">Transfiere el total exacto a una de estas cuentas y luego sube el comprobante:</p>
                                    <div class="cr-cuentas-grid">
                                        <?php if ($checkoutTransferKey !== ''): ?>
                                        <div class="cr-cuenta-card cr-cuenta-card--breb" role="button" tabindex="0" onclick="copiarTexto('breb', this.querySelector('.btn-copy-cuenta'))">
                                            <div class="cr-cuenta-card__head">
                                                <span class="cr-cuenta-card__bank"><i class="ti ti-bolt text-warning me-1"></i>Bre-B · Llave</span>
                                                <button type="button" class="btn btn-sm btn-copy-cuenta" onclick="event.stopPropagation(); copiarTexto('breb', this)"><i class="ti ti-copy me-1"></i>Copiar</button>
                                            </div>
                                            <span class="cr-cuenta-card__num" id="breb"><?= htmlspecialchars($checkoutTransferKey, ENT_QUOTES, 'UTF-8') ?></span>
                                            <span class="cr-cuenta-card__name" id="brebName"><?= htmlspecialchars($checkoutTransferName, ENT_QUOTES, 'UTF-8') ?></span>
                                        </div>
                                        <?php endif; ?>
                                        <?php if ($checkoutBancolombia !== ''): ?>
                                        <div class="cr-cuenta-card cr-cuenta-card--bancolombia" role="button" tabindex="0" onclick="copiarTexto('bancolombia', this.querySelector('.btn-copy-cuenta'))">
                                            <div class="cr-cuenta-card__head">
                                                <span class="cr-cuenta-card__bank"><i class="ti ti-building-bank text-primary me-1"></i>Bancolombia · Ahorros</span>
                                                <button type="button" class="btn btn-sm btn-copy-cuenta" onclick="event.stopPropagation(); copiarTexto('bancolombia', this)"><i class="ti ti-copy me-1"></i>Copiar</button>
                                            </div>
                                            <span class="cr-cuenta-card__num" id="bancolombia"><?= htmlspecialchars($checkoutBancolombia, ENT_QUOTES, 'UTF-8') ?></span>
                                            <span class="cr-cuenta-card__name"><?= htmlspecialchars($checkoutBancolombiaName, ENT_QUOTES, 'UTF-8') ?></span>
                                        </div>
                                        <?php endif; ?>
                                        <?php if ($checkoutNequi !== ''): ?>
                                        <div class="cr-cuenta-card cr-cuenta-card--nequi" role="button" tabindex="0" onclick="copiarTexto('nequi', this.querySelector('.btn-copy-cuenta'))">
                                            <div class="cr-cuenta-card__head">
                                                <span class="cr-cuenta-card__bank"><i class="ti ti-device-mobile text-danger me-1"></i>Nequi</span>
                                                <button type="button" class="btn btn-sm btn-copy-cuenta" onclick="event.stopPropagation(); copiarTexto('nequi', this)"><i class="ti ti-copy me-1"></i>Copiar</button>
                                            </div>
                                            <span class="cr-cuenta-card__num" id="nequi"><?= htmlspecialchars($checkoutNequi, ENT_QUOTES, 'UTF-8') ?></span>
                                            <span class="cr-cuenta-card__name"><?= htmlspecialchars($checkoutNequiName, ENT_QUOTES, 'UTF-8') ?></span>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <label class="cr-upload-zone" id="comprobantePagoZone" for="comprobantePago">
                                        <i class="ti ti-cloud-upload" id="comprobantePagoIcon" aria-hidden="true"></i>
                                        <span class="cr-upload-zone__label" id="comprobantePagoLabel">Toca para subir tu comprobante</span>
                                        <span class="cr-upload-zone__status d-none" id="comprobantePagoStatus">Listo para enviar</span>
                                        <span class="cr-upload-zone__hint" id="comprobantePagoHint">JPG, PNG o captura de pantalla</span>
                                    </label>
                                    <input type="file" class="visually-hidden" id="comprobantePago" accept="image/*">
                                </div>
                            </div>
                        </section>
                    </form>
                </div>

                <div class="cr-checkout-footer" id="checkoutFooterActions">
                    <div id="footerMetodoTransferencia" class="cr-checkout-footer__action">
                        <button type="button" class="btn btn-warning btn-cta-pago w-100 fw-bold" id="btnConfirmarTransferencia" onclick="procesarTransferencia(event)">
                            Confirmar pago
                        </button>
                        <div class="cr-checkout-wa-rescue text-center mt-2 pt-1">
                            <span class="small text-muted d-block mb-1">¿Prefieres pagar o enviar tu comprobante por WhatsApp?</span>
                            <button type="button" class="btn btn-whatsapp-checkout w-100 d-inline-flex align-items-center justify-content-center gap-2 fw-bold py-2" id="btnCheckoutWhatsAppModal" onclick="abrirWhatsAppCheckout(event)">
                                <i class="ti ti-brand-whatsapp fs-5" aria-hidden="true"></i> Prefiero pagar por WhatsApp
                            </button>
                        </div>
                    </div>
                    <p class="cr-checkout-footer__trust">
                        <i class="ti ti-lock-square-rounded text-success" aria-hidden="true"></i>
                        Pago seguro · Confirmación inmediata
                    </p>
                </div>

            </div>
        </div>
    </div>
