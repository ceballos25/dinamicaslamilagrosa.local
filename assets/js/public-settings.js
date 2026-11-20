/* Settings mínimos para la landing pública (sin UI de admin). */
let SETTINGS = {};
let SETTINGS_LOADED = false;
let SETTINGS_PROMISE = null;
const SETTINGS_READY_QUEUE = [];

function getSetting(key, defaultValue = null) {
    return SETTINGS[key] ?? defaultValue;
}

function seedPublicSettings(map) {
    if (!map || typeof map !== 'object') {
        return;
    }
    Object.assign(SETTINGS, map);
    SETTINGS_LOADED = true;
    SETTINGS_PROMISE = Promise.resolve(SETTINGS);
    while (SETTINGS_READY_QUEUE.length) {
        const cb = SETTINGS_READY_QUEUE.shift();
        try { cb(SETTINGS); } catch (e) { /* ignore */ }
    }
}

function onSettingsReady(cb) {
    if (typeof cb !== 'function') return;
    if (SETTINGS_LOADED) {
        cb(SETTINGS);
        return;
    }
    SETTINGS_READY_QUEUE.push(cb);
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
            const fd = new FormData();
            fd.append('action', 'obtener');
            const res = await fetch('/front/ajax/settings.ajax.php', {
                method: 'POST',
                body: fd,
                credentials: 'same-origin',
            });
            const data = JSON.parse((await res.text()).trim());
            if (!data.success) return SETTINGS;
            SETTINGS = {};
            (Array.isArray(data.data) ? data.data : []).forEach((s) => {
                if (s && s.key_setting != null) {
                    SETTINGS[s.key_setting] = s.value_setting;
                }
            });
            SETTINGS_LOADED = true;
            while (SETTINGS_READY_QUEUE.length) {
                const cb = SETTINGS_READY_QUEUE.shift();
                try { cb(SETTINGS); } catch (e) { /* ignore */ }
            }
            return SETTINGS;
        } catch (e) {
            SETTINGS_PROMISE = null;
            return SETTINGS;
        }
    })();

    return SETTINGS_PROMISE;
}

function redondearPorcentajeUIPct(n, decimales = 2) {
    if (!Number.isFinite(n)) return 0;
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

function getBarraBoostRaw() {
    const direct = getSetting('barra');
    if (direct !== null && direct !== undefined && String(direct).trim() !== '') {
        return direct;
    }
    return null;
}

function obtenerPorcentajeFinal(porcentajeBackend) {
    const real = Number(porcentajeBackend);
    const base = Number.isFinite(real) ? real : 0;
    if (isBarraModoFija()) {
        const fija = getBarraFijaValue();
        if (fija !== null) return fija;
    }
    const raw = getBarraBoostRaw();
    if (raw === null || raw === undefined) return base;
    const trimmed = String(raw).trim();
    if (trimmed === '') return base;
    const barraSetting = parseFloat(trimmed.replace(',', '.'));
    if (!Number.isFinite(barraSetting) || barraSetting <= 0) return base;
    return base + barraSetting;
}

function actualizarBarraProgreso(porcentajeBackend) {
    const porcentaje = obtenerPorcentajeFinal(porcentajeBackend);
    const clamped = Math.min(Math.max(porcentaje, 0), 100);
    const porcentajeFinal = redondearPorcentajeUIPct(clamped, 2);
    const texto = document.getElementById('porcentajeTexto');
    if (texto) texto.innerText = porcentajeFinal + '%';
    const barra = document.getElementById('barraProgreso');
    if (barra) barra.style.width = porcentajeFinal + '%';
    const caballo = document.getElementById('caballoProgreso');
    if (caballo) caballo.style.left = porcentajeFinal + '%';
}

window.getSetting = getSetting;
window.seedPublicSettings = seedPublicSettings;
window.onSettingsReady = onSettingsReady;
window.cargarSettingsGlobal = cargarSettingsGlobal;
window.actualizarBarraProgreso = actualizarBarraProgreso;
window.obtenerPorcentajeFinal = obtenerPorcentajeFinal;
