const ENDPOINT = '/front/ajax/tracking.ajax.php';
const PAGE_SIZE = 25;
let paginaTracking = 1;
let totalTracking = 0;
let eventOptionsLoaded = false;

const EVENT_BADGE = {
    'meta.page_view': 'secondary',
    'meta.view_content': 'info',
    'meta.add_to_cart': 'primary',
    'meta.initiate_checkout': 'warning',
    'meta.lead': 'dark',
    'meta.add_payment_info': 'warning',
    'meta.initiate_checkout.sent': 'success',
};

document.addEventListener('DOMContentLoaded', () => {
    aplicarPeriodo();
    document.getElementById('filtroPeriodo')?.addEventListener('change', () => {
        aplicarPeriodo();
        if (document.getElementById('filtroPeriodo').value !== 'rango') {
            cargarTracking(1);
        }
    });
    document.getElementById('filtroEvento')?.addEventListener('change', () => cargarTracking(1));
    document.getElementById('filtroDesde')?.addEventListener('change', () => {
        document.getElementById('filtroPeriodo').value = 'rango';
    });
    document.getElementById('filtroHasta')?.addEventListener('change', () => {
        document.getElementById('filtroPeriodo').value = 'rango';
    });
    document.getElementById('funnelTracking')?.addEventListener('click', (e) => {
        const card = e.target.closest('[data-funnel-action]');
        if (!card) {
            return;
        }
        const action = card.getAttribute('data-funnel-action') || '';
        const sel = document.getElementById('filtroEvento');
        if (!sel) {
            return;
        }
        sel.value = sel.value === action ? '' : action;
        cargarTracking(1);
    });
    cargarTracking(1);
});

function formatDate(d) {
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
}

function aplicarPeriodo() {
    const periodo = document.getElementById('filtroPeriodo')?.value || 'semana';
    const desdeEl = document.getElementById('filtroDesde');
    const hastaEl = document.getElementById('filtroHasta');
    if (!desdeEl || !hastaEl) {
        return;
    }

    const now = new Date();
    let desde = formatDate(now);
    let hasta = formatDate(now);

    if (periodo === 'ayer') {
        const d = new Date(now);
        d.setDate(d.getDate() - 1);
        desde = hasta = formatDate(d);
    } else if (periodo === 'semana') {
        const d = new Date(now);
        const day = d.getDay() || 7;
        d.setDate(d.getDate() - (day - 1));
        desde = formatDate(d);
        hasta = formatDate(now);
    } else if (periodo === 'mes') {
        desde = formatDate(new Date(now.getFullYear(), now.getMonth(), 1));
        hasta = formatDate(new Date(now.getFullYear(), now.getMonth() + 1, 0));
    } else if (periodo === 'rango') {
        return;
    }

    desdeEl.value = desde;
    hastaEl.value = hasta;
}

async function cargarTracking(page) {
    paginaTracking = Math.max(1, parseInt(page, 10) || 1);
    aplicarPeriodo();

    try {
        const fd = new FormData();
        fd.append('action', 'listar');
        fd.append('page', String(paginaTracking));
        fd.append('limit', String(PAGE_SIZE));
        fd.append('event', document.getElementById('filtroEvento')?.value || '');
        fd.append('date_from', document.getElementById('filtroDesde')?.value || '');
        fd.append('date_to', document.getElementById('filtroHasta')?.value || '');
        const data = await adminFetchJson(ENDPOINT, { body: fd });
        if (!data.success) {
            adminNotifyError(adminExtractMessage(data, 'No se pudo cargar el tracking.'));
            return;
        }

        fillEventFilter(data.events || []);
        renderFunnel(data.funnel || []);
        totalTracking = Number(data.total || 0);
        renderTabla(data.data || []);
        renderPaginacion();
    } catch (e) {
        adminNotifyError(e instanceof Error ? e.message : 'No se pudo cargar el tracking.');
    }
}

function fillEventFilter(events) {
    const sel = document.getElementById('filtroEvento');
    if (!sel || eventOptionsLoaded || !events.length) {
        return;
    }
    const current = sel.value;
    sel.innerHTML = '<option value="">Todos los eventos</option>' + events.map(ev =>
        `<option value="${adminEscapeHtml(ev.action)}">${adminEscapeHtml(ev.label)}</option>`
    ).join('');
    sel.value = current;
    eventOptionsLoaded = true;
}

function renderFunnel(funnel) {
    const host = document.getElementById('funnelTracking');
    if (!host) {
        return;
    }
    const selected = document.getElementById('filtroEvento')?.value || '';
    host.innerHTML = funnel.map(item => {
        const active = selected === item.action ? ' border-primary' : '';
        return `
        <div class="col">
            <button type="button" class="card border-0 shadow-sm h-100 w-100 text-start${active}" data-funnel-action="${adminEscapeHtml(item.action)}" style="cursor:pointer">
                <div class="card-body py-3">
                    <div class="text-muted small text-uppercase fw-bold mb-1">
                        <i class="ti ${adminEscapeHtml(item.icon || 'ti-chart-dots')} me-1"></i>${adminEscapeHtml(item.label)}
                    </div>
                    <div class="fs-3 fw-bolder">${Number(item.total || 0).toLocaleString('es-CO')}</div>
                    <div class="text-muted small">${adminEscapeHtml(item.hint || '')}</div>
                </div>
            </button>
        </div>`;
    }).join('');
}

function renderTabla(rows) {
    const body = document.getElementById('bodyTracking');
    if (!rows.length) {
        body.innerHTML = '<tr><td colspan="3" class="text-center py-4 text-muted">No hay actividad en este periodo. Prueba <strong>Esta semana</strong> o <strong>Este mes</strong>.</td></tr>';
        if (typeof renderAdminMobileRows === 'function') {
            renderAdminMobileRows('cardsTracking', []);
        }
        return;
    }

    body.innerHTML = rows.map(r => {
        const d = trackingDatos(r);
        return `
        <tr class="align-middle admin-table-row">
            <td class="py-3 ps-4 small">${adminDateBlock(d.dt.fecha, d.dt.hora)}</td>
            <td class="py-3">${eventBadge(r)}</td>
            <td class="py-3 small">${d.detalle || '—'}</td>
        </tr>`;
    }).join('');

    if (typeof renderAdminMobileRows === 'function') {
        renderAdminMobileRows('cardsTracking', rows.map(r => {
            const d = trackingDatos(r);
            return adminMobileRow(
                adminMobileSection(adminClientBlock({
                    initial: adminInitial(r.action_label || 'E'),
                    name: r.action_label || r.action_audit,
                    phone: d.detalle || 'Landing',
                    city: '',
                })) +
                adminMobileBottom(adminDateBlock(d.dt.fecha, d.dt.hora), '')
            );
        }));
    }
}

function trackingDatos(r) {
    const dt = adminFormatDateColombia(r.created_at);
    return { dt, detalle: formatTrackingDetalle(r) };
}

function formatTrackingDetalle(r) {
    const raw = r.new_data || '';
    if (!raw) {
        return '';
    }
    try {
        const d = typeof raw === 'string' ? JSON.parse(raw) : raw;
        if (!d || typeof d !== 'object') {
            return esc(String(raw));
        }
        const parts = [];
        if (d.value != null && d.value !== '') {
            parts.push(new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 }).format(Number(d.value)));
        }
        if (d.num_items != null && d.num_items !== '') {
            parts.push(d.num_items + ' nums');
        }
        if (d.code_transfer) {
            parts.push('Ref. ' + d.code_transfer);
        }
        if (d.code_sale) {
            parts.push('Venta ' + d.code_sale);
        }
        if (d.order_id) {
            parts.push('Ref. ' + d.order_id);
        }
        if (d.path) {
            parts.push(d.path);
        }
        return esc(parts.join(' · '));
    } catch (e) {
        return esc(String(raw));
    }
}

function eventBadge(r) {
    const label = r.action_label || r.action_audit || '';
    const tone = EVENT_BADGE[r.action_audit] || 'secondary';
    return `<span class="badge text-bg-${tone}">${adminEscapeHtml(label)}</span>`;
}

function renderPaginacion() {
    const maxPages = Math.ceil(totalTracking / PAGE_SIZE) || 0;
    const totalPaginas = Math.max(1, maxPages);
    const info = document.getElementById('infoTracking');
    const contenedor = document.getElementById('paginacionTracking');
    const desde = totalTracking === 0 ? 0 : (paginaTracking - 1) * PAGE_SIZE + 1;
    const hasta = Math.min(paginaTracking * PAGE_SIZE, totalTracking);
    if (info) {
        info.textContent = totalTracking === 0
            ? '0 eventos'
            : `Página ${paginaTracking} de ${totalPaginas} · ${desde}–${hasta} de ${totalTracking.toLocaleString('es-CO')}`;
    }
    if (!contenedor) {
        return;
    }

    let html = `<li class="page-item ${paginaTracking <= 1 ? 'disabled' : ''}">
        <a class="page-link" href="javascript:void(0)" onclick="cargarTracking(${paginaTracking - 1})">Anterior</a></li>`;

    if (maxPages <= 1) {
        html += '<li class="page-item active"><a class="page-link" href="javascript:void(0)">1</a></li>';
    } else {
        let inicio = Math.max(1, paginaTracking - 2);
        let fin = Math.min(maxPages, inicio + 4);
        if (fin - inicio < 4) {
            inicio = Math.max(1, fin - 4);
        }
        for (let i = inicio; i <= fin; i++) {
            html += `<li class="page-item ${i === paginaTracking ? 'active' : ''}">
                <a class="page-link" href="javascript:void(0)" onclick="cargarTracking(${i})">${i}</a></li>`;
        }
    }

    html += `<li class="page-item ${paginaTracking >= maxPages || maxPages === 0 ? 'disabled' : ''}">
        <a class="page-link" href="javascript:void(0)" onclick="cargarTracking(${paginaTracking + 1})">Siguiente</a></li>`;
    contenedor.innerHTML = html;
}

function esc(s) {
    return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}
