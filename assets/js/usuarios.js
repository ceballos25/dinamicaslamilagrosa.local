const MODULE_ICONS = {
    dashboard: 'ti-chart-pie',
    usuarios: 'ti-users',
    roles: 'ti-shield',
    rifas: 'ti-ticket',
    transferencias: 'ti-building-bank',
    clientes: 'ti-address-book',
    reportes: 'ti-file-report',
    configuracion: 'ti-photo',
    auditoria: 'ti-history',
    ventas: 'ti-shopping-cart',
    settings: 'ti-settings',
};

function moduleIcon(modKey) {
    return MODULE_ICONS[String(modKey)] || 'ti-folder';
}

const USERS_ENDPOINT = '/front/ajax/usuarios.ajax.php';
const ROLES_ENDPOINT = '/front/ajax/roles.ajax.php';

const PAGE = window.USUARIOS_PAGE || {
    canUsers: true,
    canManageUsers: false,
    canRoles: true,
    canManageRoles: false,
    currentAdminId: 0,
};

let rolesCache = [];
let usuariosCache = [];
let permisosCatalogo = [];
let rolSeleccionado = null;
let rolEsSistema = false;
let permisosSeleccionados = new Set();

let modalUsuario;
let modalRol;

document.addEventListener('DOMContentLoaded', async () => {
    const modalUsuarioEl = document.getElementById('modalUsuario');
    const modalRolEl = document.getElementById('modalRol');
    if (modalUsuarioEl) modalUsuario = new bootstrap.Modal(modalUsuarioEl);
    if (modalRolEl) modalRol = new bootstrap.Modal(modalRolEl);

    document.getElementById('btnGuardarUsuario')?.addEventListener('click', guardarUsuario);
    document.getElementById('btnGuardarRol')?.addEventListener('click', guardarRol);

    renderToolbar();

    if (PAGE.canUsers) {
        await cargarRolesSelect();
        await cargarUsuarios();
    }
    if (PAGE.canRoles) {
        await cargarCatalogoPermisos();
        await cargarRolesLista();
    }
});

function renderToolbar() {
    const toolbar = document.getElementById('usuariosToolbar');
    if (!toolbar) return;

    const buttons = [];
    if (PAGE.canManageUsers) {
        buttons.push('<button type="button" class="btn btn-primary" id="btnNuevoUsuario"><i class="ti ti-plus"></i> Nuevo usuario</button>');
    }
    if (PAGE.canManageRoles) {
        buttons.push('<button type="button" class="btn btn-outline-primary" id="btnNuevoRol"><i class="ti ti-shield"></i> Nuevo rol</button>');
        buttons.push('<button type="button" class="btn btn-outline-secondary" id="btnSyncPermisos" title="Importa permisos nuevos al catálogo"><i class="ti ti-refresh"></i> Sincronizar permisos</button>');
    }
    toolbar.innerHTML = buttons.join('');

    document.getElementById('btnNuevoUsuario')?.addEventListener('click', abrirModalUsuario);
    document.getElementById('btnNuevoRol')?.addEventListener('click', () => abrirModalRol());
    document.getElementById('btnSyncPermisos')?.addEventListener('click', sincronizarPermisos);
}

async function apiUsers(action, extra = {}) {
    const fd = new FormData();
    fd.append('action', action);
    Object.entries(extra).forEach(([k, v]) => {
        if (v !== undefined && v !== null) fd.append(k, v);
    });
    const writeActions = ['crear', 'actualizar', 'cambiar_mi_password'];
    return adminFetchJson(USERS_ENDPOINT, {
        body: fd,
        withCsrf: writeActions.includes(action),
        writeActions,
    });
}

async function apiRoles(action, extra = {}) {
    const fd = new FormData();
    fd.append('action', action);
    Object.entries(extra).forEach(([k, v]) => {
        if (v !== undefined && v !== null) fd.append(k, v);
    });
    const writeActions = ['crear', 'actualizar', 'eliminar', 'asignar_permisos', 'sincronizar_catalogo'];
    return adminFetchJson(ROLES_ENDPOINT, {
        body: fd,
        withCsrf: writeActions.includes(action),
        writeActions,
    });
}

async function cargarRolesSelect() {
    try {
        const data = await apiUsers('roles');
        if (!data.success) return;
        rolesCache = data.data || [];
        const select = document.getElementById('usuarioRol');
        if (!select) return;
        select.innerHTML = rolesCache.map(r =>
            `<option value="${r.id_role}">${adminEscapeHtml(r.name_role)}</option>`
        ).join('');
    } catch (e) {
        adminNotifyError(e instanceof Error ? e.message : 'No se pudieron cargar los roles.');
    }
}

async function cargarUsuarios() {
    const tbody = document.getElementById('bodyUsuarios');
    if (!tbody) return;

    try {
        const data = await apiUsers('listar');
        if (!data.success) throw new Error(data.message || 'Error al listar');

        usuariosCache = data.data || [];
        if (!usuariosCache.length) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">Sin usuarios</td></tr>';
            return;
        }

        tbody.innerHTML = usuariosCache.map(u => renderUsuarioRow(u)).join('');

        if (typeof renderAdminMobileRows === 'function') {
            renderAdminMobileRows('usuariosMobile', usuariosCache.map(u => {
                const activo = parseInt(u.status_admin, 10) === 1;
                const editBtn = PAGE.canManageUsers
                    ? adminIconActions(adminIconBtn(`editarUsuario(${u.id_admin})`, 'ti-edit', 'primary', 'Editar'))
                    : '';
                return adminMobileRow(
                    adminMobileSection(`<div class="fw-bold text-dark text-truncate">${adminEscapeHtml(u.email_admin)}</div>`) +
                    `<div class="admin-mobile-row__fields">
                        <div class="admin-mobile-row__field"><div class="text-muted small">Rol</div><div><span class="badge bg-primary">${adminEscapeHtml(u.name_role || u.rol_admin || '—')}</span></div></div>
                        <div class="admin-mobile-row__field"><div class="text-muted small">Estado</div><div class="${activo ? 'text-success' : 'text-danger'} fw-medium">${activo ? 'Activo' : 'Inactivo'}</div></div>
                    </div>` +
                    adminMobileBottom('', editBtn)
                );
            }));
        }
    } catch (e) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-danger">Error al cargar</td></tr>';
        adminNotifyError(e instanceof Error ? e.message : 'No se pudieron cargar los usuarios.');
    }
}

function renderUsuarioRow(u) {
    const activo = parseInt(u.status_admin, 10) === 1;
    const acciones = PAGE.canManageUsers
        ? adminIconActions(adminIconBtn(`editarUsuario(${u.id_admin})`, 'ti-edit', 'primary', 'Editar'))
        : '<span class="text-muted small">—</span>';

    return `
        <tr class="align-middle">
            <td class="py-3 ps-4">${u.id_admin}</td>
            <td class="py-3">${adminEscapeHtml(u.email_admin)}</td>
            <td class="py-3"><span class="badge bg-primary">${adminEscapeHtml(u.name_role || u.rol_admin || '—')}</span></td>
            <td class="py-3">${activo ? '<span class="text-success fw-medium">Activo</span>' : '<span class="text-danger fw-medium">Inactivo</span>'}</td>
            <td class="py-3 text-muted small">${adminEscapeHtml(u.date_created_admin || '—')}</td>
            <td class="py-3 text-end">${acciones}</td>
        </tr>`;
}

function abrirModalUsuario() {
    document.getElementById('usuarioId').value = '';
    document.getElementById('usuarioEmail').value = '';
    document.getElementById('usuarioPass').value = '';
    document.getElementById('usuarioPass').required = true;
    document.getElementById('usuarioPassHelp').textContent = 'Obligatoria al crear (mínimo 8 caracteres).';
    document.getElementById('usuarioEstado').value = '1';
    document.getElementById('tituloModalUsuario').textContent = 'Nuevo usuario';
    if (rolesCache.length && document.getElementById('usuarioRol')) {
        document.getElementById('usuarioRol').value = rolesCache[0].id_role;
    }
    modalUsuario?.show();
}

function editarUsuario(id) {
    const u = usuariosCache.find(x => parseInt(x.id_admin, 10) === parseInt(id, 10));
    if (!u) return;

    document.getElementById('usuarioId').value = u.id_admin;
    document.getElementById('usuarioEmail').value = u.email_admin;
    document.getElementById('usuarioPass').value = '';
    document.getElementById('usuarioPass').required = false;
    document.getElementById('usuarioPassHelp').textContent = 'Déjala vacía para no cambiar la contraseña.';
    document.getElementById('usuarioRol').value = u.id_role || '';
    document.getElementById('usuarioEstado').value = u.status_admin;
    document.getElementById('tituloModalUsuario').textContent = 'Editar usuario';

    const esYo = parseInt(u.id_admin, 10) === parseInt(PAGE.currentAdminId, 10);
    document.getElementById('usuarioEstado').disabled = esYo;
    document.getElementById('usuarioRol').disabled = esYo;

    modalUsuario?.show();

    document.getElementById('modalUsuario').addEventListener('hidden.bs.modal', () => {
        document.getElementById('usuarioEstado').disabled = false;
        document.getElementById('usuarioRol').disabled = false;
    }, { once: true });
}

async function guardarUsuario() {
    const id = document.getElementById('usuarioId').value;
    const extra = {
        email_admin: document.getElementById('usuarioEmail').value.trim(),
        password_admin: document.getElementById('usuarioPass').value,
        id_role: document.getElementById('usuarioRol').value,
        status_admin: document.getElementById('usuarioEstado').value,
    };

    if (!extra.email_admin) {
        alertify.error('El email es obligatorio');
        return;
    }
    if (!id && (!extra.password_admin || extra.password_admin.length < 8)) {
        alertify.error('La contraseña debe tener al menos 8 caracteres');
        return;
    }
    if (id) extra.id_admin = id;

    try {
        const data = await apiUsers(id ? 'actualizar' : 'crear', extra);
        if (data.success) {
            modalUsuario?.hide();
            await cargarUsuarios();
            alertify.success(data.message || 'Guardado');
        } else {
            alertify.error(data.message || 'Error');
        }
    } catch (e) {
        adminNotifyError(e instanceof Error ? e.message : 'No se pudo guardar el usuario.');
    }
}

async function cargarCatalogoPermisos() {
    try {
        const data = await apiRoles('catalogo');
        if (data.success) {
            permisosCatalogo = data.data?.permissions || [];
        }
    } catch (e) {
        adminNotifyError(e instanceof Error ? e.message : 'No se pudo cargar el catálogo de permisos.');
    }
}

async function cargarRolesLista() {
    const lista = document.getElementById('listaRoles');
    if (!lista) return;

    try {
        const data = await apiRoles('listar');
        if (!data.success) throw new Error(data.message || 'Error');

        rolesCache = data.data || [];
        if (!rolesCache.length) {
            lista.innerHTML = '<div class="auth-roles-nav__placeholder text-muted">Sin roles</div>';
            return;
        }

        lista.innerHTML = rolesCache.map(r => {
            const active = rolSeleccionado === parseInt(r.id_role, 10) ? ' is-active' : '';
            const count = parseInt(r.admin_count || 0, 10);
            return `
            <button type="button" class="auth-roles-nav__item${active}" onclick="seleccionarRol(${r.id_role})">
                <span class="auth-roles-nav__label">${adminEscapeHtml(r.name_role)}</span>
                <span class="auth-roles-nav__meta">
                    <span class="auth-roles-nav__slug">${adminEscapeHtml(r.slug_role)}</span>
                    ${count > 0 ? `<span class="auth-roles-nav__count">${count}</span>` : ''}
                </span>
            </button>`;
        }).join('');

        if (!rolSeleccionado && rolesCache.length) {
            seleccionarRol(rolesCache[0].id_role);
        }
    } catch (e) {
        lista.innerHTML = '<div class="auth-roles-nav__placeholder text-danger">Error al cargar</div>';
    }
}

async function seleccionarRol(id) {
    rolSeleccionado = parseInt(id, 10);
    await cargarRolesLista();

    try {
        const data = await apiRoles('obtener', { id_role: id });
        if (!data.success) throw new Error(data.message || 'Error');

        const role = data.data.role;
        const ids = data.data.permission_ids || [];
        const roleMeta = rolesCache.find(r => parseInt(r.id_role, 10) === parseInt(id, 10)) || {};
        role.admin_count = roleMeta.admin_count || 0;
        permisosSeleccionados = new Set(ids.map(x => parseInt(x, 10)));

        document.getElementById('tituloRolSeleccionado').textContent = role.name_role;
        const sub = document.getElementById('subtituloRolSeleccionado');
        if (sub) {
            const n = parseInt(role.admin_count || 0, 10);
            sub.textContent = n === 1 ? '1 usuario con este rol' : `${n} usuarios con este rol`;
        }
        rolEsSistema = !!data.data.is_system;
        const badge = document.getElementById('badgeRolSistema');
        if (badge) {
            badge.style.display = rolEsSistema ? 'inline' : 'none';
        }
        updatePermCountBadge();

        renderMatrizPermisos(role, data.data.is_system);
    } catch (e) {
        adminNotifyError(e instanceof Error ? e.message : 'No se pudo cargar el rol.');
    }
}

function updatePermCountBadge() {
    const el = document.getElementById('badgePermCount');
    if (!el) return;
    const n = permisosSeleccionados.size;
    const total = permisosCatalogo.length;
    el.textContent = total > 0 ? `${n} de ${total} activos` : `${n} activos`;
    el.style.display = rolSeleccionado ? 'inline' : 'none';
}

function renderMatrizPermisos(role, isSystem) {
    const panel = document.getElementById('panelPermisos');
    if (!panel) return;

    const grouped = {};
    permisosCatalogo.forEach(p => {
        const modKey = p.module || 'otros';
        if (!grouped[modKey]) {
            grouped[modKey] = { label: p.module_label || modKey, items: [] };
        }
        grouped[modKey].items.push(p);
    });

    const canEdit = PAGE.canManageRoles;
    let html = '';

    if (canEdit) {
        html += `<div class="d-flex flex-wrap gap-2 mb-3 pb-3 border-bottom">
            <button type="button" class="btn btn-sm btn-outline-primary btn-editar-rol" data-id="${role.id_role}">
                <i class="ti ti-edit"></i> Editar
            </button>`;
        if (!isSystem && parseInt(role.admin_count || 0, 10) === 0) {
            html += `<button type="button" class="btn btn-sm btn-outline-danger btn-eliminar-rol" data-id="${role.id_role}">
                <i class="ti ti-trash"></i> Eliminar
            </button>`;
        }
        html += '</div>';
    }

    html += '<div class="auth-perm-list">';
    Object.entries(grouped).forEach(([modKey, group]) => {
        const items = group.items;
        const allChecked = items.every(p => permisosSeleccionados.has(parseInt(p.id_permission, 10)));
        const someChecked = items.some(p => permisosSeleccionados.has(parseInt(p.id_permission, 10)));
        const moduleId = `mod_${modKey.replace(/[^a-z0-9]/gi, '_')}`;
        const icon = moduleIcon(modKey);

        html += `<section class="auth-perm-section">
            <header class="auth-perm-section__head">
                <div class="form-check form-switch mb-0 auth-perm-section__switch">
                    <input class="form-check-input auth-perm-module-toggle" type="checkbox" role="switch"
                        id="${moduleId}_all" data-module="${adminEscapeHtml(modKey)}"
                        ${allChecked ? 'checked' : ''} ${someChecked && !allChecked ? 'data-indeterminate="1"' : ''}
                        ${canEdit ? '' : 'disabled'}
                        onchange="toggleModuloPermisos('${modKey.replace(/'/g, "\\'")}', this.checked)">
                    <label class="form-check-label fw-bold" for="${moduleId}_all">
                        <i class="ti ${icon} me-1 text-primary"></i>${adminEscapeHtml(group.label)}
                    </label>
                </div>
            </header>
            <div class="auth-perm-section__rows">`;

        items.forEach(p => {
            const pid = parseInt(p.id_permission, 10);
            const checked = permisosSeleccionados.has(pid) ? 'checked' : '';
            const disabled = !canEdit ? 'disabled' : '';
            const rowId = `perm_${pid}`;
            html += `<div class="auth-perm-row">
                <div class="form-check form-switch mb-0">
                    <input class="form-check-input" type="checkbox" role="switch" id="${rowId}" value="${pid}"
                        ${checked} ${disabled}
                        onchange="togglePermiso(${pid}, this.checked); updatePermCountBadge();">
                    <label class="form-check-label" for="${rowId}">${adminEscapeHtml(p.action_label || p.action)}</label>
                </div>
            </div>`;
        });

        html += '</div></section>';
    });
    html += '</div>';

    if (canEdit) {
        html += `<div class="auth-perm-footer">
            <button type="button" class="btn btn-primary" onclick="guardarPermisosRol()">
                <i class="ti ti-device-floppy"></i> Guardar
            </button>
        </div>`;
    }

    panel.innerHTML = html;

    panel.querySelectorAll('.auth-perm-module-toggle[data-indeterminate="1"]').forEach(el => {
        el.indeterminate = true;
    });

    panel.querySelector('.btn-editar-rol')?.addEventListener('click', () => {
        abrirModalRol(role.id_role, role.name_role, role.slug_role, isSystem);
    });
    panel.querySelector('.btn-eliminar-rol')?.addEventListener('click', () => eliminarRol(role.id_role));
}

function toggleModuloPermisos(modKey, checked) {
    permisosCatalogo
        .filter(p => String(p.module) === String(modKey))
        .forEach(p => {
            const id = parseInt(p.id_permission, 10);
            if (checked) permisosSeleccionados.add(id);
            else permisosSeleccionados.delete(id);
        });
    updatePermCountBadge();
    if (rolSeleccionado) {
        const role = rolesCache.find(r => parseInt(r.id_role, 10) === rolSeleccionado);
        if (role) {
            renderMatrizPermisos(role, rolEsSistema);
        }
    }
}

function togglePermiso(id, checked) {
    id = parseInt(id, 10);
    if (checked) permisosSeleccionados.add(id);
    else permisosSeleccionados.delete(id);
    updatePermCountBadge();
}

async function guardarPermisosRol() {
    if (!rolSeleccionado) return;
    try {
        const data = await apiRoles('asignar_permisos', {
            id_role: rolSeleccionado,
            permission_ids: JSON.stringify([...permisosSeleccionados]),
        });
        if (data.success) {
            alertify.success(data.message || 'Permisos guardados');
        } else {
            alertify.error(data.message || 'Error');
        }
    } catch (e) {
        adminNotifyError(e instanceof Error ? e.message : 'No se pudieron guardar los permisos.');
    }
}

function abrirModalRol(id = null, name = '', slug = '', lockSlug = false) {
    document.getElementById('rolId').value = id || '';
    document.getElementById('rolNombre').value = name;
    document.getElementById('rolSlug').value = slug;
    document.getElementById('rolSlug').disabled = !!lockSlug;
    document.getElementById('tituloModalRol').textContent = id ? 'Editar rol' : 'Nuevo rol';
    modalRol?.show();
}

async function guardarRol() {
    const id = document.getElementById('rolId').value;
    const extra = {
        name_role: document.getElementById('rolNombre').value.trim(),
        slug_role: document.getElementById('rolSlug').value.trim(),
    };
    if (!extra.name_role) {
        alertify.error('El nombre del rol es obligatorio');
        return;
    }
    if (id) extra.id_role = id;

    try {
        const data = await apiRoles(id ? 'actualizar' : 'crear', extra);
        if (data.success) {
            modalRol?.hide();
            await cargarRolesLista();
            if (PAGE.canUsers) await cargarRolesSelect();
            if (id) await seleccionarRol(id);
            else if (data.id_role) await seleccionarRol(data.id_role);
            alertify.success(data.message || 'Rol guardado');
        } else {
            alertify.error(data.message || 'Error');
        }
    } catch (e) {
        adminNotifyError(e instanceof Error ? e.message : 'No se pudo guardar el rol.');
    }
}

async function eliminarRol(id) {
    if (!confirm('¿Eliminar este rol? Solo es posible si no tiene usuarios asignados.')) return;
    try {
        const data = await apiRoles('eliminar', { id_role: id });
        if (data.success) {
            rolSeleccionado = null;
            await cargarRolesLista();
            document.getElementById('tituloRolSeleccionado').textContent = '—';
            const sub = document.getElementById('subtituloRolSeleccionado');
            if (sub) sub.textContent = 'Elige un rol a la izquierda.';
            document.getElementById('panelPermisos').innerHTML = '<p class="text-muted mb-0 py-4 text-center small">Selecciona un rol para ver sus permisos.</p>';
            alertify.success(data.message || 'Rol eliminado');
        } else {
            alertify.error(data.message || 'Error');
        }
    } catch (e) {
        adminNotifyError(e instanceof Error ? e.message : 'No se pudo eliminar el rol.');
    }
}

async function sincronizarPermisos() {
    try {
        const data = await apiRoles('sincronizar_catalogo');
        if (data.success) {
            await cargarCatalogoPermisos();
            if (rolSeleccionado) await seleccionarRol(rolSeleccionado);
            alertify.success(`${data.message || 'Listo'} (+${data.inserted_permissions || 0} nuevos, ${data.roles_repaired || 0} roles reparados)`);
        } else {
            alertify.error(data.message || 'Error');
        }
    } catch (e) {
        adminNotifyError(e instanceof Error ? e.message : 'No se pudo sincronizar.');
    }
}

window.abrirModalUsuario = abrirModalUsuario;
window.editarUsuario = editarUsuario;
window.seleccionarRol = seleccionarRol;
window.togglePermiso = togglePermiso;
window.toggleModuloPermisos = toggleModuloPermisos;
window.guardarPermisosRol = guardarPermisosRol;
window.eliminarRol = eliminarRol;
