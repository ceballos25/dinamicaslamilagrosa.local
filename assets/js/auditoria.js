const ENDPOINT = '/front/ajax/auditoria.ajax.php';
let paginaAuditoria = 1;

const META_BADGE = {
    'meta.page_view': 'secondary',
    'meta.view_content': 'info',
    'meta.add_to_cart': 'primary',
    'meta.initiate_checkout': 'warning',
    'meta.lead': 'info',
    'meta.add_payment_info': 'warning',
    'meta.initiate_checkout.sent': 'success',
    'meta.purchase.sent': 'success',
};

document.addEventListener('DOMContentLoaded', () => {
    cargarAuditoria();
    document.getElementById('filtroScope')?.addEventListener('change', () => {
        paginaAuditoria = 1;
        cargarAuditoria();
    });
});

async function cargarAuditoria() {
    try {
        const fd = new FormData();
        fd.append('action', 'listar');
        fd.append('page', paginaAuditoria);
        fd.append('search_action', document.getElementById('filtroAccion')?.value || '');
        fd.append('date_from', document.getElementById('filtroDesde')?.value || '');
        fd.append('date_to', document.getElementById('filtroHasta')?.value || '');
        fd.append('scope', document.getElementById('filtroScope')?.value || '');
        const data = await adminFetchJson(ENDPOINT, { body: fd });
        if (!data.success) {
            adminNotifyError(adminExtractMessage(data, 'No se pudo cargar la auditoría.'));
            return;
        }

        const rows = data.data || [];
        document.getElementById('infoAuditoria').textContent = `${data.total} registros`;
        renderFunnel(data.funnel || []);

        if (!rows.length) {
            document.getElementById('bodyAuditoria').innerHTML = '<tr><td colspan="4" class="text-center py-3">Sin registros</td></tr>';
            if (typeof renderAdminMobileRows === 'function') renderAdminMobileRows('cardsAuditoria', []);
            return;
        }

        document.getElementById('bodyAuditoria').innerHTML = rows.map(r => renderAuditoriaDesktopRow(r)).join('');

        if (typeof renderAdminMobileRows === 'function') {
            renderAdminMobileRows('cardsAuditoria', rows.map(r => renderAuditoriaMobileRow(r)));
        }
    } catch (e) {
        adminNotifyError(e instanceof Error ? e.message : 'No se pudo cargar la auditoría.');
    }
}

function renderFunnel(funnel) {
    const host = document.getElementById('funnelMeta');
    if (!host) {
        return;
    }

    const icons = {
        'meta.page_view': 'ti-eye',
        'meta.initiate_checkout': 'ti-click',
        'meta.lead': 'ti-user',
        'meta.initiate_checkout.sent': 'ti-receipt',
        'meta.purchase.sent': 'ti-shopping-cart',
    };

    host.innerHTML = funnel.map(item => `
        <div class="col-6 col-md">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body py-3">
                    <div class="text-muted small text-uppercase fw-bold mb-1">
                        <i class="ti ${icons[item.action] || 'ti-chart-dots'} me-1"></i>${adminEscapeHtml(item.label)}
                    </div>
                    <div class="fs-3 fw-bolder">${Number(item.total || 0).toLocaleString('es-CO')}</div>
                    <div class="text-muted small">${adminEscapeHtml(item.hint || '')}</div>
                </div>
            </div>
        </div>`).join('');
}

function auditoriaDatos(r) {
    const dt = adminFormatDateColombia(r.created_at);
    const label = r.action_label || r.action_audit || '';
    const detalle = formatAuditoriaDetalle(r);
    const who = r.is_meta ? 'Visitante' : (r.email_admin || 'Sistema');
    return { dt, detalle, label, who };
}

function formatAuditoriaDetalle(r) {
    const raw = r.new_data || r.old_data || '';
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
            parts.push(d.num_items + (Number(d.num_items) === 1 ? ' número' : ' números'));
        }
        if (d.quantity != null && d.quantity !== '') {
            parts.push(d.quantity + (Number(d.quantity) === 1 ? ' número' : ' números'));
        }
        if (d.total != null && d.total !== '') {
            parts.push(new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 }).format(Number(d.total)));
        }
        if (d.code_sale) {
            parts.push('Venta ' + d.code_sale);
        }
        if (d.code_transfer) {
            parts.push('Ref. ' + d.code_transfer);
        }
        if (d.path) {
            parts.push(d.path);
        }
        return esc(parts.join(' · '));
    } catch (e) {
        return esc(String(raw));
    }
}

function actionBadge(r) {
    const label = r.action_label || r.action_audit || '';
    const tone = META_BADGE[r.action_audit] || (r.is_meta ? 'secondary' : 'light');
    return `<span class="badge text-bg-${tone}">${adminEscapeHtml(label)}</span>`;
}

function renderAuditoriaDesktopRow(r) {
    const d = auditoriaDatos(r);
    return `
        <tr class="align-middle admin-table-row">
            <td class="py-3 ps-4 small">${adminDateBlock(d.dt.fecha, d.dt.hora)}</td>
            <td class="py-3">${adminEscapeHtml(d.who)}</td>
            <td class="py-3">${actionBadge(r)}</td>
            <td class="py-3 small text-truncate" style="max-width:280px" title="${d.detalle}">${d.detalle || '—'}</td>
        </tr>`;
}

function renderAuditoriaMobileRow(r) {
    const d = auditoriaDatos(r);
    return adminMobileRow(
        adminMobileSection(adminClientBlock({
            initial: adminInitial(d.who),
            name: r.action_label || r.action_audit,
            phone: d.who,
            city: d.detalle || '—',
        })) +
        adminMobileBottom(adminDateBlock(d.dt.fecha, d.dt.hora), '')
    );
}

function esc(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}
