let transferencias = [];
let paginaActual = 1;
let totalTransferencias = 0;
const registrosPorPagina = 10;
const TRANSFER_STATUS_PENDIENTE = 1;
const TRANSFER_STATUS_APROBADA = 2;
const TRANSFER_STATUS_RECHAZADA = 3;

const TRANSFERENCIAS_ENDPOINT = '/front/ajax/transferencias.ajax.php';
const VENTAS_ENDPOINT = '/front/ajax/ventas.ajax.php';
const TZ_COLOMBIA = 'America/Bogota';
const LOCALE_CO = 'es-CO';

function parseTransferDateTime(raw) {
    const s = String(raw ?? '').trim();
    if (!s) return null;
    const d = new Date(s.replace(' ', 'T') + '-05:00');
    return Number.isFinite(d.getTime()) ? d : null;
}

function formatTransferFechaHora(raw) {
    const d = parseTransferDateTime(raw);
    if (!d) return { fecha: '—', hora: '' };
    const opts = { timeZone: TZ_COLOMBIA };
    return {
        fecha: d.toLocaleDateString(LOCALE_CO, opts),
        hora: d.toLocaleTimeString(LOCALE_CO, {
            ...opts,
            hour: '2-digit',
            minute: '2-digit',
            hour12: true,
        }),
    };
}

function transferNombreCompleto(t) {
    return `${t.name_customer || ''} ${t.lastname_customer || ''}`.trim() || '—';
}

function transferProofReference(t) {
    const norm = String(t.proof_reference_norm ?? '').trim();
    if (norm !== '') {
        return norm;
    }
    const raw = String(t.proof_reference ?? '').trim();
    return raw !== '' ? raw : '—';
}

function transferProofBank(t) {
    const bank = String(t.proof_bank ?? '').trim();
    if (bank !== '' && !transferIsPaymentRail(bank)) {
        return bank;
    }
    return '';
}

function transferIsPaymentRail(label) {
    const key = String(label ?? '').trim().toLowerCase();
    return key === 'bre-b' || key === 'breb';
}

function renderTransferProofBankBadge(t) {
    const bank = transferProofBank(t);
    if (!bank) {
        return '';
    }
    return `<div class="transfer-review-bank">
        <span class="badge border text-primary bg-light transfer-source-badge">
            <i class="ti ti-building-bank"></i> ${adminEscapeHtml(bank)}
        </span>
    </div>`;
}

function renderTransferLoadingSkeleton() {
    return `<div class="transfer-skeleton-list" aria-live="polite" aria-busy="true">
        ${[1, 2, 3].map(() => `
        <div class="transfer-skeleton-card">
            <div class="transfer-skeleton-line transfer-skeleton-line--lg"></div>
            <div class="transfer-skeleton-line"></div>
            <div class="transfer-skeleton-block"></div>
        </div>`).join('')}
        <p class="text-center text-muted small mb-0 pt-2">Cargando transferencias…</p>
    </div>`;
}

function renderTransferSoldNumbers(t) {
    const nums = Array.isArray(t.reserved_numbers) ? t.reserved_numbers : [];
    if (nums.length > 0) {
        return nums.map(n => `<span class="cr-numero-chip me-1 mb-1">${adminEscapeHtml(n)}</span>`).join('');
    }
    return `<span class="text-muted small">${adminEscapeHtml(t.reserved_numbers_label || '—')}</span>`;
}

function transferDatos(t) {
    const { fecha, hora } = formatTransferFechaHora(t.date_created_transfer);
    return {
        fecha,
        hora,
        inicial: adminInitial(t.name_customer),
        nombre: transferNombreCompleto(t),
        qtyLabel: `${t.quantity_transfer} núm${Number(t.quantity_transfer) > 1 ? 's' : ''}`,
        totalLabel: `$${Number(t.amount_transfer).toLocaleString('es-CO')}`,
    };
}

function transferFiltroStatus() {
    return String(document.getElementById('filterTransferStatus')?.value || TRANSFER_STATUS_PENDIENTE);
}

function syncTransferStatusTabs() {
    const status = transferFiltroStatus();
    document.querySelectorAll('.transfer-status-tab').forEach(btn => {
        const active = btn.dataset.status === status;
        btn.classList.toggle('is-active', active);
        btn.setAttribute('aria-selected', active ? 'true' : 'false');
    });
}

function setTransferStatusFilter(status) {
    const select = document.getElementById('filterTransferStatus');
    if (select) {
        select.value = String(status);
    }
    syncTransferStatusTabs();
    paginaActual = 1;
    cargarTransferencias();
}

function colombiaTodayIso() {
    return new Date().toLocaleDateString('en-CA', { timeZone: TZ_COLOMBIA });
}

function filtrarTransferenciasHoy() {
    const hoy = colombiaTodayIso();
    document.getElementById('fecha_inicio').value = hoy;
    document.getElementById('fecha_fin').value = hoy;
    paginaActual = 1;
    cargarTransferencias();
}

function actualizarTransferencias() {
    const btn = document.getElementById('btnRefreshTransfer');
    btn?.classList.add('is-spinning');
    cargarTransferencias().finally(() => btn?.classList.remove('is-spinning'));
}

function copiarTransferTexto(texto, etiqueta) {
    const value = String(texto ?? '').trim();
    if (!value || value === '—') {
        adminNotifyError(`No hay ${etiqueta || 'dato'} para copiar.`);
        return;
    }
    const done = () => adminNotifySuccess(`${etiqueta || 'Texto'} copiado`);
    if (navigator.clipboard?.writeText) {
        navigator.clipboard.writeText(value).then(done).catch(() => {
            adminNotifyError('No se pudo copiar. Mantén pulsado para seleccionar.');
        });
        return;
    }
    adminNotifyError('Copia no disponible en este navegador.');
}

function renderTransferCopyableValue(texto, etiqueta) {
    const value = String(texto ?? '').trim();
    if (!value || value === '—') {
        return '<span class="text-muted">—</span>';
    }
    return `<span class="transfer-copyable">
        <span class="font-monospace">${adminEscapeHtml(value)}</span>
        <button type="button" class="transfer-copy-btn" onclick='copiarTransferTexto(${JSON.stringify(value)}, ${JSON.stringify(etiqueta)})' title="Copiar ${adminEscapeHtml(etiqueta)}" aria-label="Copiar ${adminEscapeHtml(etiqueta)}">
            <i class="ti ti-copy"></i>
        </button>
    </span>`;
}

function transferEsPendiente(t) {
    return Number(t.status_transfer) === TRANSFER_STATUS_PENDIENTE;
}

function transferEsRechazada(t) {
    return Number(t.status_transfer) === TRANSFER_STATUS_RECHAZADA;
}

function transferEstadoMeta() {
    const status = transferFiltroStatus();
    if (status === String(TRANSFER_STATUS_PENDIENTE)) {
        return {
            key: 'pendiente',
            plural: 'pendientes',
            emptyTitle: 'No hay transferencias pendientes',
            emptyHint: 'Cuando un cliente envíe comprobante, aparecerá aquí para aprobar o rechazar.',
        };
    }
    if (status === String(TRANSFER_STATUS_RECHAZADA)) {
        return {
            key: 'rechazada',
            plural: 'rechazadas',
            emptyTitle: 'No hay transferencias rechazadas',
            emptyHint: 'Las compras anuladas o rechazadas aparecerán en este listado.',
        };
    }
    return {
        key: 'aprobada',
        plural: 'aprobadas',
        emptyTitle: 'No hay transferencias aprobadas',
        emptyHint: 'Las compras que apruebes aparecen aquí con sus números asignados.',
    };
}

function actualizarBadgeTotal() {
    const badge = document.getElementById('transferTotalBadge');
    if (!badge) return;
    const meta = transferEstadoMeta();
    const status = transferFiltroStatus();
    badge.textContent = `${totalTransferencias} ${totalTransferencias === 1 ? meta.key : meta.plural}`;
    badge.classList.toggle('d-none', totalTransferencias === 0);
    badge.classList.toggle('bg-warning-subtle', status === String(TRANSFER_STATUS_PENDIENTE));
    badge.classList.toggle('text-warning-emphasis', status === String(TRANSFER_STATUS_PENDIENTE));
    badge.classList.toggle('bg-danger-subtle', status === String(TRANSFER_STATUS_RECHAZADA));
    badge.classList.toggle('text-danger', status === String(TRANSFER_STATUS_RECHAZADA));
    badge.classList.toggle('bg-success-subtle', status === String(TRANSFER_STATUS_APROBADA));
    badge.classList.toggle('text-success', status === String(TRANSFER_STATUS_APROBADA));
}

function actualizarPaginacionTransfer() {
    if (typeof PaginationHelper === 'undefined') return;
    PaginationHelper.render({
        totalItems: totalTransferencias,
        currentPage: paginaActual,
        limit: registrosPorPagina,
        containerId: 'contenedorPaginacionTransfer',
        infoId: 'infoPaginacionTransfer',
        callbackName: 'cambiarPaginaTransfer',
    });
    const info = document.getElementById('infoPaginacionTransfer');
    if (info && totalTransferencias > 0) {
        const totalPaginas = Math.ceil(totalTransferencias / registrosPorPagina) || 1;
        const desde = (paginaActual - 1) * registrosPorPagina + 1;
        const hasta = Math.min(paginaActual * registrosPorPagina, totalTransferencias);
        info.textContent = `Mostrando ${desde}–${hasta} de ${totalTransferencias} · ${registrosPorPagina} por página (pág. ${paginaActual} de ${totalPaginas})`;
    }
}

function cambiarPaginaTransfer(pagina) {
    const totalPaginas = Math.ceil(totalTransferencias / registrosPorPagina) || 1;
    if (pagina < 1 || pagina > totalPaginas) return;
    paginaActual = pagina;
    cargarTransferencias();
}

function renderTabla() {
    actualizarBadgeTotal();

    const cola = document.getElementById('transferCola');
    if (!cola) return;

    if (!transferencias.length) {
        const meta = transferEstadoMeta();
        cola.innerHTML = `
        <div class="transfer-empty card border-0 shadow-sm">
            <div class="card-body text-center py-5">
                <i class="ti ti-inbox text-muted fs-1 mb-2 d-block"></i>
                <p class="fw-semibold mb-1">${adminEscapeHtml(meta.emptyTitle)}</p>
                <p class="text-muted small mb-0">${adminEscapeHtml(meta.emptyHint)}</p>
            </div>
        </div>`;
        actualizarPaginacionTransfer();
        return;
    }

    cola.innerHTML = transferencias.map(t => renderTransferCard(t)).join('');
    actualizarPaginacionTransfer();
}

function transferFiltrosParams() {
    const fd = new URLSearchParams();
    fd.append('action', 'obtener');
    fd.append('page', String(paginaActual));
    fd.append('limit', String(registrosPorPagina));
    fd.append('search', document.getElementById('searchTransfer')?.value?.trim() || '');
    fd.append('status', transferFiltroStatus());
    fd.append('fecha_inicio', document.getElementById('fecha_inicio')?.value || '');
    fd.append('fecha_fin', document.getElementById('fecha_fin')?.value || '');
    return fd;
}

document.addEventListener('DOMContentLoaded', () => {
    syncTransferStatusTabs();

    document.querySelectorAll('.transfer-status-tab').forEach(btn => {
        btn.addEventListener('click', () => {
            if (btn.classList.contains('is-active')) return;
            setTransferStatusFilter(btn.dataset.status);
        });
    });

    cargarTransferencias();

    document.getElementById('searchTransfer')?.addEventListener('input', debounce(() => {
        paginaActual = 1;
        cargarTransferencias();
    }, 400));

    document.getElementById('searchTransfer')?.addEventListener('keydown', e => {
        if (e.key === 'Enter') {
            e.preventDefault();
            paginaActual = 1;
            cargarTransferencias();
        }
    });

    ['fecha_inicio', 'fecha_fin'].forEach(id => {
        document.getElementById(id)?.addEventListener('change', function () {
            const ini = document.getElementById('fecha_inicio')?.value || '';
            const fin = document.getElementById('fecha_fin')?.value || '';
            if (ini && fin) {
                paginaActual = 1;
                cargarTransferencias();
            }
        });
    });
});

function cargarTransferencias() {
    const cola = document.getElementById('transferCola');
    if (cola) {
        cola.innerHTML = renderTransferLoadingSkeleton();
    }

    return fetch(TRANSFERENCIAS_ENDPOINT, { method: 'POST', body: transferFiltrosParams() })
        .then(parseJsonTransferenciasAjax)
        .then(data => {
            if (!data.success) {
                transferencias = [];
                totalTransferencias = 0;
                renderTabla();
                adminNotifyError(adminExtractMessage(data, 'No se pudieron cargar las transferencias.'));
                return;
            }

            transferencias = Array.isArray(data.data) ? data.data : (data.data ? [data.data] : []);
            totalTransferencias = Number(data.total) || 0;

            const totalPaginas = Math.ceil(totalTransferencias / registrosPorPagina) || 1;
            if (paginaActual > totalPaginas && totalPaginas > 0) {
                paginaActual = totalPaginas;
                return cargarTransferencias();
            }

            renderTabla();
        })
        .catch(err => {
            transferencias = [];
            totalTransferencias = 0;
            renderTabla();
            adminNotifyError(err instanceof Error ? err.message : 'No se pudieron cargar las transferencias.');
        });
}

function limpiarFiltrosTransfer() {
    document.getElementById('searchTransfer').value = '';
    const select = document.getElementById('filterTransferStatus');
    if (select) {
        select.value = String(TRANSFER_STATUS_PENDIENTE);
    }
    syncTransferStatusTabs();
    document.getElementById('fecha_inicio').value = '';
    document.getElementById('fecha_fin').value = '';
    paginaActual = 1;
    cargarTransferencias();
}

function transferComprobanteUrl(t) {
    const view = String(t.comprobante_view_url ?? '').trim();
    if (view !== '') {
        return view;
    }
  const id = Number(t.id_transfer);
    if (id > 0) {
        return `/front/comprobante.php?id_transfer=${id}`;
    }
    return String(t.url_transfer ?? '').trim();
}

function renderTransferProofBlock(t, large) {
    const id = Number(t.id_transfer);
    const imgUrl = transferComprobanteUrl(t);
    if (!imgUrl) {
        return `<div class="transfer-proof transfer-proof--empty ${large ? 'transfer-proof--lg' : ''}">
            <i class="ti ti-photo-off fs-1 text-muted"></i>
            <span class="small text-muted">Sin comprobante</span>
        </div>`;
    }
    const cls = large ? 'transfer-proof transfer-proof--lg' : 'transfer-proof';
    return `
    <button type="button" class="${cls} transfer-proof-btn" onclick="verComprobantePorId(${id})" title="Ampliar comprobante">
        <img src="${adminEscapeHtml(imgUrl)}" alt="Comprobante" loading="lazy" decoding="async">
        <span class="transfer-proof-zoom"><i class="ti ti-zoom-in"></i></span>
    </button>`;
}

function renderTransferStatusBadge(t) {
    if (transferEsPendiente(t)) {
        return '<span class="badge transfer-badge-pendiente px-3 py-2 rounded-pill">Pendiente</span>';
    }
    if (transferEsRechazada(t)) {
        return '<span class="badge bg-danger-subtle text-danger border border-danger-subtle px-3 py-2 rounded-pill">Rechazada</span>';
    }
    return '<span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 rounded-pill">Aprobada</span>';
}

function renderTransferPhoneLink(t) {
    const phone = String(t.phone_customer || '').replace(/\D/g, '');
    const label = adminEscapeHtml(t.phone_customer || '—');
    if (!phone) {
        return `<span class="transfer-review-phone text-muted pe-none"><i class="ti ti-phone"></i> ${label}</span>`;
    }
    return `<a href="https://api.whatsapp.com/send?phone=57${phone}" target="_blank" rel="noopener" class="transfer-review-phone">
        <i class="ti ti-brand-whatsapp"></i> ${label}
    </a>`;
}

function transferTieneComprobante(t) {
    return transferComprobanteUrl(t) !== '';
}

function renderTransferCard(t) {
    const d = transferDatos(t);
    const id = Number(t.id_transfer);
    const pendiente = transferEsPendiente(t);
    const rechazada = transferEsRechazada(t);
    const cardClass = pendiente
        ? 'transfer-review-card--pending'
        : (rechazada ? 'transfer-review-card--rejected' : 'transfer-review-card--done');
    const ref = transferProofReference(t);
    const tieneComprobante = transferTieneComprobante(t);
    const canApprove = adminCanCap('transferencias.approve');

    const comprobanteBtn = tieneComprobante
        ? `<button type="button" class="btn btn-primary transfer-action-btn d-md-none" onclick="verComprobantePorId(${id})">
                <i class="ti ti-photo me-1"></i> Ver comprobante
           </button>`
        : '';

    let actions = '';
    if (pendiente && canApprove) {
        actions = [
            comprobanteBtn,
            `<button type="button" class="btn btn-outline-danger transfer-action-btn" onclick="rechazarTransferenciaVenta(${id})">
                <i class="ti ti-x me-1"></i> Rechazar
            </button>`,
            `<button type="button" class="btn btn-success transfer-action-btn" onclick="aprobarTransferenciaVenta(${id})">
                <i class="ti ti-check me-1"></i> Aprobar compra
            </button>`,
        ].filter(Boolean).join('');
    } else if (!rechazada && !pendiente && canApprove) {
        actions = [
            comprobanteBtn,
            `<button type="button" class="btn btn-outline-danger transfer-action-btn" onclick="rechazarTransferenciaVenta(${id})">
                <i class="ti ti-ban me-1"></i> Anular venta
            </button>`,
        ].filter(Boolean).join('');
    } else {
        actions = comprobanteBtn;
    }

    const numbersLabel = pendiente
        ? 'Números'
        : (rechazada ? 'Números liberados' : 'Números asignados');

    return `
    <article class="transfer-review-card ${cardClass}">
        <div class="transfer-card-top">
            <div class="transfer-card-top__main">
                <div class="transfer-review-avatar" aria-hidden="true">${adminEscapeHtml(d.inicial)}</div>
                <div class="transfer-card-top__text">
                    <h3 class="transfer-review-name">${adminEscapeHtml(d.nombre)}</h3>
                    ${renderTransferPhoneLink(t)}
                </div>
            </div>
            <div class="transfer-card-top__aside">
                ${renderTransferStatusBadge(t)}
                <div class="transfer-card-total transfer-card-total--mobile d-md-none" aria-label="Total pagado">${adminEscapeHtml(d.totalLabel)}</div>
            </div>
        </div>

        <div class="transfer-review-card__body">
            <div class="transfer-review-card__proof-col transfer-review-card__proof-col--desktop">
                ${renderTransferProofBlock(t, true)}
                <span class="transfer-review-card__proof-hint small text-muted">Toca la imagen para ampliar</span>
            </div>
            <div class="transfer-review-card__info">
                <div class="transfer-review-meta">
                    <div class="transfer-review-meta__item transfer-review-meta__item--total">
                        <span class="transfer-review-meta__label">Total</span>
                        <span class="transfer-review-meta__value">${adminEscapeHtml(d.totalLabel)}</span>
                    </div>
                    <div class="transfer-review-meta__item">
                        <span class="transfer-review-meta__label">Cantidad</span>
                        <span class="transfer-review-meta__value">${adminEscapeHtml(d.qtyLabel)}</span>
                    </div>
                    <div class="transfer-review-meta__item transfer-review-meta__item--numbers transfer-review-meta__item--wide">
                        <span class="transfer-review-meta__label">${numbersLabel}</span>
                        <span class="transfer-review-meta__value">${renderTransferSoldNumbers(t)}</span>
                    </div>
                    <div class="transfer-review-meta__item">
                        <span class="transfer-review-meta__label">Código compra</span>
                        <span class="transfer-review-meta__value">${renderTransferCopyableValue(t.code_transfer, 'código')}</span>
                    </div>
                    <div class="transfer-review-meta__item">
                        <span class="transfer-review-meta__label">Ref. comprobante</span>
                        <span class="transfer-review-meta__value">${renderTransferCopyableValue(ref === '—' ? '' : ref, 'referencia')}</span>
                    </div>
                    <div class="transfer-review-meta__item transfer-review-meta__item--wide">
                        <span class="transfer-review-meta__label">Rifa</span>
                        <span class="transfer-review-meta__value">${adminEscapeHtml(t.title_raffle || '—')}</span>
                    </div>
                    <div class="transfer-review-meta__item">
                        <span class="transfer-review-meta__label">Fecha</span>
                        <span class="transfer-review-meta__value">${adminEscapeHtml(d.fecha)} · ${adminEscapeHtml(d.hora)}</span>
                    </div>
                </div>
                ${renderTransferProofBankBadge(t)}
            </div>
        </div>
        ${actions ? `<div class="transfer-review-card__actions">${actions}</div>` : ''}
    </article>`;
}

function paramsAprobarTransferencia(t) {
    const fd = new FormData();
    fd.append('action', 'aprobar');
    fd.append('id_transfer', String(t.id_transfer ?? ''));
    fd.append('status_transfer', String(t.status_transfer ?? ''));
    fd.append('quantity_transfer', String(t.quantity_transfer ?? ''));
    fd.append('id_raffle_transfer', String(t.id_raffle_transfer ?? ''));
    fd.append('amount_transfer', String(t.amount_transfer ?? ''));
    fd.append('id_customer_transfer', String(t.id_customer_transfer ?? ''));
    fd.append('code_transfer', String(t.code_transfer ?? ''));
    if (t.source_transfer != null && String(t.source_transfer).trim() !== '') {
        fd.append('source_transfer', String(t.source_transfer));
    }
    adminAppendCsrf(fd);
    return fd;
}

const aprobacionesTransferEnCurso = new Set();

function aprobarTransferenciaVenta(id) {
    const t = transferencias.find(x => Number(x.id_transfer) === Number(id));
    if (!t) return;

    const idTransfer = Number(t.id_transfer);
    if (aprobacionesTransferEnCurso.has(idTransfer)) {
        return;
    }

    Swal.fire({
        title: '¿Aprobar esta compra?',
        html: `<p class="mb-2">${adminEscapeHtml(transferNombreCompleto(t))}</p>
               <p class="fw-bold text-success mb-0">${adminEscapeHtml('$' + Number(t.amount_transfer).toLocaleString('es-CO'))}</p>
               <small class="text-muted font-monospace">${adminEscapeHtml(t.code_transfer || '')}</small>`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, aprobar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#198754',
        reverseButtons: true,
    }).then(result => {
        if (!result.isConfirmed) return;
        if (aprobacionesTransferEnCurso.has(idTransfer)) {
            return;
        }
        aprobacionesTransferEnCurso.add(idTransfer);

        Swal.fire({
            title: 'Procesando…',
            text: 'Creando venta y asignando números',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading(),
        });

        fetch(TRANSFERENCIAS_ENDPOINT, { method: 'POST', body: paramsAprobarTransferencia(t) })
            .then(parseJsonTransferenciasAjax)
            .then(res => {
                const idSale = Number(res.id_sale);
                if (res.success && idSale > 0) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Venta creada',
                        text: 'Abriendo recibo…',
                        timer: 1200,
                        showConfirmButton: false,
                    });
                    setTimeout(() => verReciboTransferencia(idSale), 1200);
                } else if (!res.success && idSale > 0) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Venta registrada',
                        text: res.message || 'Revise el estado en la base de datos.',
                    });
                    verReciboTransferencia(idSale);
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: adminExtractMessage(res, 'No se pudo aprobar'),
                    });
                }
                cargarTransferencias();
            })
            .catch(() => Swal.fire('Error', 'Fallo en la solicitud', 'error'))
            .finally(() => aprobacionesTransferEnCurso.delete(idTransfer));
    });
}

function rechazarTransferenciaVenta(id) {
    const t = transferencias.find(x => Number(x.id_transfer) === Number(id));
    const nombre = t ? transferNombreCompleto(t) : 'esta compra';
    const codigo = t?.code_transfer ? ` (${t.code_transfer})` : '';
    const pendiente = t ? transferEsPendiente(t) : false;

    Swal.fire({
        title: pendiente ? '¿Rechazar transferencia?' : '¿Anular esta venta?',
        html: pendiente
            ? `Se rechazará la compra de <strong>${adminEscapeHtml(nombre)}</strong>${adminEscapeHtml(codigo)}.`
            : `Se anulará la compra de <strong>${adminEscapeHtml(nombre)}</strong>${adminEscapeHtml(codigo)}, se liberarán los números y quedará como rechazada.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: pendiente ? 'Sí, rechazar' : 'Sí, anular',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#dc3545',
        focusCancel: true,
    }).then(result => {
        if (!result.isConfirmed) return;

        const fd = new FormData();
        fd.append('action', 'rechazar');
        fd.append('id_transfer', String(id));
        adminAppendCsrf(fd);

        fetch(TRANSFERENCIAS_ENDPOINT, { method: 'POST', body: fd })
            .then(parseJsonTransferenciasAjax)
            .then(data => {
                if (!data.success) {
                    adminNotifyError(adminExtractMessage(data, pendiente ? 'No se pudo rechazar.' : 'No se pudo rechazar la venta.'));
                    return;
                }
                adminNotifySuccess(adminExtractMessage(data, pendiente ? 'Transferencia rechazada.' : 'Venta anulada correctamente.'));
                cargarTransferencias();
            })
            .catch(err => {
                adminNotifyError(err instanceof Error ? err.message : (pendiente ? 'No se pudo rechazar.' : 'No se pudo rechazar la venta.'));
            });
    });
}

function verComprobantePorId(id) {
    const t = transferencias.find(x => Number(x.id_transfer) === Number(id));
    const imgUrl = t ? transferComprobanteUrl(t) : '';
    if (!imgUrl) {
        Swal.fire('Error', 'No hay comprobante disponible', 'error');
        return;
    }
    verComprobante(imgUrl, t && transferEsPendiente(t) ? t : null);
}

function verComprobante(url, transfer) {
    if (!url) return;

    const cuerpo = document.getElementById('cuerpoComprobante');
    const footer = document.getElementById('modalComprobanteActions');
    if (cuerpo) {
        cuerpo.innerHTML = `<img src="${adminEscapeHtml(url)}" class="img-fluid rounded transfer-proof-modal-img" alt="Comprobante">`;
    }

    if (footer && transfer && transferEsPendiente(transfer) && adminCanCap('transferencias.approve')) {
        const id = Number(transfer.id_transfer);
        footer.classList.remove('d-none');
        footer.innerHTML = `
            <button type="button" class="btn btn-outline-danger" onclick="rechazarTransferenciaVenta(${id}); bootstrap.Modal.getInstance(document.getElementById('modalComprobante'))?.hide();">
                <i class="ti ti-x me-1"></i> Rechazar
            </button>
            <button type="button" class="btn btn-success" onclick="aprobarTransferenciaVenta(${id}); bootstrap.Modal.getInstance(document.getElementById('modalComprobante'))?.hide();">
                <i class="ti ti-check me-1"></i> Aprobar compra
            </button>`;
    } else if (footer) {
        footer.classList.add('d-none');
        footer.innerHTML = '';
    }

    new bootstrap.Modal(document.getElementById('modalComprobante')).show();
}

function verReciboTransferencia(id) {
    const fd = new FormData();
    fd.append('action', 'detalle_venta');
    fd.append('id_sale', String(id));
    adminAppendCsrf(fd);

    fetch(VENTAS_ENDPOINT, { method: 'POST', body: fd })
        .then(parseJsonTransferenciasAjax)
        .then(res => {
            if (res.success && document.getElementById('cuerpoRecibo')) {
                document.getElementById('cuerpoRecibo').innerHTML = res.html_recibo || '';
                new bootstrap.Modal(document.getElementById('modalRecibo')).show();
                return;
            }
            Swal.fire('Error', 'No se pudo cargar el recibo', 'error');
        })
        .catch(() => Swal.fire('Error', 'No se pudo cargar el recibo', 'error'));
}

function parseJsonTransferenciasAjax(res) {
    if (!res || typeof res.text !== 'function') {
        return Promise.reject(new Error('Respuesta inválida del servidor. Recarga la página e intenta de nuevo.'));
    }
    return res.text().then(text => {
        try {
            return JSON.parse(text);
        } catch (e) {
            throw new Error('Respuesta inválida del servidor. Recarga la página e intenta de nuevo.');
        }
    });
}

function debounce(func, wait) {
    let timeout;
    return function (...args) {
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(this, args), wait);
    };
}
