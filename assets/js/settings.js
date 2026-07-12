let SETTINGS = {};
let SETTINGS_ROWS = [];
let SETTINGS_LOADED = false;
let SETTINGS_PROMISE = null;

const SETTING_UI_META = {
    maintenance_mode: {
        title: 'Modo mantenimiento',
        help: 'Actívalo cuando quieras cerrar la página al público por un rato. Tú y tu equipo podéis seguir entrando con normalidad.',
        type: 'toggle',
        section: 'web',
    },
    maintenance_message: {
        title: 'Mensaje para visitantes',
        help: 'Lo que verán quienes entren mientras el sitio está en mantenimiento.',
        placeholder: 'Ej: Estamos actualizando la página. Volvemos pronto.',
        type: 'textarea',
        section: 'web',
    },
    web_compras_habilitadas: {
        title: 'Permitir compras en la web',
        help: 'Encendido: pueden pagar en línea y subir comprobante. Apagado: solo ven información y consultan números, sin comprar.',
        type: 'toggle',
        section: 'web',
    },
    web_mensaje_compras_bloqueadas: {
        title: 'Aviso cuando las compras están pausadas',
        help: 'Texto de la franja roja en la web. Si lo dejas vacío, usamos un mensaje genérico.',
        placeholder: 'Ej: Las compras en línea están pausadas por ahora.',
        type: 'textarea',
        section: 'web',
    },
    comprobante_validation_enabled: {
        title: 'Validar comprobantes de pago (OCR)',
        help: 'Encendido: en el checkout revisa la imagen (monto, duplicados, si parece un recibo) y rechaza las dudosas. Apagado: acepta cualquier imagen. En ambos casos la compra queda pendiente: un administrador debe aprobarla en Transferencias.',
        type: 'toggle',
        section: 'web',
    },
    web_id_raffle: {
        title: 'Rifa que se muestra en la página principal',
        help: 'Se actualiza solo al crear o activar una rifa. Si pausas la rifa actual, pasa a la última rifa activa. El ID lo ves en Rifas.',
        placeholder: 'Ej: 1',
        type: 'text',
        section: 'web',
    },
    barra: {
        title: 'Ajuste de la barra de progreso',
        help: 'Usa el panel de abajo: suma un extra al % real o congélalo en un valor fijo.',
        placeholder: '0',
        type: 'text',
        section: 'web',
        panel: 'barra',
    },
    barra_modo: {
        title: 'Modo barra de progreso',
        help: 'sumar = real + extra. fija = porcentaje congelado.',
        type: 'hidden',
        section: 'web',
        panel: 'barra',
    },
    barra_fija: {
        title: 'Porcentaje fijo de la barra',
        help: 'Solo aplica en modo fija.',
        placeholder: '35',
        type: 'hidden',
        section: 'web',
        panel: 'barra',
    },
    nombre_rifa: {
        title: 'Nombre del sitio / marca',
        help: 'Aparece en footer, títulos y textos de marca.',
        placeholder: 'Dinámicas La Milagrosa',
        type: 'text',
        section: 'landing',
    },
    landing_meta_description: {
        title: 'Descripción SEO (meta)',
        help: 'Texto que ven Google y redes al compartir el enlace.',
        placeholder: 'Descripción de la dinámica…',
        type: 'textarea',
        section: 'landing',
    },
    landing_hero_title: {
        title: 'Título del hero',
        help: 'Título principal de la portada. Usa \\n para saltos de línea si hace falta.',
        placeholder: '¡Gana una Yamaha NMAX!',
        type: 'text',
        section: 'landing',
    },
    landing_premio_mayor_titulo: {
        title: 'Premio mayor — título',
        help: 'Etiqueta del bloque de premio mayor.',
        placeholder: 'Premio mayor',
        type: 'text',
        section: 'landing',
    },
    landing_premio_mayor_desc: {
        title: 'Premio mayor — descripción',
        help: 'Detalle del premio mayor (moto, lotería, fecha…).',
        placeholder: 'Yamaha NMAX · Lotería de Boyacá · Sábado 22 de agosto',
        type: 'text',
        section: 'landing',
    },
    landing_premio_extra_titulo: {
        title: 'Premios extra — título',
        help: 'Etiqueta del bloque de premios adicionales.',
        placeholder: 'Premios adicionales',
        type: 'text',
        section: 'landing',
    },
    landing_premio_extra_label: {
        title: 'Premios extra — etiqueta',
        help: 'Texto destacado (ej. 6 números bendecidos).',
        placeholder: '6 números bendecidos',
        type: 'text',
        section: 'landing',
    },
    landing_premio_extra_desc: {
        title: 'Premios extra — valor',
        help: 'Detalle o monto (ej. $500.000 c/u).',
        placeholder: '$500.000 c/u',
        type: 'text',
        section: 'landing',
    },
    footer_tagline: {
        title: 'Footer — texto de marca',
        help: 'Párrafo bajo el nombre en el pie de página.',
        placeholder: 'Participa fácil, rápido y seguro…',
        type: 'textarea',
        section: 'landing',
    },
    footer_phones: {
        title: 'Footer — teléfonos',
        help: 'Números visibles en contacto del pie.',
        placeholder: '320 259 5153 · 320 259 5184',
        type: 'text',
        section: 'landing',
    },
    contact_email: {
        title: 'Correo de contacto',
        help: 'Email del pie y formularios de contacto.',
        placeholder: 'contacto@ejemplo.com',
        type: 'text',
        section: 'landing',
    },
    footer_location: {
        title: 'Footer — ubicación',
        help: 'Ciudad o país en el pie.',
        placeholder: 'Colombia',
        type: 'text',
        section: 'landing',
    },
    footer_payments: {
        title: 'Footer — medios de pago',
        help: 'Texto de transferencias (Bre-B, Bancolombia, Nequi…).',
        placeholder: 'Bre-B · Bancolombia · Nequi',
        type: 'text',
        section: 'landing',
    },
    whatsapp: {
        title: 'Número de WhatsApp',
        help: 'Solo números, con indicativo. Ejemplo Colombia: 573001234567',
        placeholder: '573001234567',
        type: 'text',
        section: 'contacto',
    },
    whatsapp_secondary: {
        title: 'WhatsApp secundario',
        help: 'Segundo celular (solo dígitos con indicativo).',
        placeholder: '573001234568',
        type: 'text',
        section: 'contacto',
    },
    whatsapp_chat_url: {
        title: 'Enlace de WhatsApp (opcional)',
        help: 'Si tienes un enlace wa.me completo, pégalo aquí. Tiene prioridad sobre el número de arriba.',
        placeholder: 'https://wa.me/573001234567',
        type: 'text',
        section: 'contacto',
    },
    whatsapp_group_url: {
        title: 'Grupo de WhatsApp (comprobante)',
        help: 'Enlace de invitación al grupo. Aparece en el botón «Ir al grupo» del comprobante de venta.',
        placeholder: 'https://chat.whatsapp.com/...',
        type: 'text',
        section: 'contacto',
    },
    social_instagram_url: {
        title: 'Instagram',
        help: 'Enlace a tu perfil o página de Instagram.',
        placeholder: 'https://instagram.com/tu_cuenta',
        type: 'text',
        section: 'contacto',
    },
    social_facebook_url: {
        title: 'Facebook',
        help: 'Enlace a tu página de Facebook.',
        placeholder: 'https://facebook.com/tu_pagina',
        type: 'text',
        section: 'contacto',
    },
    social_tiktok_url: {
        title: 'TikTok',
        help: 'Enlace a tu perfil de TikTok.',
        placeholder: 'https://www.tiktok.com/@tu_cuenta',
        type: 'text',
        section: 'contacto',
    },
    pricing_packages_enabled: {
        title: 'Paquetes de compra (web)',
        help: 'Encendido: la web muestra paquetes listos (10, 18, 30…). Apagado: solo cantidad libre.',
        type: 'toggle',
        section: 'precios',
    },
    pricing_tiered_enabled: {
        title: 'Precios por tramos (web)',
        help: 'Encendido: usa precios diferenciados por cantidad (umbral o 1.º/2.º/3+).',
        type: 'toggle',
        section: 'precios',
    },
    pricing_first_unit: {
        title: 'Precio unitario base (COP)',
        help: 'Precio por número debajo del umbral (ej. 1500).',
        placeholder: '1500',
        type: 'text',
        section: 'precios',
    },
    pricing_tier1_qty: {
        title: 'Cantidad precio normal (legacy)',
        help: 'Obsoleto: ya no se usa. Precios por posición (1.º, 2.º, 3+).',
        placeholder: '2',
        type: 'text',
        section: 'precios',
    },
    pricing_tier1_unit: {
        title: 'Precio 2.º número (COP)',
        help: 'Solo modo legacy sin umbral. Ej: 1500',
        placeholder: '1500',
        type: 'text',
        section: 'precios',
    },
    pricing_tier2_unit: {
        title: 'Precio desde umbral / 3+ (COP)',
        help: 'Con umbral: precio c/u desde esa cantidad. Sin umbral: precio de 3 o más.',
        placeholder: '1200',
        type: 'text',
        section: 'precios',
    },
    pricing_bulk_threshold: {
        title: 'Umbral de descuento (cantidad)',
        help: 'Desde esta cantidad aplica el precio reducido. Ej: 40. Déjalo en 0 para modo 1/2/3+.',
        placeholder: '40',
        type: 'text',
        section: 'precios',
    },
    transfer_breb_key: {
        title: 'Llave Bre-B',
        help: 'Llave o número Bre-B para transferencias.',
        placeholder: '@astrid59202',
        type: 'text',
        section: 'contacto',
    },
    transfer_breb_name: {
        title: 'Nombre Bre-B',
        help: 'Nombre del titular de la llave Bre-B.',
        placeholder: 'Astrid',
        type: 'text',
        section: 'contacto',
    },
    transfer_bancolombia: {
        title: 'Cuenta Bancolombia',
        help: 'Número de cuenta de ahorros Bancolombia.',
        placeholder: '91209541721',
        type: 'text',
        section: 'contacto',
    },
    transfer_bancolombia_name: {
        title: 'Titular Bancolombia',
        help: 'Nombre del titular de la cuenta Bancolombia.',
        placeholder: 'Astrid',
        type: 'text',
        section: 'contacto',
    },
    transfer_nequi: {
        title: 'Nequi',
        help: 'Número Nequi para transferencias.',
        placeholder: '3156129798',
        type: 'text',
        section: 'contacto',
    },
    transfer_nequi_name: {
        title: 'Titular Nequi',
        help: 'Nombre del titular Nequi.',
        placeholder: 'Astrid',
        type: 'text',
        section: 'contacto',
    },
};

const SETTING_SECTIONS = [
    {
        id: 'web',
        title: 'Página web y ventas',
        subtitle: 'Qué ven los visitantes y si pueden comprar en línea.',
    },
    {
        id: 'landing',
        title: 'Textos de la portada',
        subtitle: 'Hero, premios, SEO y pie de página. Todo editable sin tocar código.',
    },
    {
        id: 'precios',
        title: 'Precios por cantidad',
        subtitle: 'Paquetes, umbrales y precios unitarios (web y checkout).',
    },
    {
        id: 'contacto',
        title: 'Contacto y redes',
        subtitle: 'WhatsApp, transferencias e iconos de redes.',
    },
    {
        id: 'otros',
        title: 'Otros ajustes',
        subtitle: 'Opciones adicionales. Si no estás seguro, déjalas como están.',
    },
];

const SETTING_UI_ORDER = [
    'maintenance_mode',
    'maintenance_message',
    'web_compras_habilitadas',
    'web_mensaje_compras_bloqueadas',
    'comprobante_validation_enabled',
    'web_id_raffle',
    'barra',
    'barra_modo',
    'barra_fija',
    'nombre_rifa',
    'landing_meta_description',
    'landing_hero_title',
    'landing_premio_mayor_titulo',
    'landing_premio_mayor_desc',
    'landing_premio_extra_titulo',
    'landing_premio_extra_label',
    'landing_premio_extra_desc',
    'footer_tagline',
    'footer_phones',
    'contact_email',
    'footer_location',
    'footer_payments',
    'pricing_packages_enabled',
    'pricing_tiered_enabled',
    'pricing_first_unit',
    'pricing_tier1_unit',
    'pricing_tier2_unit',
    'pricing_bulk_threshold',
    'whatsapp',
    'whatsapp_secondary',
    'whatsapp_chat_url',
    'whatsapp_group_url',
    'social_instagram_url',
    'social_facebook_url',
    'social_tiktok_url',
    'transfer_breb_key',
    'transfer_breb_name',
    'transfer_bancolombia',
    'transfer_bancolombia_name',
    'transfer_nequi',
    'transfer_nequi_name',
];

document.addEventListener('DOMContentLoaded', async () => {
    initSettingsAlerts();

    if (document.getElementById('settingsContainer')) {
        await cargarSettingsGlobal();
        renderSettingsFromGlobal();
    }
});

function escapeHtml(text) {
    return String(text ?? '')
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

function initSettingsAlerts() {
    if (typeof alertify === 'undefined') return;
    alertify.set('notifier', 'position', 'top-center');
    alertify.set('notifier', 'delay', 5);
}

function settingLabelForKey(key) {
    return metaForKey(key).title || key;
}

function showSettingsToast(type, message) {
    initSettingsAlerts();
    const msg = String(message ?? '').trim();
    if (!msg) return;

    if (typeof alertify !== 'undefined') {
        if (typeof alertify.dismissAll === 'function') {
            alertify.dismissAll();
        }
        if (type === 'error') {
            alertify.error(msg);
        } else {
            alertify.success(msg);
        }
        return;
    }

    if (type === 'error' && typeof toastError === 'function') {
        toastError(msg);
        return;
    }
    if (type !== 'error' && typeof toastSuccess === 'function') {
        toastSuccess(msg);
    }
}

function notifySettingSaved(label) {
    const name = String(label ?? '').trim();
    const msg = name ? `✓ Guardado: ${name}` : '✓ Cambio guardado correctamente';
    showSettingsToast('success', msg);
}

function notifySettingError(message) {
    const msg = String(message ?? '').trim() || 'No se pudo guardar. Revisa los datos e intenta de nuevo.';
    showSettingsToast('error', `✕ ${msg}`);
}

function flashSettingRow(key) {
    const row = document.querySelector(`.settings-row[data-setting-key="${CSS.escape(String(key))}"]`);
    if (!row) return;
    row.classList.remove('settings-row--saved');
    void row.offsetWidth;
    row.classList.add('settings-row--saved');
    window.setTimeout(() => row.classList.remove('settings-row--saved'), 1800);
}

function getSetting(key, defaultValue = null) {
    return SETTINGS[key] ?? defaultValue;
}

function settingsCsrfToken() {
    if (typeof adminCsrfToken === 'function') {
        return adminCsrfToken();
    }
    if (window.PUBLIC_CSRF_TOKEN) {
        return window.PUBLIC_CSRF_TOKEN;
    }
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? (meta.getAttribute('content') || '') : '';
}

function settingsAppendCsrf(fd) {
    if (fd instanceof FormData && !fd.has('csrf_token')) {
        const token = settingsCsrfToken();
        if (token) {
            fd.append('csrf_token', token);
        }
    }
    return fd;
}

async function fetchSettings(action, extra = {}) {

    const fd = new FormData();
    fd.append('action', action);

    Object.entries(extra).forEach(([k, v]) => fd.append(k, v));

    const writeActions = ['actualizar', 'crear', 'eliminar'];
    if (writeActions.includes(action)) {
        settingsAppendCsrf(fd);
    }

    const token = settingsCsrfToken();
    const res = await fetch('/front/ajax/settings.ajax.php', {
        method: 'POST',
        body: fd,
        credentials: 'same-origin',
        headers: token ? { 'X-CSRF-Token': token } : {},
    });

    const text = await res.text();
    let data;
    try {
        data = JSON.parse(text.trim());
    } catch {
        throw new Error('El servidor respondió de forma inesperada. Recarga la página.');
    }

    if (!data.success) {
        if (data.code === 'csrf_invalid') {
            throw new Error(data.message || 'Sesión expirada. Recarga la página (F5) e intenta de nuevo.');
        }
        throw new Error(data.message || 'No se pudo completar la operación.');
    }

    return data;
}

async function cargarSettingsGlobal(force = false) {

    if (force) {
        SETTINGS_LOADED = false;
        SETTINGS_PROMISE = null;
    }

    if (SETTINGS_LOADED) return SETTINGS;

    if (SETTINGS_PROMISE) return SETTINGS_PROMISE;

    SETTINGS_PROMISE = (async () => {

        try {

            const data = await fetchSettings('obtener');

            SETTINGS = {};
            SETTINGS_ROWS = Array.isArray(data.data) ? data.data : [];

            SETTINGS_ROWS.forEach(s => {
                SETTINGS[s.key_setting] = s.value_setting;
            });

            SETTINGS_LOADED = true;

            return SETTINGS;

        } catch (e) {
            if (typeof notifySettingError === 'function') {
                notifySettingError(e instanceof Error ? e.message : 'Error cargando ajustes');
            } else if (typeof adminNotifyError === 'function') {
                adminNotifyError(e instanceof Error ? e.message : 'Error cargando ajustes');
            }
            return {};
        }

    })();

    return SETTINGS_PROMISE;
}

function isSettingTruthyValue(val) {
    const v = String(val ?? '').trim().toLowerCase();
    return !['0', 'false', 'no', 'off'].includes(v);
}

function onSettingsReady(callback) {
    cargarSettingsGlobal().then(callback);
}

function metaForKey(key) {
    return (
        SETTING_UI_META[key] || {
            title: key.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase()),
            help: 'Opción personalizada. Si no la reconoces, consulta antes de cambiarla.',
            type: 'text',
            section: 'avanzado',
        }
    );
}

function isKnownSettingKey(key) {
    return Object.prototype.hasOwnProperty.call(SETTING_UI_META, key);
}

function renderSettingControl(key, meta, val) {
    if (meta.type === 'toggle') {
        const checked = isSettingTruthyValue(val) ? 'checked' : '';
        return `
            <div class="form-check form-switch mb-0">
                <input class="form-check-input" type="checkbox" role="switch" id="toggle-${escapeHtml(key)}"
                    data-key="${escapeHtml(key)}"
                    data-boolean-toggle="1"
                    ${checked}
                    onchange="guardarToggleSetting('${escapeHtml(key)}', this)">
                <label class="form-check-label small" for="toggle-${escapeHtml(key)}">
                    <span class="text-muted toggle-state-label" data-for-key="${escapeHtml(key)}"></span>
                </label>
            </div>
        `;
    }
    if (meta.type === 'textarea') {
        const ph = meta.placeholder ? ` placeholder="${escapeHtml(meta.placeholder)}"` : '';
        return `
            <textarea class="form-control form-control-sm" rows="2" data-key="${escapeHtml(key)}"${ph}>${escapeHtml(val)}</textarea>
        `;
    }
    const ph = meta.placeholder ? ` placeholder="${escapeHtml(meta.placeholder)}"` : '';
    return `
        <input type="text" class="form-control form-control-sm" data-key="${escapeHtml(key)}"
            value="${escapeHtml(val)}"${ph}>
    `;
}

function renderSettingRow(s, options = {}) {
    const { showDelete = false } = options;
    const key = s.key_setting;
    const meta = metaForKey(key);
    const val = s.value_setting ?? '';
    const id = s.id_setting;
    const showSave = meta.type !== 'toggle';
    const known = isKnownSettingKey(key);

    return `
    <div class="settings-row row g-3 align-items-start" data-setting-key="${escapeHtml(key)}">
        <div class="col-lg-4">
            <div class="settings-title">${escapeHtml(meta.title)}</div>
            ${known ? '' : `<div class="settings-code text-muted small mb-1"><span class="badge bg-light text-muted border fw-normal">${escapeHtml(key)}</span></div>`}
            <p class="settings-help mb-0">${escapeHtml(meta.help)}</p>
        </div>
        <div class="col-lg-5">
            ${renderSettingControl(key, meta, val)}
        </div>
        <div class="col-lg-3 text-lg-end">
            ${showSave ? `
                <button type="button" class="btn btn-sm btn-success btn-save-row"
                    onclick="guardarIndividualDesdeUI('${escapeHtml(key)}', this)">
                    <i class="ti ti-device-floppy me-1"></i> Guardar
                </button>
            ` : `
                <span class="small text-success"><i class="ti ti-bolt me-1"></i>Guardado automático</span>
            `}
            ${showDelete ? `
                <button type="button" class="btn btn-sm btn-outline-danger ms-lg-2 mt-2 mt-lg-0 d-inline-block"
                    title="Eliminar"
                    onclick="eliminarSetting(${Number(id)})"
                    ${['precio_ticket', 'max_tickets', 'min_tickets'].includes(key) ? 'disabled' : ''}>
                    <i class="ti ti-trash"></i>
                </button>
            ` : ''}
        </div>
    </div>
    `;
}

function renderSettingsSectionHeader(section) {
    return `
    <div class="settings-section-header">
        <h6 class="settings-section-title mb-0">${escapeHtml(section.title)}</h6>
        <p class="settings-section-subtitle mb-0">${escapeHtml(section.subtitle)}</p>
    </div>`;
}

function sortSettingsRows(rows) {
    const seen = new Set();
    const ordered = [];

    SETTING_UI_ORDER.forEach((k) => {
        const row = rows.find((r) => r.key_setting === k);
        if (row) {
            ordered.push(row);
            seen.add(k);
        }
    });

    const rest = rows
        .filter((r) => !seen.has(r.key_setting))
        .sort((a, b) => String(a.key_setting).localeCompare(String(b.key_setting)));

    return ordered.concat(rest);
}

function renderSettingsFromGlobal() {

    const container = document.getElementById('settingsContainer');
    if (!container) return;

    const rows = sortSettingsRows([...SETTINGS_ROWS]);
    const rowsByKey = Object.fromEntries(rows.map((r) => [r.key_setting, r]));

    if (!rows.length) {
        container.innerHTML = `<div class="text-center py-5 text-muted">No hay opciones configuradas todavía.</div>`;
        return;
    }

    let html = '';

    SETTING_SECTIONS.forEach((section) => {
        const sectionRows = SETTING_UI_ORDER
            .filter((k) => (SETTING_UI_META[k]?.section || '') === section.id)
            .filter((k) => SETTING_UI_META[k]?.panel !== 'barra')
            .map((k) => rowsByKey[k])
            .filter(Boolean);

        if (!sectionRows.length && section.id !== 'web') return;

        if (sectionRows.length) {
            html += renderSettingsSectionHeader(section);
            html += sectionRows.map((s) => renderSettingRow(s)).join('');
        }

        if (section.id === 'web') {
            html += renderBarraProgresoPanel();
        }
    });

    const knownSet = new Set(SETTING_UI_ORDER);
    const HIDDEN_EXTRA_KEYS = new Set([
        'comprobante_ocr_space_api_key',
        'comprobante_tesseract_path',
        'comprobante_ocr_provider',
    ]);
    const extraRows = rows
        .filter((r) => !knownSet.has(r.key_setting))
        .filter((r) => SETTING_UI_META[r.key_setting]?.panel !== 'barra')
        .filter((r) => !HIDDEN_EXTRA_KEYS.has(r.key_setting))
        .filter((r) => !/_api_key$|_secret$|_password$/i.test(String(r.key_setting)));
    if (extraRows.length) {
        html += renderSettingsSectionHeader({
            title: 'Opciones adicionales',
            subtitle: 'Parámetros personalizados. Elimínalos solo si sabes para qué sirven.',
        });
        html += extraRows.map((s) => renderSettingRow(s, { showDelete: true })).join('');
    }

    container.innerHTML = html;

    rows.forEach((s) => {
        if (metaForKey(s.key_setting).type === 'toggle') {
            updateToggleLabel(s.key_setting);
        }
    });

    initBarraProgresoPanel();
}

function renderBarraProgresoPanel() {
    const boost = String(getSetting('barra', '0') ?? '0');
    const fija = String(getSetting('barra_fija', '') ?? '');
    const modoFija = isBarraModoFija();

    return `
    <div class="settings-row barra-progreso-panel row g-3 align-items-start" id="barraProgresoPanel">
        <div class="col-lg-4">
            <div class="settings-title">Barra de progreso en la web</div>
            <p class="settings-help mb-0">
                <strong>Sumar:</strong> el % real de ventas más un extra (ej. +20). Sube con cada venta.<br>
                <strong>Congelar:</strong> queda fijo en el valor que elijas y no sube aunque sigan vendiendo.
            </p>
        </div>
        <div class="col-lg-8">
            <div class="d-flex flex-wrap gap-3 mb-3 small">
                <span class="badge bg-light text-dark border">Real vendido: <strong id="barraPanelReal">—</strong></span>
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle">Visible web: <strong id="barraPanelVisible">—</strong></span>
                <span class="badge ${modoFija ? 'bg-warning text-dark' : 'bg-success'}" id="barraPanelModoBadge">${modoFija ? 'Congelada' : 'Sumando'}</span>
            </div>
            <div class="row g-2 align-items-end">
                <div class="col-sm-4">
                    <label class="form-label small text-muted mb-1">Extra a sumar (+)</label>
                    <input type="text" class="form-control form-control-sm" id="barraBoostInput"
                        value="${escapeHtml(boost)}" placeholder="20">
                </div>
                <div class="col-sm-4">
                    <label class="form-label small text-muted mb-1">Valor fijo (%)</label>
                    <input type="text" class="form-control form-control-sm" id="barraFijaInput"
                        value="${escapeHtml(fija)}" placeholder="35">
                </div>
                <div class="col-sm-4 d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-sm btn-success flex-grow-1" id="btnBarraSumar">
                        <i class="ti ti-trending-up me-1"></i> Seguir sumando
                    </button>
                    <button type="button" class="btn btn-sm btn-warning flex-grow-1" id="btnBarraCongelar">
                        <i class="ti ti-pinned me-1"></i> Congelar ahora
                    </button>
                </div>
            </div>
            <p class="small text-muted mb-0 mt-2">
                <em>Congelar ahora</em> guarda el % visible actual y deja de subir. <em>Seguir sumando</em> vuelve a real + extra.
            </p>
        </div>
    </div>`;
}

async function fetchBarraRealPorcentaje() {
    const idRaffle = parseInt(String(getSetting('web_id_raffle', '0')).trim(), 10);
    if (!Number.isFinite(idRaffle) || idRaffle <= 0) {
        return 0;
    }

    const fd = new FormData();
    fd.append('action', 'obtener_progreso');
    fd.append('id_raffle', String(idRaffle));
    settingsAppendCsrf(fd);

    try {
        const res = await fetch('/front/ajax/numeros.ajax.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (!data?.success) {
            return 0;
        }
        return Number(data.porcentaje) || 0;
    } catch (_) {
        return 0;
    }
}

function refreshBarraProgresoPanelPreview(realPct) {
    const realEl = document.getElementById('barraPanelReal');
    const visEl = document.getElementById('barraPanelVisible');
    const badge = document.getElementById('barraPanelModoBadge');
    const boostInput = document.getElementById('barraBoostInput');
    const fijaInput = document.getElementById('barraFijaInput');

    if (!realEl || !visEl) {
        return;
    }

    const real = Number.isFinite(realPct) ? realPct : 0;
    const prevBoost = getSetting('barra');
    const prevFija = getSetting('barra_fija');
    const prevModo = getSetting('barra_modo');

    if (boostInput) {
        SETTINGS.barra = boostInput.value.trim();
    }
    if (fijaInput) {
        SETTINGS.barra_fija = fijaInput.value.trim();
    }

    const visible = obtenerPorcentajeFinal(real);
    const clamped = Math.min(Math.max(visible, 0), 100);
    const visibleTxt = redondearPorcentajeUIPct(clamped, 2) + '%';

    realEl.textContent = redondearPorcentajeUIPct(real, 2) + '%';
    visEl.textContent = visibleTxt;

    SETTINGS.barra = prevBoost;
    SETTINGS.barra_fija = prevFija;
    SETTINGS.barra_modo = prevModo;

    if (badge) {
        const fija = isBarraModoFija();
        badge.textContent = fija ? 'Congelada' : 'Sumando';
        badge.className = 'badge ' + (fija ? 'bg-warning text-dark' : 'bg-success');
    }
}

async function guardarBarraProgresoSettings(payload, successLabel) {
    await actualizarSettings(payload);
    notifySettingSaved(successLabel);
    const panel = document.getElementById('barraProgresoPanel');
    if (panel) {
        panel.classList.add('settings-row--saved');
        window.setTimeout(() => panel.classList.remove('settings-row--saved'), 1800);
    }
    const real = await fetchBarraRealPorcentaje();
    refreshBarraProgresoPanelPreview(real);
}

function initBarraProgresoPanel() {
    const panel = document.getElementById('barraProgresoPanel');
    if (!panel || panel.dataset.bound === '1') {
        return;
    }
    panel.dataset.bound = '1';

    const btnSumar = document.getElementById('btnBarraSumar');
    const btnCongelar = document.getElementById('btnBarraCongelar');
    const boostInput = document.getElementById('barraBoostInput');
    const fijaInput = document.getElementById('barraFijaInput');

    let cachedReal = 0;

    const refresh = async () => {
        cachedReal = await fetchBarraRealPorcentaje();
        refreshBarraProgresoPanelPreview(cachedReal);
    };

    refresh();

    if (boostInput) {
        boostInput.addEventListener('input', () => refreshBarraProgresoPanelPreview(cachedReal));
    }
    if (fijaInput) {
        fijaInput.addEventListener('input', () => refreshBarraProgresoPanelPreview(cachedReal));
    }

    if (btnSumar) {
        btnSumar.addEventListener('click', async () => {
            btnSumar.disabled = true;
            try {
                const boost = boostInput ? boostInput.value.trim() : String(getSetting('barra', '0'));
                await guardarBarraProgresoSettings({
                    barra: boost,
                    barra_modo: 'sumar',
                }, 'Barra: modo sumar');
            } catch (e) {
                notifySettingError(e.message);
            } finally {
                btnSumar.disabled = false;
            }
        });
    }

    if (btnCongelar) {
        btnCongelar.addEventListener('click', async () => {
            btnCongelar.disabled = true;
            try {
                const real = await fetchBarraRealPorcentaje();
                const boost = boostInput ? boostInput.value.trim() : String(getSetting('barra', '0'));
                SETTINGS.barra = boost;
                SETTINGS.barra_modo = 'sumar';
                let visible = obtenerPorcentajeFinal(real);
                if (fijaInput && fijaInput.value.trim() !== '') {
                    const manual = parseFloat(fijaInput.value.trim().replace(',', '.'));
                    if (Number.isFinite(manual) && manual >= 0) {
                        visible = manual;
                    }
                }
                const valor = String(redondearPorcentajeUIPct(Math.min(Math.max(visible, 0), 100), 2));
                if (fijaInput) {
                    fijaInput.value = valor;
                }
                await guardarBarraProgresoSettings({
                    barra: boost,
                    barra_modo: 'fija',
                    barra_fija: valor,
                }, 'Barra congelada en ' + valor + '%');
            } catch (e) {
                notifySettingError(e.message);
            } finally {
                btnCongelar.disabled = false;
            }
        });
    }
}

function updateToggleLabel(key) {
    const el = document.querySelector(`.toggle-state-label[data-for-key="${key}"]`);
    const input = document.getElementById(`toggle-${key}`);
    if (!el || !input) return;
    const on = input.checked;
    if (key === 'maintenance_mode') {
        el.textContent = on ? 'Sitio cerrado al público' : 'Sitio abierto al público';
    } else if (key === 'web_compras_habilitadas') {
        el.textContent = on ? 'Compras activas' : 'Compras pausadas';
    } else {
        el.textContent = on ? 'Encendido' : 'Apagado';
    }
}

function setSaveButtonLoading(btn, loading) {
    if (!btn) return;
    if (loading) {
        btn.dataset.prevHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span> Guardando…';
    } else {
        btn.disabled = false;
        if (btn.dataset.prevHtml) {
            btn.innerHTML = btn.dataset.prevHtml;
            delete btn.dataset.prevHtml;
        }
    }
}

async function guardarToggleSetting(key, inputEl) {
    const v = inputEl.checked ? '1' : '0';
    const label = settingLabelForKey(key);
    updateToggleLabel(key);
    inputEl.disabled = true;
    try {
        await actualizarSettings({ [key]: v });
        notifySettingSaved(label);
        flashSettingRow(key);
    } catch (e) {
        notifySettingError(e.message);
        inputEl.checked = !inputEl.checked;
        updateToggleLabel(key);
    } finally {
        inputEl.disabled = false;
    }
}

async function actualizarSettings(payload) {

    await fetchSettings('actualizar', payload);
    await cargarSettingsGlobal(true);

    return true;
}

function syncSettingValueInDom(key, value) {
    const row = document.querySelector(`.settings-row[data-setting-key="${CSS.escape(String(key))}"]`);
    if (!row) return;
    const input = row.querySelector('[data-key]');
    if (!input) return;
    if (input.type === 'checkbox') {
        input.checked = isSettingTruthyValue(value);
        updateToggleLabel(key);
    } else if (input.tagName === 'TEXTAREA') {
        input.value = value ?? '';
    } else {
        input.value = value ?? '';
    }
}

async function guardarIndividualDesdeUI(key, btn) {

    const row = btn.closest('.settings-row');
    const input = row && row.querySelector('[data-key]');
    if (!input) return;

    const value = input.type === 'checkbox'
        ? (input.checked ? '1' : '0')
        : input.value.trim();

    setSaveButtonLoading(btn, true);

    try {

        await actualizarSettings({ [key]: value });

        syncSettingValueInDom(key, value);
        notifySettingSaved(settingLabelForKey(key));
        flashSettingRow(key);

    } catch (e) {
        notifySettingError(e.message || e);
    } finally {
        setSaveButtonLoading(btn, false);
    }
}

async function guardarSettings() {

    const btn = document.querySelector('button[onclick="guardarSettings()"]');
    const inputs = document.querySelectorAll('[data-key]');
    const payload = {};

    inputs.forEach(i => {
        if (i.type === 'checkbox') {
            payload[i.dataset.key] = i.checked ? '1' : '0';
        } else {
            payload[i.dataset.key] = i.value.trim();
        }
    });

    setSaveButtonLoading(btn, true);

    try {

        await actualizarSettings(payload);

        notifySettingSaved('Todos los ajustes');
        if (document.getElementById('settingsContainer')) {
            renderSettingsFromGlobal();
        }

    } catch (e) {
        notifySettingError(e.message);
    } finally {
        setSaveButtonLoading(btn, false);
    }
}

async function crearSetting() {

    let key = document.getElementById('newKey').value.trim();
    const value = document.getElementById('newValue').value.trim();

    if (!key) {
        notifySettingError('Escribe un nombre para la opción');
        return;
    }

    key = key.toLowerCase().replace(/\s+/g, '_');

    try {

        await fetchSettings('crear', {
            key_setting: key,
            value_setting: value
        });

        notifySettingSaved('Nueva opción');

        document.getElementById('newKey').value = '';
        document.getElementById('newValue').value = '';

        await cargarSettingsGlobal(true);
        renderSettingsFromGlobal();

    } catch (e) {
        notifySettingError(e.message);
    }
}

async function eliminarSetting(id) {

    if (!confirm('¿Eliminar esta opción?')) return;

    try {

        await fetchSettings('eliminar', { id_setting: id });

        notifySettingSaved('Opción eliminada');

        await cargarSettingsGlobal(true);
        renderSettingsFromGlobal();

    } catch (e) {
        notifySettingError(e.message || 'Error al eliminar');
    }
}

window.getSetting = getSetting;
window.onSettingsReady = onSettingsReady;
window.cargarSettingsGlobal = cargarSettingsGlobal;

function seedPublicSettings(map) {
    if (!map || typeof map !== 'object') {
        return;
    }
    Object.assign(SETTINGS, map);
    SETTINGS_LOADED = true;
    SETTINGS_PROMISE = Promise.resolve(SETTINGS);
}

window.seedPublicSettings = seedPublicSettings;
window.guardarToggleSetting = guardarToggleSetting;
window.guardarIndividualDesdeUI = guardarIndividualDesdeUI;
window.guardarSettings = guardarSettings;
window.crearSetting = crearSetting;
window.eliminarSetting = eliminarSetting;

function redondearPorcentajeUIPct(n, decimales = 2) {
    if (!Number.isFinite(n)) {
        return 0;
    }
    return parseFloat(n.toFixed(decimales));
}

function isBarraModoFija() {
    const modo = String(getSetting('barra_modo', 'sumar')).trim().toLowerCase();
    return ['fija', 'fijo', 'fixed', '1', 'true', 'on'].includes(modo);
}

function getBarraFijaValue() {
    const raw = String(getSetting('barra_fija', '')).trim().replace(',', '.');
    const n = parseFloat(raw);
    return Number.isFinite(n) && n >= 0 ? n : null;
}

function obtenerPorcentajeFinal(porcentajeBackend) {

    const real = Number(porcentajeBackend);
    const base = Number.isFinite(real) ? real : 0;

    if (isBarraModoFija()) {
        const fija = getBarraFijaValue();
        if (fija !== null) {
            return fija;
        }
    }

    const raw = getBarraBoostRaw();

    if (raw === null || raw === undefined) {
        return base;
    }

    const trimmed = String(raw).trim();
    if (trimmed === '') {
        return base;
    }

    const barraSetting = parseFloat(trimmed.replace(',', '.'));

    if (!Number.isFinite(barraSetting) || barraSetting <= 0) {
        return base;
    }

    return base + barraSetting;
}

function getBarraBoostRaw() {
    if (typeof getSetting !== 'function') {
        return null;
    }
    const direct = getSetting('barra');
    if (direct !== null && direct !== undefined && String(direct).trim() !== '') {
        return direct;
    }
    if (typeof SETTINGS === 'object' && SETTINGS !== null) {
        const keys = Object.keys(SETTINGS);
        const found = keys.find((k) => k.toLowerCase() === 'barra');
        if (found !== undefined && SETTINGS[found] !== undefined && SETTINGS[found] !== null) {
            return SETTINGS[found];
        }
    }
    return null;
}

function actualizarBarraProgreso(porcentajeBackend) {
    const porcentaje = obtenerPorcentajeFinal(porcentajeBackend);

    const clamped = Math.min(Math.max(porcentaje, 0), 100);
    const porcentajeFinal = redondearPorcentajeUIPct(clamped, 2);

    const texto = document.getElementById('porcentajeTexto');
    if (texto) {
        texto.innerText = porcentajeFinal + '%';
    }

    const barra = document.getElementById('barraProgreso');
    if (barra) {
        barra.style.width = porcentajeFinal + '%';
    }

    const barraCarrera = document.getElementById('barraProgresoCarrera');
    if (barraCarrera) {
        barraCarrera.style.width = porcentajeFinal + '%';
    }

    const caballo = document.getElementById('caballoProgreso');
    if (caballo) {
        caballo.style.left = porcentajeFinal + '%';
    }
}

let porcentajeBackendGlobal = 0;

function cargarDatosRifa(data) {
    porcentajeBackendGlobal = Number(data?.porcentaje) || 0;

    onSettingsReady(() => {
        actualizarBarraProgreso(porcentajeBackendGlobal);
    });
}

async function initBarraProgreso() {

    await cargarSettingsGlobal();

    const porcentajeBackend = 25;

    actualizarBarraProgreso(porcentajeBackend);
}
