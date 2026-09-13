const ENDPOINT = '/front/ajax/auditoria.ajax.php';
const PAGE_SIZE = 50;
let paginaAuditoria = 1;
let totalAuditoria = 0;

document.addEventListener('DOMContentLoaded', () => cargarAuditoria(1));

async function cargarAuditoria(page) {
    paginaAuditoria = Math.max(1, parseInt(page, 10) || 1);
    try {
        const fd = new FormData();
        fd.append('action', 'listar');
        fd.append('page', String(paginaAuditoria));
        fd.append('limit', String(PAGE_SIZE));
        fd.append('search_action', document.getElementById('filtroAccion')?.value || '');
        fd.append('date_from', document.getElementById('filtroDesde')?.value || '');
        fd.append('date_to', document.getElementById('filtroHasta')?.value || '');
        const data = await adminFetchJson(ENDPOINT, { body: fd });
        if (!data.success) {
            adminNotifyError(adminExtractMessage(data, 'No se pudo cargar la auditoría.'));
            return;
        }

        const rows = data.data || [];
        totalAuditoria = Number(data.total || 0);

        if (!rows.length) {
            document.getElementById('bodyAuditoria').innerHTML = '<tr><td colspan="5" class="text-center py-3">Sin registros</td></tr>';
            if (typeof renderAdminMobileRows === 'function') renderAdminMobileRows('cardsAuditoria', []);
            renderPaginacionAuditoria();
            return;
        }

        document.getElementById('bodyAuditoria').innerHTML = rows.map(r => renderAuditoriaDesktopRow(r)).join('');

        if (typeof renderAdminMobileRows === 'function') {
            renderAdminMobileRows('cardsAuditoria', rows.map(r => renderAuditoriaMobileRow(r)));
        }
        renderPaginacionAuditoria();
    } catch (e) {
        adminNotifyError(e instanceof Error ? e.message : 'No se pudo cargar la auditoría.');
    }
}

function renderPaginacionAuditoria() {
    const maxPages = Math.ceil(totalAuditoria / PAGE_SIZE) || 0;
    const totalPaginas = Math.max(1, maxPages);
    const info = document.getElementById('infoAuditoria');
    const contenedor = document.getElementById('paginacionAuditoria');
    const desde = totalAuditoria === 0 ? 0 : (paginaAuditoria - 1) * PAGE_SIZE + 1;
    const hasta = Math.min(paginaAuditoria * PAGE_SIZE, totalAuditoria);

    if (info) {
        info.textContent = totalAuditoria === 0
            ? '0 registros'
            : `Página ${paginaAuditoria} de ${totalPaginas} · ${desde}–${hasta} de ${totalAuditoria.toLocaleString('es-CO')}`;
    }
    if (!contenedor) {
        return;
    }

    let html = `<li class="page-item ${paginaAuditoria <= 1 ? 'disabled' : ''}">
        <a class="page-link" href="javascript:void(0)" onclick="cargarAuditoria(${paginaAuditoria - 1})">Anterior</a></li>`;

    if (maxPages <= 1) {
        html += '<li class="page-item active"><a class="page-link" href="javascript:void(0)">1</a></li>';
    } else {
        let inicio = Math.max(1, paginaAuditoria - 2);
        let fin = Math.min(maxPages, inicio + 4);
        if (fin - inicio < 4) {
            inicio = Math.max(1, fin - 4);
        }
        for (let i = inicio; i <= fin; i++) {
            html += `<li class="page-item ${i === paginaAuditoria ? 'active' : ''}">
                <a class="page-link" href="javascript:void(0)" onclick="cargarAuditoria(${i})">${i}</a></li>`;
        }
    }

    html += `<li class="page-item ${paginaAuditoria >= maxPages || maxPages === 0 ? 'disabled' : ''}">
        <a class="page-link" href="javascript:void(0)" onclick="cargarAuditoria(${paginaAuditoria + 1})">Siguiente</a></li>`;
    contenedor.innerHTML = html;
}

function auditoriaDatos(r) {
    const dt = adminFormatDateColombia(r.created_at);
    const detalle = esc(r.new_data || r.old_data || '');
    const entity = [r.entity_type, r.entity_id].filter(Boolean).join(' #');
    const label = r.action_label || r.action_audit;
    return { dt, detalle, entity, label };
}

function renderAuditoriaDesktopRow(r) {
    const d = auditoriaDatos(r);
    return `
        <tr class="align-middle admin-table-row">
            <td class="py-3 ps-4 small">${adminDateBlock(d.dt.fecha, d.dt.hora)}</td>
            <td class="py-3">${adminEscapeHtml(r.email_admin || 'Sistema')}</td>
            <td class="py-3">${adminEscapeHtml(d.label)}</td>
            <td class="py-3">${adminEscapeHtml(d.entity || '—')}</td>
            <td class="py-3 small text-truncate" style="max-width:200px" title="${d.detalle}">${d.detalle || '—'}</td>
        </tr>`;
}

function renderAuditoriaMobileRow(r) {
    const d = auditoriaDatos(r);
    return adminMobileRow(
        adminMobileSection(adminClientBlock({
            initial: adminInitial(r.email_admin || 'S'),
            name: d.label,
            phone: r.email_admin || 'Sistema',
            city: d.entity || '—',
        })) +
        (d.detalle ? adminMobileSection(`<div class="text-muted small text-truncate" title="${d.detalle}">${d.detalle}</div>`) : '') +
        adminMobileBottom(adminDateBlock(d.dt.fecha, d.dt.hora), '')
    );
}

function esc(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}
