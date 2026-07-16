const estado = {
    rifa: { id: null, precio: 0, type: 'automatic', minCantidad: 1 },
    rifasActivas: [],
    inventarioCompleto: [],
    statsInventario: null,
    disponiblesCount: 0,
    numerosSeleccionados: [],
    cantidadSeleccionada: 0,
    siteImages: {},
    contactConfig: {},
    pricing: null,
    splideInstances: {},
    clienteCompraBloqueada: false,
    rutas: {
        web: 'front/ajax/web.ajax.php',
    }
};

function publicCsrfToken() {
    if (window.PUBLIC_CSRF_TOKEN) {
        return window.PUBLIC_CSRF_TOKEN;
    }
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') || '' : '';
}

function appendPublicCsrf(payload) {
    if (payload instanceof URLSearchParams || payload instanceof FormData) {
        payload.append('csrf_token', publicCsrfToken());
    }
    return payload;
}

let ORIGEN = null;
$(document).ready(function () {
        
    ORIGEN = obtenerOrigenURL();

    inicializarSistema();

    $('#celularCliente').on('input paste', function () {

        let val = $(this).val().replace(/\D/g, '');

        if (val.startsWith('57') && val.length > 10)
            val = val.substring(2);

        $(this).val(val);

        if (val.length === 10)
            buscarClientePorCelular(val);
        else if (val.length < 10)
            aplicarBloqueoClienteInactivo(false);

    });

    if (typeof datosColombia !== 'undefined') {
        cargarDepartamentos();
        $('#departamento').on('change', function () {
            cargarCiudades(this.value);
        });
    }

});

function resolverIdRifaPublica() {
    const activas = estado.rifasActivas || [];
    const idCfg = parseInt(
        String(typeof getSetting === 'function' ? getSetting('web_id_raffle', '') : '').trim(),
        10
    );

    if (Number.isFinite(idCfg) && idCfg > 0) {
        const match = activas.find(r => parseInt(r.id_raffle, 10) === idCfg);
        if (match) {
            return idCfg;
        }
    }

    if (activas.length > 0) {
        return parseInt(activas[0].id_raffle, 10);
    }

    return null;
}

function aplicarRifaAlEstado(idRifa) {
    const rifa = estado.rifasActivas.find(r => parseInt(r.id_raffle, 10) === parseInt(idRifa, 10));
    estado.rifa.id = idRifa;
    if (rifa) {
        estado.rifa.type = rifa.type_raffle || 'automatic';
        estado.rifa.precio = typeof parsePrecioCOP === 'function'
            ? parsePrecioCOP(rifa.price_raffle)
            : (Number(rifa.price_raffle) || 0);
        estado.rifa.minCantidad = Math.max(1, parseInt(rifa.min_quantity_raffle, 10) || 1);
    }
}

function minCantidadCompra() {
    const pkg = packagePricingConfig();
    if (pkg.enabled) {
        return Math.max(pkg.minQuantity, Number(estado.rifa.minCantidad) || 1);
    }
    return Math.max(1, Number(estado.rifa.minCantidad) || 1);
}

function aplicarBannerSinRifaActiva() {
    const banner = document.getElementById('sinRifaActivaBanner');
    const sinRifa = !estado.rifa.id || estado.rifasActivas.length === 0;
    if (banner) {
        banner.classList.toggle('d-none', !sinRifa);
    }
    if (sinRifa) {
        $('#btnPagarDesktop, #btnPagarMobile').prop('disabled', true);
        const finalPay = document.getElementById('btnConfirmarTransferencia');
        if (finalPay) finalPay.disabled = true;
    }
}

async function inicializarSistema() {
    try {
        const boot = await cargarBootstrapLanding();
        if (boot) {
            await completarInicializacionLanding(boot);
            return;
        }
    } catch (e) {
        console.error('bootstrap_landing falló, usando carga clásica', e);
    }

    await inicializarSistemaLegacy();
}

async function completarInicializacionLanding(boot) {
    aplicarRedesDesdeSettings();

    const idRifa = boot.idRaffle || resolverIdRifaPublica();
    if (idRifa) {
        aplicarRifaAlEstado(idRifa);
    } else {
        estado.rifa.id = null;
        estado.rifa.precio = 0;
        estado.rifa.type = 'automatic';
    }

    aplicarBannerSinRifaActiva();
    initCheckoutUiListeners();
    initCheckoutModalUi();

    renderSelectorRifas();
    renderLandingCompleto();

    if (!estado.rifa.id) {
        hidePreloader();
        return;
    }

    await cargarInventario();

    actualizarPrecioVisual();

    const porcentajeBackend = boot.progreso?.porcentaje ?? await cargarPorcentajeBackend();

    aplicarBloqueoComprasWeb();
    actualizarBarraProgreso(porcentajeBackend);
    aplicarModoRifa();
    hidePreloader();
}

async function inicializarSistemaLegacy() {
    if (typeof cargarSettingsGlobal === 'function') {
        await cargarSettingsGlobal();
    }
    aplicarRedesDesdeSettings();
    await cargarConfigPublica();
    await cargarRifasActivas();

    const idRifa = resolverIdRifaPublica();
    if (idRifa) {
        aplicarRifaAlEstado(idRifa);
    } else {
        estado.rifa.id = null;
        estado.rifa.precio = 0;
        estado.rifa.type = 'automatic';
    }

    aplicarBannerSinRifaActiva();
    initCheckoutUiListeners();
    initCheckoutModalUi();

    renderSelectorRifas();
    renderLandingCompleto();

    if (!estado.rifa.id) {
        hidePreloader();
        return;
    }

    await cargarInventario();
    actualizarPrecioVisual();

    const porcentajeBackend = await cargarPorcentajeBackend();

    aplicarBloqueoComprasWeb();
    actualizarBarraProgreso(porcentajeBackend);
    aplicarModoRifa();
    hidePreloader();
}

async function cargarBootstrapLanding() {
    try {
        const fd = new FormData();
        fd.append('action', 'bootstrap_landing');
        const res = await fetch(estado.rutas.web, { method: 'POST', body: fd });
        const text = await res.text();
        let payload;
        try {
            payload = JSON.parse(text.trim());
        } catch (parseErr) {
            throw new Error('Respuesta inválida del servidor.');
        }

        if (!payload.success || !payload.data) {
            if (typeof toastError === 'function') {
                toastError(payload.message || 'No se pudo cargar la página.');
            }
            return null;
        }

        const { config, rifas, id_raffle_resolved, progreso } = payload.data;

        if (typeof seedPublicSettings === 'function' && config?.settings) {
            seedPublicSettings(config.settings);
        }

        if (config) {
            estado.siteImages = config.images || {};
            estado.contactConfig = config.contact || {};
            estado.pricing = config.pricing || null;
            if (config.maintenance_mode) {
                estado.rifasActivas = Array.isArray(rifas) ? rifas : [];
                return { idRaffle: null, progreso: null };
            }
            aplicarImagenesSitio();
        }

        estado.rifasActivas = Array.isArray(rifas) ? rifas : [];

        return {
            idRaffle: id_raffle_resolved ? parseInt(id_raffle_resolved, 10) : null,
            progreso: progreso || null,
        };
    } catch (e) {
        console.error('cargarBootstrapLanding', e);
        if (typeof toastError === 'function') {
            toastError('No se pudo cargar la página. Recarga e intenta de nuevo.');
        }
        return null;
    }
}

function aplicarInventarioResponse(json) {
    estado.inventarioCompleto = json.data || [];
    estado.statsInventario = json.stats || null;
    estado.disponiblesCount = json.stats?.disponibles
        ?? (typeof CrGrillaNumeros !== 'undefined'
            ? CrGrillaNumeros.contarDisponibles(estado.inventarioCompleto)
            : estado.inventarioCompleto.filter(t => Number(t.status_ticket) === 0).length);

    const p = typeof parsePrecioCOP === 'function'
        ? parsePrecioCOP(json.price_raffle)
        : Number(json.price_raffle);
    if (Number.isFinite(p) && p > 0) {
        estado.rifa.precio = p;
    }

    if (typeof CrGrillaNumeros !== 'undefined' && typeof LANDING_GRILLA !== 'undefined') {
        CrGrillaNumeros.resetUi(LANDING_GRILLA.gridId);
        const search = document.getElementById(LANDING_GRILLA.searchId);
        if (search) search.value = '';
    }
}

async function cargarConfigPublica() {
    try {
        const fd = new FormData();
        fd.append('action', 'config_publica');
        const res = await fetch(estado.rutas.web, { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success && data.data) {
            estado.siteImages = data.data.images || {};
            estado.contactConfig = data.data.contact || {};
            estado.pricing = data.data.pricing || null;
            if (data.data.maintenance_mode) return;
            aplicarImagenesSitio();
            aplicarRedesDesdeSettings();
        } else if (!data.success && typeof toastError === 'function') {
            toastError(data.message || 'No se pudo cargar la configuración del sitio.');
        }
    } catch (e) {
        if (typeof toastError === 'function') {
            toastError('No se pudo cargar la configuración del sitio. Recarga la página.');
        }
    }
}

function aplicarImagenesSitio() {
    const img = estado.siteImages;
    const resolveAssetUrl = (entry) => {
        const raw = entry?.url || entry?.fallback || '';
        if (!raw) return '';
        if (/^https?:\/\//i.test(raw)) return raw;
        if (raw.startsWith('/')) return window.location.origin + raw;
        return raw;
    };

    const logoUrl = resolveAssetUrl(img.logo) || (window.location.origin + '/assets/images/logo-transparent.png');
    document.querySelectorAll('[data-site-logo], [data-site-logo-footer], [data-site-logo-white], [data-site-logo-nav]').forEach((el) => {
        el.src = logoUrl;
    });
    const favUrl = resolveAssetUrl(img.favicon) || logoUrl;
    if (favUrl) {
        document.querySelectorAll('link[data-site-favicon]').forEach(link => { link.href = favUrl; });
    }

    const heroPlaceholder = window.location.origin + '/assets/images/hero-evento-principal.png';
    const isLogoAsset = (url) => /\/logo(?:[-_]?white|-transparent)?\.(?:png|jpe?g|webp|svg)(\?|$)/i.test(String(url || ''));
    const resolveHeroAssetUrl = (entry) => {
        const primary = resolveAssetUrl(entry);
        if (primary && !isLogoAsset(primary)) {
            return primary;
        }
        const fallback = entry?.fallback ? resolveAssetUrl({ url: entry.fallback, fallback: '' }) : '';
        if (fallback && !isLogoAsset(fallback)) {
            return fallback;
        }
        return '';
    };

    const isMobileHero = window.matchMedia('(max-width: 991.98px)').matches;
    const heroSource = isMobileHero && (img.hero_mobile?.url || img.hero_mobile?.fallback)
        ? img.hero_mobile
        : img.hero_banner;
    const heroUrl = resolveHeroAssetUrl(heroSource) || heroPlaceholder;
    document.querySelectorAll('[data-site-hero-banner]').forEach((el) => {
        el.src = heroUrl;
        el.dataset.fallbackSrc = heroPlaceholder;
        el.onerror = function onHeroImgError() {
            const fb = this.dataset.fallbackSrc;
            if (fb && this.src !== fb) {
                this.src = fb;
            }
        };
    });

    const gallery2Url = resolveHeroAssetUrl(img.hero_gallery_2)
        || resolveHeroAssetUrl(img.section_info_1)
        || (window.location.origin + '/assets/images/premio-nmax.jpeg');
    document.querySelectorAll('[data-site-hero-gallery-2]').forEach((el) => {
        el.src = gallery2Url;
        el.dataset.fallbackSrc = window.location.origin + '/assets/images/premio-nmax.jpeg';
        el.onerror = function onHeroGallery2Error() {
            const fb = this.dataset.fallbackSrc;
            if (fb && this.src !== fb) {
                this.src = fb;
            }
        };
    });
}

function rifaActiva() {
    return estado.rifasActivas.find(r => parseInt(r.id_raffle, 10) === parseInt(estado.rifa.id, 10)) || null;
}

function renderLandingCompleto() {
    aplicarImagenesSitio();
    aplicarTextosRifa();
    initLandingSplides();
}

function escapeHeroTitleText(value) {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function sanitizarTextoPublico(text) {
    return String(text || '')
        .replace(/\bloter[ií]as?\b/gi, 'Boyacá')
        .replace(/\bsorteos?\b/gi, 'evento')
        .replace(/\brifas?\b/gi, 'campaña')
        .replace(/\bpremios?\b/gi, 'bendecido')
        .replace(/\bganadores?\b/gi, 'entrega')
        .replace(/\bstickers?\b/gi, 'nro')
        .replace(/\bboletas?\b/gi, 'comprobante')
        .replace(/\bn[uú]meros?\b/gi, (m) => (m.toLowerCase().endsWith('s') ? 'nros' : 'nro'));
}

function formatHeroTitleHtml(rawTitle) {
    const raw = sanitizarTextoPublico(String(rawTitle || '').trim());
    if (!raw) return '';

    if (/<span[\s>]/i.test(raw) || /<br\s*\/?>/i.test(raw)) {
        const box = document.createElement('div');
        box.innerHTML = raw;
        box.querySelectorAll('*').forEach((node) => {
            const tag = node.tagName.toLowerCase();
            if (tag !== 'span' && tag !== 'br') {
                node.replaceWith(document.createTextNode(node.textContent || ''));
                return;
            }
            if (tag === 'span') {
                [...node.attributes].forEach((attr) => node.removeAttribute(attr.name));
                node.className = 'millonario';
            }
        });
        return box.innerHTML;
    }

    let title = escapeHeroTitleText(raw);
    title = title.replace(/\*\*([^*]+)\*\*/g, '<span class="millonario">$1</span>');
    title = title.replace(/\|([^|]+)\|/g, '<span class="millonario">$1</span>');

    if (!/<span/i.test(title)) {
        const unaMatch = raw.match(/^(.*?\buna\s+)(.+?)([!?.…].*)?$/iu);
        if (unaMatch && unaMatch[2]) {
            title = escapeHeroTitleText(unaMatch[1])
                + '<span class="millonario">' + escapeHeroTitleText(unaMatch[2].trim()) + '</span>'
                + escapeHeroTitleText(unaMatch[3] || '');
        } else {
            const lastWord = raw.match(/^(.+?\s)([^\s!?.…,]+)([!?.…,\s]*)$/u);
            if (lastWord) {
                title = escapeHeroTitleText(lastWord[1])
                    + '<span class="millonario">' + escapeHeroTitleText(lastWord[2]) + '</span>'
                    + escapeHeroTitleText(lastWord[3] || '');
            }
        }
    }

    return title.replace(/\n/g, '<br>');
}

function aplicarTextosRifa() {
    const heroTitle = document.getElementById('landingHeroTitle');
    const fromSetting = typeof getSetting === 'function'
        ? String(getSetting('landing_hero_title', '') || '').trim()
        : '';
    if (heroTitle && fromSetting) {
        heroTitle.innerHTML = formatHeroTitleHtml(fromSetting);
    } else if (heroTitle) {
        const rifa = rifaActiva();
        const titulo = rifa ? String(rifa.title_raffle || '').trim() : '';
        if (titulo) heroTitle.innerHTML = formatHeroTitleHtml(titulo);
    }

    const setText = (id, key) => {
        const el = document.getElementById(id);
        if (!el || typeof getSetting !== 'function') return;
        const val = String(getSetting(key, '') || '').trim();
        if (val !== '') el.textContent = val;
    };

    setText('landingPremioMayorTitulo', 'landing_premio_mayor_titulo');
    setText('landingPremioMayorDesc', 'landing_premio_mayor_desc');
    setText('landingPremioExtraTitulo', 'landing_premio_extra_titulo');
    setText('landingPremioExtraLabel', 'landing_premio_extra_label');
    setText('landingPremioExtraDesc', 'landing_premio_extra_desc');
    setText('landingFooterTagline', 'footer_tagline');
    setText('landingFooterPhones', 'footer_phones');
    setText('landingFooterLocation', 'footer_location');
    setText('landingFooterPayments', 'footer_payments');

    const brand = typeof getSetting === 'function'
        ? String(getSetting('nombre_rifa', '') || '').trim()
        : '';
    if (brand) {
        setText('landingFooterBrand', 'nombre_rifa');
        setText('landingFooterCopyBrand', 'nombre_rifa');
    }

    const email = typeof getSetting === 'function'
        ? String(getSetting('contact_email', '') || '').trim()
        : '';
    const emailEl = document.getElementById('landingFooterEmail');
    if (emailEl && email) {
        emailEl.textContent = email;
        emailEl.setAttribute('href', 'mailto:' + email);
    }

    const toggleWrap = (wrapId, value) => {
        const wrap = document.getElementById(wrapId);
        if (!wrap) return;
        wrap.classList.toggle('d-none', String(value || '').trim() === '');
    };
    if (typeof getSetting === 'function') {
        toggleWrap('landingFooterPhonesWrap', getSetting('footer_phones', ''));
        toggleWrap('landingFooterEmailWrap', getSetting('contact_email', ''));
        toggleWrap('landingFooterLocationWrap', getSetting('footer_location', ''));
        toggleWrap('landingFooterPaymentsWrap', getSetting('footer_payments', ''));
    }

    aplicarNavEvento();
}

function formatEventDateDayMonth(raw) {
    if (!raw) return '';
    const value = String(raw).trim();
    if (!value) return '';

    const d = new Date(value.replace(' ', 'T') + '-05:00');
    if (Number.isNaN(d.getTime())) return '';

    const day = d.toLocaleDateString('es-CO', {
        day: 'numeric',
        timeZone: 'America/Bogota',
    });
    const monthRaw = d.toLocaleDateString('es-CO', {
        month: 'long',
        timeZone: 'America/Bogota',
    });
    const month = monthRaw.charAt(0).toUpperCase() + monthRaw.slice(1);

    return `${day} de ${month}`.trim();
}

function aplicarNavEvento() {
    const badge = document.getElementById('landingNavEventBadge');
    const textEl = document.getElementById('landingNavEventText');
    if (!badge || !textEl) return;

    const rifa = rifaActiva();
    if (!rifa) {
        badge.classList.add('d-none');
        textEl.textContent = '';
        return;
    }

    const fecha = formatEventDateDayMonth(rifa.date_raffle);

    if (!fecha) {
        badge.classList.add('d-none');
        textEl.textContent = '';
        return;
    }

    textEl.textContent = fecha;
    badge.classList.remove('d-none');
}

function destroySplide(key) {
    const inst = estado.splideInstances[key];
    if (inst && typeof inst.destroy === 'function') {
        try { inst.destroy(true); } catch (e) {  }
    }
    delete estado.splideInstances[key];
}

function crearSlider(mainId, thumbsId, interval, keyPrefix) {
    const mainEl = document.querySelector(mainId);
    const thumbsEl = document.querySelector(thumbsId);
    if (!mainEl || !thumbsEl) return;
    if (!mainEl.querySelector('.splide__slide')) return;

    destroySplide(keyPrefix + '_main');
    destroySplide(keyPrefix + '_thumbs');

    const thumbs = new Splide(thumbsId, {
        fixedWidth: keyPrefix === 'hero' ? 90 : 100,
        fixedHeight: keyPrefix === 'hero' ? 60 : 60,
        gap: 10,
        rewind: true,
        pagination: false,
        isNavigation: true,
        arrows: keyPrefix !== 'hero',
        focus: 'center',
        cover: true,
        breakpoints: { 600: { fixedWidth: 60, fixedHeight: 44 } },
    });

    const main = new Splide(mainId, {
        type: keyPrefix === 'hero' ? 'fade' : 'slide',
        autoplay: true,
        interval: interval,
        rewind: true,
        pagination: false,
        arrows: keyPrefix === 'hero',
        speed: 800,
    });

    main.sync(thumbs);
    main.mount();
    thumbs.mount();
    estado.splideInstances[keyPrefix + '_main'] = main;
    estado.splideInstances[keyPrefix + '_thumbs'] = thumbs;
}

function initLandingSplides() {
    crearSlider('#main-carousel', '#thumbnail-carousel', 3000, 'hero');
    if (document.getElementById('premiosMainSlides')?.children.length) {
        crearSlider('#slider-premios', '#slider-thumbnails', 2500, 'premios');
    }
    if (document.getElementById('ganadoresMainSlides')?.children.length) {
        crearSlider('#ganadores-carousel', '#ganadores-thumbnails', 3500, 'ganadores');
    }
}

async function cargarRifasActivas() {
    try {
        const fd = new FormData();
        fd.append('action', 'rifas_activas');
        const res = await fetch(estado.rutas.web, { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) estado.rifasActivas = data.data || [];
        else if (typeof toastError === 'function') {
            toastError(data.message || 'No se pudieron cargar las campañas activas.');
        }
    } catch (e) {
        if (typeof toastError === 'function') {
            toastError('No se pudieron cargar las campañas. Recarga la página.');
        }
    }
}

function renderSelectorRifas() {
    const bar = document.getElementById('raffleSelectorBar');
    const container = document.getElementById('raffleSelectorButtons');
    if (!bar || !container) return;
    if (estado.rifasActivas.length <= 1) {
        bar.classList.add('d-none');
        return;
    }
    bar.classList.remove('d-none');
    container.innerHTML = estado.rifasActivas.map(r =>
        `<button type="button" class="btn btn-sm ${parseInt(estado.rifa.id) === parseInt(r.id_raffle) ? 'btn-warning' : 'btn-outline-light'}"
         onclick="seleccionarRifa(${r.id_raffle})">${sanitizarTextoPublico(r.title_raffle)}</button>`
    ).join('');
}

async function seleccionarRifa(id) {
    aplicarRifaAlEstado(id);
    estado.numerosSeleccionados = [];
    estado.cantidadSeleccionada = 0;
    renderSelectorRifas();
    renderLandingCompleto();
    await cargarInventario();
    actualizarPrecioVisual();
    const porcentajeBackend = await cargarPorcentajeBackend();
    actualizarBarraProgreso(porcentajeBackend);
    aplicarModoRifa();
}

function webComprasHabilitadas() {
    const raw =
        typeof getSetting === 'function'
            ? getSetting('web_compras_habilitadas', '1')
            : '1';
    const v = String(raw ?? '1').trim().toLowerCase();
    return !['0', 'false', 'no', 'off'].includes(v);
}

function mensajeClienteInactivo() {
    return 'Estamos presentando algunos problemas técnicos, inténtalo más tarde.';
}

function comprasPermitidas() {
    return webComprasHabilitadas() && !estado.clienteCompraBloqueada;
}

function avisarComprasNoPermitidas() {
    toastError(estado.clienteCompraBloqueada
        ? mensajeClienteInactivo()
        : 'Las compras en línea no están disponibles en este momento.');
}

function aplicarBloqueoClienteInactivo(bloqueado, mensaje) {
    estado.clienteCompraBloqueada = !!bloqueado;
    const banner = document.getElementById('clienteInactivoBanner');
    if (banner) {
        if (bloqueado) {
            banner.textContent = mensaje || mensajeClienteInactivo();
            banner.classList.remove('d-none');
        } else {
            banner.classList.add('d-none');
            banner.textContent = '';
        }
    }
    if (bloqueado) {
        toastError(mensaje || mensajeClienteInactivo());
    }
    aplicarEstadoBotonesCompra();
    actualizarUI();
}

function aplicarEstadoBotonesCompra() {
    const ok = comprasPermitidas();
    $('#btnPagarDesktop, #btnPagarMobile').prop('disabled', !ok);
    document.querySelectorAll('#comprobantePago').forEach((el) => {
        el.disabled = !ok;
    });
    const finalPay = document.getElementById('btnConfirmarTransferencia');
    if (finalPay) finalPay.disabled = !ok;
}

function aplicarBloqueoComprasWeb() {
    const ok = webComprasHabilitadas();
    const banner = document.getElementById('webComprasBloqueadasBanner');
    if (banner) {
        if (!ok) {
            let msg =
                'Las compras en línea están temporalmente deshabilitadas. Puedes seguir navegando y consultar tus nros.';
            if (typeof getSetting === 'function') {
                const custom = String(getSetting('web_mensaje_compras_bloqueadas', '') || '').trim();
                if (custom !== '') {
                    msg = custom;
                }
            }
            banner.textContent = msg;
            banner.classList.remove('d-none');
        } else {
            banner.classList.add('d-none');
            banner.textContent = '';
        }
    }

    aplicarEstadoBotonesCompra();
    if (ok) {
        actualizarUI();
    }
}

async function cargarInventario() {

    if (!estado.rifa.id) {
        hidePreloader();
        return;
    }

    showPreloader();

    try {
        const fd = new FormData();
        fd.append('action', 'inventario');
        fd.append('id_raffle', estado.rifa.id);

        const res = await fetch(estado.rutas.web, {
            method: 'POST',
            body: fd
        });

        const text = await res.text();
        let json;
        try {
            json = JSON.parse(text.trim());
        } catch (parseErr) {
            throw new Error('Respuesta inválida al cargar nros disponibles.');
        }

        if (json.success) {

            aplicarInventarioResponse(json);
        } else {
            toastError(json.message || 'No se pudo cargar el inventario de nros.');
        }
    } catch (e) {
        toastError(e instanceof Error ? e.message : 'Error al cargar nros disponibles.');
    }

    hidePreloader();

}

function esRifaManual() {
    const rifa = rifaActiva();
    const type = rifa ? (rifa.type_raffle || 'automatic') : (estado.rifa.type || 'automatic');
    return String(type) === 'manual';
}

async function refrescarRifaAntesPago() {
    await cargarRifasActivas();
    if (estado.rifa.id) {
        aplicarRifaAlEstado(estado.rifa.id);
        aplicarModoRifa();
    }
}

function sincronizarCantidadCheckout() {
    if (esRifaManual()) {
        estado.cantidadSeleccionada = estado.numerosSeleccionados.length;
        return;
    }
    estado.numerosSeleccionados = [];
    const checked = document.querySelector('.paquete-radio:checked');
    if (checked && checked.value !== 'custom') {
        estado.cantidadSeleccionada = parseInt(checked.value, 10) || estado.cantidadSeleccionada;
    } else {
        const custom = parseInt(document.getElementById('cantidadManual')?.value || '', 10);
        if (custom >= minCantidadCompra()) estado.cantidadSeleccionada = custom;
    }
}

function aplicarModoRifa() {
    const rifa = estado.rifasActivas.find(r => parseInt(r.id_raffle) === parseInt(estado.rifa.id));
    if (rifa) {
        estado.rifa.type = rifa.type_raffle || 'automatic';
    }

    const manual = esRifaManual();
    const manualBox = document.getElementById('selectorManualNumeros');
    const paquetesBox = document.getElementById('bloquePaquetesLanding');
    if (manualBox) manualBox.classList.toggle('d-none', !manual);
    if (paquetesBox) paquetesBox.classList.toggle('d-none', manual);

    if (manual) {
        estado.cantidadSeleccionada = estado.numerosSeleccionados.length;
        initGrillaLanding();
        renderSelectorManualNumeros();
    } else {
        estado.numerosSeleccionados = [];
        document.querySelectorAll('.paquete-radio').forEach(r => { r.checked = false; });
    }

    actualizarUI();
}

const LANDING_GRILLA = {
    gridId: 'gridNumerosManual',
    pagerId: 'gridNumerosManualPager',
    statsId: 'gridNumerosManualStats',
    countId: 'manualSeleccionCount',
    searchId: 'buscarNumeroManual',
};

let grillaLandingInicializada = false;

function initGrillaLanding() {
    if (grillaLandingInicializada || typeof CrGrillaNumeros === 'undefined') return;
    CrGrillaNumeros.bindSearch(LANDING_GRILLA.searchId, LANDING_GRILLA.gridId, renderSelectorManualNumeros);
    grillaLandingInicializada = true;
}

function renderSelectorManualNumeros() {
    if (typeof CrGrillaNumeros === 'undefined') return;
    CrGrillaNumeros.render({
        ...LANDING_GRILLA,
        items: estado.inventarioCompleto,
        selectedIds: estado.numerosSeleccionados,
        serverStats: estado.statsInventario,
        soloDisponibles: true,
        onToggleName: 'toggleNumeroManual',
        rerender: renderSelectorManualNumeros,
        emptyMessage: 'No hay nros disponibles en este momento.',
    });
}

function toggleNumeroManual(idTicket) {
    const id = parseInt(idTicket, 10);
    const ticket = estado.inventarioCompleto.find(t => parseInt(t.id_ticket, 10) === id);
    if (ticket && typeof CrGrillaNumeros !== 'undefined' && !CrGrillaNumeros.isDisponible(ticket)) {
        toastError('Este nro ya no está disponible.');
        return;
    }
    const idx = estado.numerosSeleccionados.indexOf(id);
    if (idx >= 0) {
        estado.numerosSeleccionados.splice(idx, 1);
    } else {
        estado.numerosSeleccionados.push(id);
    }
    estado.cantidadSeleccionada = estado.numerosSeleccionados.length;

    const isSelected = idx < 0;
    const patched = typeof CrGrillaNumeros !== 'undefined'
        && CrGrillaNumeros.patchSelection(LANDING_GRILLA.gridId, id, isSelected);

    if (patched) {
        const countEl = document.getElementById(LANDING_GRILLA.countId);
        if (countEl) {
            countEl.textContent = String(estado.cantidadSeleccionada);
        }
    } else {
        renderSelectorManualNumeros();
    }

    actualizarUI();
}

function filtrarNumerosManual() {
    if (typeof CrGrillaNumeros !== 'undefined') {
        CrGrillaNumeros.resetPage(LANDING_GRILLA.gridId);
    }
    renderSelectorManualNumeros();
}

window.toggleNumeroManual = toggleNumeroManual;

function validarSeleccionManualCheckout() {
    if (!esRifaManual()) {
        return true;
    }
    const min = minCantidadCompra();
    if (estado.numerosSeleccionados.length < min) {
        toastError(`Selecciona al menos ${min} nro${min > 1 ? 's' : ''} en la grilla`);
        return false;
    }
    estado.cantidadSeleccionada = estado.numerosSeleccionados.length;
    return true;
}

function aplicarNumerosNoDisponiblesCheckout(json) {
    if (typeof CrGrillaNumeros === 'undefined') {
        return false;
    }
    return CrGrillaNumeros.handleConflict(
        json,
        estado,
        () => {
            renderSelectorManualNumeros();
            actualizarUI();
        },
        cargarInventario
    );
}

function appendTicketIdsToPayload(payload) {
    if (!esRifaManual() || estado.numerosSeleccionados.length === 0) {
        return payload;
    }
    if (payload instanceof FormData) {
        estado.numerosSeleccionados.forEach(id => payload.append('ticket_ids[]', id));
        return payload;
    }
    if (payload instanceof URLSearchParams) {
        estado.numerosSeleccionados.forEach(id => payload.append('ticket_ids[]', id));
        return payload;
    }
    payload.ticket_ids = estado.numerosSeleccionados.slice();
    return payload;
}

$(document).on('change', '.paquete-radio', function () {

    if (this.value === 'custom') {
        $('#cantidadManual').show().focus();
        return;
    }

    $('#cantidadManual').hide();

    estado.cantidadSeleccionada = parseInt(this.value);

    actualizarUI();

});

$('#cantidadManual').on('blur', function () {

    let cant = parseInt(this.value);

    const min = minCantidadCompra();

    if (!cant) return;

    if (cant < min) {

        toastError(`Recuerda mínimo ${min} nro${min > 1 ? 's' : ''} para participar`);

        this.value = min;
        cant = min;
    }

    estado.cantidadSeleccionada = cant;

    actualizarUI();

});

function precioUnitarioBoleta() {
    return Number(estado.rifa.precio) || 0;
}

function isPricingTierEnabled(value) {
    if (value === true || value === 1) {
        return true;
    }
    if (value === false || value === 0 || value == null) {
        return false;
    }
    const v = String(value).trim().toLowerCase();
    return !['0', 'false', 'no', 'off', ''].includes(v);
}

function readPricingInt(value, fallback) {
    const n = parseInt(value, 10);
    return Number.isFinite(n) && n > 0 ? n : fallback;
}

function pricingTierConfig() {
    const p = estado.pricing;
    const enabled = p
        ? isPricingTierEnabled(p.enabled)
        : (typeof getSetting === 'function'
            && isPricingTierEnabled(getSetting('pricing_tiered_enabled', '0')));

    if (!enabled) {
        return { enabled: false, firstUnit: 0, secondUnit: 0, thirdPlusUnit: 0, bulkThreshold: 0 };
    }

    const setting = (key, fallback) => (
        typeof getSetting === 'function' ? getSetting(key, String(fallback)) : String(fallback)
    );

    const bulkThreshold = readPricingInt(
        p?.bulk_threshold ?? setting('pricing_bulk_threshold', 0),
        0
    );

    return {
        enabled: true,
        firstUnit: readPricingInt(p?.first_unit ?? setting('pricing_first_unit', 1500), 1500),
        secondUnit: readPricingInt(
            p?.second_unit ?? p?.tier1_unit ?? setting('pricing_tier1_unit', 1500),
            1500
        ),
        thirdPlusUnit: readPricingInt(
            p?.third_plus_unit ?? p?.tier2_unit ?? setting('pricing_tier2_unit', 1200),
            1200
        ),
        bulkThreshold,
    };
}

function defaultPackageGridItems() {
    return [
        { qty: 10, price: 15000, badge: null, badge_slug: null },
        { qty: 18, price: 27000, badge: null, badge_slug: null },
        { qty: 30, price: 45000, badge: 'Popular', badge_slug: 'popular' },
        { qty: 40, price: 48000, badge: 'Ahorro', badge_slug: 'ahorro' },
        { qty: 60, price: 72000, badge: null, badge_slug: null },
        { qty: 100, price: 120000, badge: 'Más vendido', badge_slug: 'mas-vendido' },
        { qty: 200, price: 240000, badge: null, badge_slug: null },
        { qty: 300, price: 360000, badge: 'Mega', badge_slug: 'mega' },
    ];
}

function packageGridItems() {
    const p = estado.pricing;
    const fromApi = Array.isArray(p?.grid_items) ? p.grid_items : [];
    if (fromApi.length) {
        return fromApi;
    }

    const pkg = packagePricingConfig();
    const items = defaultPackageGridItems().map((item) => {
        const price = pkg.packages[item.qty];
        return price > 0 ? { ...item, price } : item;
    });

    return items;
}

function packagePricingConfig() {
    const p = estado.pricing;
    const settingEnabled = typeof getSetting === 'function'
        ? isPricingTierEnabled(getSetting('pricing_packages_enabled', '0'))
        : true;
    const enabled = p?.mode === 'packages'
        ? isPricingTierEnabled(p.enabled ?? true)
        : (p?.packages ? true : settingEnabled);

    const packages = {};
    const list = Array.isArray(p?.packages) ? p.packages : [];
    list.forEach(item => {
        const qty = parseInt(item.qty, 10);
        const price = parseInt(item.price, 10);
        if (qty > 0 && price > 0) packages[qty] = price;
    });

    if (!Object.keys(packages).length) {
        [10, 18, 30, 40, 60, 100, 200, 300].forEach((qty, i) => {
            const prices = [15000, 27000, 45000, 48000, 72000, 120000, 240000, 360000];
            packages[qty] = prices[i];
        });
    }

    return {
        enabled,
        unitPrice: readPricingInt(p?.unit_price ?? 1500, 1500),
        bulkUnitPrice: 1200,
        bulkThreshold: 40,
        minQuantity: readPricingInt(p?.min_quantity ?? 10, 10),
        packages,
    };
}

function packagePriceForQuantity(qty) {
    const pkg = packagePricingConfig();
    if (!pkg.enabled) return null;
    const quantity = Math.max(0, parseInt(qty, 10) || 0);
    if (quantity <= 0) return null;

    const checked = document.querySelector('.paquete-radio:checked');
    if (checked && checked.value !== 'custom' && parseInt(checked.value, 10) === quantity) {
        const dataPrice = parseInt(checked.dataset.price || '', 10);
        if (dataPrice > 0) {
            return { total: dataPrice, isPackage: true };
        }
    }

    if (Object.prototype.hasOwnProperty.call(pkg.packages, quantity)) {
        return { total: pkg.packages[quantity], isPackage: true };
    }

    if (quantity >= pkg.minQuantity) {
        const unit = quantity >= pkg.bulkThreshold ? pkg.bulkUnitPrice : pkg.unitPrice;
        return { total: quantity * unit, isPackage: false };
    }

    return null;
}

function calcularDesglosePrecio(cantidad) {
    const qty = Math.max(0, parseInt(cantidad, 10) || 0);

    const packagePrice = packagePriceForQuantity(qty);
    if (packagePrice) {
        return {
            total: packagePrice.total,
            firstCount: qty,
            secondCount: 0,
            thirdPlusCount: 0,
            pairCount: 0,
            tier1Count: qty,
            tier2Count: 0,
            promoActive: packagePrice.isPackage,
            pairPromoActive: false,
            firstUnit: packagePricingConfig().unitPrice,
            secondUnit: packagePricingConfig().unitPrice,
            thirdPlusUnit: packagePricingConfig().unitPrice,
            tier1Unit: packagePricingConfig().unitPrice,
            tier2Unit: packagePricingConfig().unitPrice,
        };
    }

    const tier = pricingTierConfig();

    if (!tier.enabled || qty <= 0) {
        const unit = precioUnitarioBoleta();
        return {
            total: unit > 0 ? Math.round(qty * unit) : 0,
            firstCount: qty,
            secondCount: 0,
            thirdPlusCount: 0,
            pairCount: 0,
            tier1Count: qty,
            tier2Count: 0,
            promoActive: false,
            pairPromoActive: false,
            firstUnit: unit,
            secondUnit: unit,
            thirdPlusUnit: unit,
            tier1Unit: unit,
            tier2Unit: unit,
        };
    }

    if (tier.bulkThreshold > 0) {
        const unit = qty >= tier.bulkThreshold ? tier.thirdPlusUnit : tier.firstUnit;
        const promo = qty >= tier.bulkThreshold;
        return {
            total: Math.round(unit * qty),
            firstCount: promo ? 0 : qty,
            secondCount: 0,
            thirdPlusCount: promo ? qty : 0,
            pairCount: 0,
            tier1Count: promo ? 0 : qty,
            tier2Count: promo ? qty : 0,
            promoActive: promo,
            pairPromoActive: false,
            firstUnit: tier.firstUnit,
            secondUnit: tier.firstUnit,
            thirdPlusUnit: tier.thirdPlusUnit,
            tier1Unit: tier.firstUnit,
            tier2Unit: tier.thirdPlusUnit,
        };
    }

    if (qty === 1) {
        return {
            total: tier.firstUnit,
            firstCount: 1,
            secondCount: 0,
            thirdPlusCount: 0,
            pairCount: 0,
            tier1Count: 1,
            tier2Count: 0,
            promoActive: false,
            pairPromoActive: false,
            firstUnit: tier.firstUnit,
            secondUnit: tier.secondUnit,
            thirdPlusUnit: tier.thirdPlusUnit,
            tier1Unit: tier.secondUnit,
            tier2Unit: tier.thirdPlusUnit,
        };
    }

    if (qty === 2) {
        return {
            total: tier.secondUnit * 2,
            firstCount: 0,
            secondCount: 2,
            thirdPlusCount: 0,
            pairCount: 2,
            tier1Count: 2,
            tier2Count: 0,
            promoActive: true,
            pairPromoActive: true,
            firstUnit: tier.firstUnit,
            secondUnit: tier.secondUnit,
            thirdPlusUnit: tier.thirdPlusUnit,
            tier1Unit: tier.secondUnit,
            tier2Unit: tier.thirdPlusUnit,
        };
    }

    const total = tier.thirdPlusUnit * qty;

    return {
        total: Math.round(total),
        firstCount: 0,
        secondCount: 0,
        thirdPlusCount: qty,
        pairCount: 0,
        tier1Count: 0,
        tier2Count: qty,
        promoActive: true,
        pairPromoActive: false,
        firstUnit: tier.firstUnit,
        secondUnit: tier.secondUnit,
        thirdPlusUnit: tier.thirdPlusUnit,
        tier1Unit: tier.secondUnit,
        tier2Unit: tier.thirdPlusUnit,
    };
}

function totalAPagarCOP() {
    return calcularDesglosePrecio(estado.cantidadSeleccionada).total;
}

function fmtCOPShort(n) {
    return '$' + (Number(n) || 0).toLocaleString('es-CO');
}

function mensajePromoPrecio(cantidad, variant = 'full') {
    const qty = Math.max(0, parseInt(cantidad, 10) || 0);
    const tier = pricingTierConfig();
    if (!tier.enabled || qty <= 0) {
        return '';
    }

    if (tier.bulkThreshold > 0) {
        const unit = qty >= tier.bulkThreshold ? tier.thirdPlusUnit : tier.firstUnit;
        const total = unit * qty;
        if (variant === 'hero' || variant === 'compact') {
            if (qty >= tier.bulkThreshold) {
                return `Desde ${tier.bulkThreshold} · ${fmtCOPShort(tier.thirdPlusUnit)} c/u · ${fmtCOPShort(total)}`;
            }
            return '';
        }
        if (qty >= tier.bulkThreshold) {
            return `PROMO DESDE ${tier.bulkThreshold} — ${fmtCOPShort(tier.thirdPlusUnit)} c/u (${fmtCOPShort(total)} total)`;
        }
        return `Desde ${tier.bulkThreshold} nros: ${fmtCOPShort(tier.thirdPlusUnit)} c/u`;
    }

    const total2 = tier.secondUnit * 2;
    const total3Plus = tier.thirdPlusUnit * qty;

    if (variant === 'hero') {
        if (qty >= 3) {
            return `Promo 3+ · ${fmtCOPShort(tier.thirdPlusUnit)} c/u · ${fmtCOPShort(total3Plus)}`;
        }
        if (qty === 2) {
            return `Promo 2 nums · ${fmtCOPShort(tier.secondUnit)} c/u · ${fmtCOPShort(total2)}`;
        }
        if (qty === 1) {
            return `2 nros: ${fmtCOPShort(tier.secondUnit)} c/u · ${fmtCOPShort(total2)}`;
        }
        return '';
    }

    if (variant === 'compact') {
        if (qty >= 3) {
            return `${qty}×${fmtCOPShort(tier.thirdPlusUnit)} · ${fmtCOPShort(total3Plus)}`;
        }
        if (qty === 2) {
            return `2×${fmtCOPShort(tier.secondUnit)} · ${fmtCOPShort(total2)}`;
        }
        return '';
    }

    if (qty >= 3) {
        return `PROMO 3+ — ${fmtCOPShort(tier.thirdPlusUnit)} c/u (${fmtCOPShort(total3Plus)} total)`;
    }

    if (qty === 2) {
        return `PROMO 2 NROS — ${fmtCOPShort(tier.secondUnit)} c/u (${fmtCOPShort(total2)} total)`;
    }

    if (qty === 1) {
        return `2 nros: ${fmtCOPShort(tier.secondUnit)} c/u cada uno`;
    }

    return '';
}

function actualizarPromoEnElemento(el, cantidad, variant, isPromo) {
    if (!el) {
        return;
    }

    const msg = mensajePromoPrecio(cantidad, variant);

    if (!msg) {
        el.textContent = '';
        el.classList.add('d-none');
        el.classList.remove('cr-promo-badge--active');
        return;
    }

    el.textContent = msg;
    el.classList.remove('d-none');
    el.classList.toggle('cr-promo-badge--active', isPromo);
}

function actualizarPromoVisual(cantidad) {
    const tier = pricingTierConfig();
    const isPromo = tier.enabled
        && calcularDesglosePrecio(cantidad).promoActive;

    actualizarPromoEnElemento(document.getElementById('precioPromoNote'), cantidad, 'hero', isPromo);
    actualizarPromoEnElemento(document.getElementById('checkoutPromoNote'), cantidad, 'compact', isPromo);
    actualizarPromoEnElemento(document.getElementById('sidebarPromoNote'), cantidad, 'compact', isPromo);
    actualizarPromoEnElemento(document.getElementById('mobileCartPromo'), cantidad, 'compact', isPromo);
}

function renderHeroPackagesHtml() {
    const items = packageGridItems()
        .slice()
        .sort((a, b) => (a.qty || 0) - (b.qty || 0));

    return items.map((item) => {
        const slug = String(item.badge_slug || '').trim();
        const slugClass = slug ? ` cr-hero-precio-tier--${slug}` : '';
        const badgeHtml = item.badge
            ? `<span class="cr-hero-precio-tier__badge badge-paquete badge-paquete--${slug}">${String(item.badge)}</span>`
            : '';

        return `
        <div class="cr-hero-precio-tier${slugClass}">
            ${badgeHtml}
            <span class="cr-hero-precio-tier__label">${item.qty} nros</span>
            <span class="cr-hero-precio-tier__value">${fmtCOPShort(item.price)}</span>
        </div>`;
    }).join('');
}

function renderHeroPrecioTiersHtml(tier) {
    if (tier.bulkThreshold > 0) {
        return `
        <div class="cr-hero-precio-tier">
            <span class="cr-hero-precio-tier__label">Hasta ${tier.bulkThreshold - 1}</span>
            <span class="cr-hero-precio-tier__value">${fmtCOPShort(tier.firstUnit)}<span class="cr-hero-precio-tier__unit">c/u</span></span>
        </div>
        <div class="cr-hero-precio-tier cr-hero-precio-tier--promo">
            <span class="cr-hero-precio-tier__badge">Ahorro</span>
            <span class="cr-hero-precio-tier__label">Desde ${tier.bulkThreshold}</span>
            <span class="cr-hero-precio-tier__value">${fmtCOPShort(tier.thirdPlusUnit)}<span class="cr-hero-precio-tier__unit">c/u</span></span>
        </div>`;
    }

    return `
        <div class="cr-hero-precio-tier">
            <span class="cr-hero-precio-tier__label">1 num</span>
            <span class="cr-hero-precio-tier__value">${fmtCOPShort(tier.firstUnit)}<span class="cr-hero-precio-tier__unit">c/u</span></span>
        </div>
        <div class="cr-hero-precio-tier">
            <span class="cr-hero-precio-tier__label">2 nums</span>
            <span class="cr-hero-precio-tier__value">${fmtCOPShort(tier.secondUnit)}<span class="cr-hero-precio-tier__unit">c/u</span></span>
        </div>
        <div class="cr-hero-precio-tier cr-hero-precio-tier--promo">
            <span class="cr-hero-precio-tier__badge">Promo</span>
            <span class="cr-hero-precio-tier__label">3 o más</span>
            <span class="cr-hero-precio-tier__value">${fmtCOPShort(tier.thirdPlusUnit)}<span class="cr-hero-precio-tier__unit">c/u</span></span>
        </div>`;
}

function actualizarPrecioVisual() {
    const pkg = packagePricingConfig();
    const tier = pricingTierConfig();
    const display = document.getElementById('precioBoletaDisplay');

    if (pkg.enabled) {
        if (display) {
            display.innerHTML = renderHeroPackagesHtml();
            display.classList.add('cr-hero-precio-tiers');
        }
    } else if (tier.enabled) {
        if (display) {
            display.innerHTML = renderHeroPrecioTiersHtml(tier);
            display.classList.add('cr-hero-precio-tiers');
        }
    } else {
        const u = precioUnitarioBoleta();
        if (display) {
            display.classList.remove('cr-hero-precio-tiers');
            display.innerHTML = u > 0
                ? `<span class="cr-hero-precio-single">${fmtCOPShort(u)}<span class="cr-hero-precio-tier__unit">c/u</span></span>`
                : '—';
        }
    }

    actualizarPromoVisual(estado.cantidadSeleccionada || 0);
}

function crearChipSeleccionadoHtml(id, num) {
    return `<button type="button" class="cr-chip-seleccionado" data-ticket-id="${id}" onclick="toggleNumeroManual(${id})" title="Quitar nro ${num}">
        <span>${num}</span><i class="ti ti-x"></i>
    </button>`;
}

function renderSeleccionadosBarSmooth(container) {
    if (!container) return;

    const cant = estado.cantidadSeleccionada || 0;

    if (!cant) {
        container.classList.remove('is-visible');
        window.setTimeout(() => {
            if (!(estado.cantidadSeleccionada || 0)) {
                container.innerHTML = '';
            }
        }, 320);
        return;
    }

    if (!esRifaManual() || !estado.numerosSeleccionados.length) {
        container.innerHTML = `<div class="cr-seleccionados-pack">
            <span class="cr-seleccionados-pack__badge">${cant} nro${cant > 1 ? 's' : ''}</span>
            <span class="cr-seleccionados-pack__hint">Se asignan al confirmar el pago</span>
        </div>`;
        container.classList.add('is-visible');
        return;
    }

    let scroll = container.querySelector('.cr-seleccionados-scroll');
    if (!scroll) {
        container.innerHTML = '<div class="cr-seleccionados-scroll"></div>';
        scroll = container.querySelector('.cr-seleccionados-scroll');
    }

    const wantedIds = estado.numerosSeleccionados.map(String);
    const existing = new Map();

    scroll.querySelectorAll('[data-ticket-id]').forEach((btn) => {
        existing.set(btn.dataset.ticketId, btn);
    });

    existing.forEach((btn, id) => {
        if (wantedIds.includes(id)) return;
        btn.classList.add('cr-chip-seleccionado--out');
        window.setTimeout(() => btn.remove(), 220);
    });

    wantedIds.forEach((idStr) => {
        if (existing.has(idStr)) return;

        const id = parseInt(idStr, 10);
        const ticket = estado.inventarioCompleto.find(
            (t) => parseInt(t.id_ticket, 10) === id
        );
        if (!ticket) return;

        const wrap = document.createElement('div');
        wrap.innerHTML = crearChipSeleccionadoHtml(id, String(ticket.number_ticket));
        const chip = wrap.firstElementChild;
        if (!chip) return;

        chip.classList.add('cr-chip-seleccionado--enter');
        scroll.appendChild(chip);
        requestAnimationFrame(() => {
            chip.classList.remove('cr-chip-seleccionado--enter');
        });
    });

    container.classList.add('is-visible');
}

function renderBarraSeleccionados() {
    const barEl = document.getElementById('numerosSeleccionadosBar');
    const desktopEl = document.getElementById('listaTicketsDesktop');
    const cant = estado.cantidadSeleccionada || 0;
    const isDesktop = window.matchMedia('(min-width: 992px)').matches;

    if (barEl) {
        if (isDesktop) {
            barEl.classList.remove('is-visible');
            barEl.innerHTML = '';
        } else {
            renderSeleccionadosBarSmooth(barEl);
        }
    }

    if (!desktopEl) {
        renderCheckoutModalResumen();
        return;
    }

    if (!isDesktop) {
        desktopEl.innerHTML = '';
        renderCheckoutModalResumen();
        return;
    }

    if (!cant) {
        desktopEl.innerHTML = '';
        renderCheckoutModalResumen();
        return;
    }

    let html = '';

    if (esRifaManual() && estado.numerosSeleccionados.length) {
        const chips = estado.numerosSeleccionados
            .map((id) => {
                const ticket = estado.inventarioCompleto.find(
                    (t) => parseInt(t.id_ticket, 10) === parseInt(id, 10)
                );
                if (!ticket) return '';
                return crearChipSeleccionadoHtml(id, String(ticket.number_ticket));
            })
            .filter(Boolean)
            .join('');

        html = `<div class="cr-seleccionados-scroll">${chips}</div>`;
    } else {
        html = `<div class="cr-seleccionados-pack">
            <span class="cr-seleccionados-pack__badge">${cant} nro${cant > 1 ? 's' : ''}</span>
            <span class="cr-seleccionados-pack__hint">Se asignan al confirmar el pago</span>
        </div>`;
    }

    desktopEl.innerHTML = html;

    renderCheckoutModalResumen();
}

function renderCheckoutModalResumen() {
    const cant = estado.cantidadSeleccionada || 0;
    const cantEl = document.getElementById('modalCheckoutCantidad');
    const numsEl = document.getElementById('checkoutNumerosChips');
    const ticket = document.getElementById('modalCheckoutTicket');

    if (cantEl) {
        cantEl.textContent = cant
            ? `${cant} num${cant !== 1 ? 's' : ''}`
            : '0 nums';
    }

    if (ticket) {
        ticket.classList.toggle('cr-checkout-ticket--empty', !cant);
    }

    if (!numsEl) return;

    if (!cant) {
        numsEl.innerHTML = '';
        return;
    }

    const nums = obtenerNumerosCheckoutSeleccionados();

    if (esRifaManual() && nums.length) {
        numsEl.innerHTML = `<div class="cr-checkout-ticket__chips">${nums.map((n) => landingNumeroChipHtml(n)).join('')}</div>`;
        return;
    }

    numsEl.innerHTML = `<span class="cr-checkout-ticket__auto"><i class="ti ti-shuffle" aria-hidden="true"></i> Auto</span>`;
}

function landingNumeroChipHtml(num) {
    return `<span class="cr-numero-chip cr-numero-chip--selected">${String(num)}</span>`;
}

function obtenerNumerosCheckoutSeleccionados() {
    if (!esRifaManual() || !estado.numerosSeleccionados.length) {
        return [];
    }

    return estado.inventarioCompleto
        .filter((t) => estado.numerosSeleccionados.includes(parseInt(t.id_ticket, 10)))
        .map((t) => String(t.number_ticket));
}

function renderCheckoutSheetNumeros() {
    const el = document.getElementById('landingSheetNumeros');
    if (!el) return;

    const nums = obtenerNumerosCheckoutSeleccionados();

    if (esRifaManual() && nums.length) {
        el.innerHTML = `
            <span class="text-muted small d-block mb-2">Nros seleccionados</span>
            <div class="d-flex flex-wrap gap-1">${nums.map((n) => landingNumeroChipHtml(n)).join('')}</div>`;
        return;
    }

    el.innerHTML = `
        <span class="text-muted small d-block mb-2">Nros</span>
        <span class="vender-sheet__auto-badge">
            <i class="ti ti-shuffle"></i> Asignación automática al confirmar el pago
        </span>`;
}

function actualizarCheckoutSheet() {
    const cant = estado.cantidadSeleccionada || 0;
    const total = totalAPagarCOP();

    const fmt = (n) =>
        new Intl.NumberFormat('es-CO', {
            style: 'currency',
            currency: 'COP',
            maximumFractionDigits: 0,
        }).format(n);

    const sheetCant = document.getElementById('landingSheetCantidad');
    const sheetTotal = document.getElementById('landingSheetTotal');

    if (sheetCant) {
        sheetCant.textContent = `${cant} nro${cant !== 1 ? 's' : ''}`;
    }

    if (sheetTotal) {
        sheetTotal.textContent = fmt(total);
    }

    renderCheckoutSheetNumeros();

    const tier = pricingTierConfig();
    const isPromo = tier.enabled && calcularDesglosePrecio(cant).promoActive;
    actualizarPromoEnElemento(
        document.getElementById('landingSheetPromo'),
        cant,
        'compact',
        isPromo
    );
}

function isCheckoutSheetOpen() {
    const sheet = document.getElementById('landingCheckoutSheet');
    return !!(sheet && sheet.classList.contains('is-open'));
}

function isCheckoutModalOpen() {
    const modal = document.getElementById('modalCheckout');
    return !!(modal && modal.classList.contains('show'));
}

function isCheckoutUiOpen() {
    return isCheckoutSheetOpen() || isCheckoutModalOpen();
}

function syncMobileCartVisibility() {
    const bar = document.getElementById('mobileCart');
    if (!bar) return;

    const cant = estado.cantidadSeleccionada || 0;
    const show = cant > 0 && !isCheckoutUiOpen();

    bar.classList.toggle('is-visible', show);
    bar.setAttribute('aria-hidden', show ? 'false' : 'true');
    document.body.classList.toggle('has-mobile-cart', show);
    document.body.classList.toggle('landing-checkout-active', isCheckoutUiOpen());
}

function resetCheckoutModalUi() {
    const fileInput = document.getElementById('comprobantePago');
    if (fileInput) {
        fileInput.value = '';
    }
    setComprobanteUploadState(null);
    syncCheckoutTransferButtonLabel();
}

function truncateFileName(name, max = 26) {
    const text = String(name || '').trim();
    if (text.length <= max) return text;
    return `${text.slice(0, max - 1)}…`;
}

function setComprobanteUploadState(file) {
    const zone = document.getElementById('comprobantePagoZone');
    const icon = document.getElementById('comprobantePagoIcon');
    const label = document.getElementById('comprobantePagoLabel');
    const status = document.getElementById('comprobantePagoStatus');
    const hint = document.getElementById('comprobantePagoHint');

    if (!zone) return;

    if (file) {
        zone.classList.add('cr-upload-zone--ready');
        if (icon) icon.className = 'ti ti-circle-check-filled';
        if (label) label.textContent = truncateFileName(file.name);
        if (status) {
            status.textContent = 'Listo para enviar';
            status.classList.remove('d-none');
        }
        if (hint) hint.classList.add('d-none');
        return;
    }

    zone.classList.remove('cr-upload-zone--ready');
    if (icon) icon.className = 'ti ti-cloud-upload';
    if (label) label.textContent = 'Toca para subir tu comprobante';
    if (status) status.classList.add('d-none');
    if (hint) hint.classList.remove('d-none');
}

function initCheckoutUiListeners() {
    const modal = document.getElementById('modalCheckout');
    if (!modal || modal.dataset.crCheckoutBound) return;

    modal.dataset.crCheckoutBound = '1';
    modal.addEventListener('shown.bs.modal', syncMobileCartVisibility);
    modal.addEventListener('hidden.bs.modal', () => {
        resetCheckoutModalUi();
        syncMobileCartVisibility();
    });
}

function abrirCheckoutSheetMobile() {
    actualizarCheckoutSheet();

    const sheet = document.getElementById('landingCheckoutSheet');
    const backdrop = document.getElementById('landingSheetBackdrop');
    if (!sheet || !backdrop) {
        abrirModalCheckout();
        return;
    }

    backdrop.hidden = false;
    requestAnimationFrame(() => {
        backdrop.classList.add('is-open');
        sheet.classList.add('is-open');
        sheet.setAttribute('aria-hidden', 'false');
        document.body.classList.add('landing-checkout-sheet-open');
        syncMobileCartVisibility();
    });
}

function cerrarCheckoutSheetMobile() {
    const sheet = document.getElementById('landingCheckoutSheet');
    const backdrop = document.getElementById('landingSheetBackdrop');
    if (!sheet || !backdrop) return;

    sheet.classList.remove('is-open');
    backdrop.classList.remove('is-open');
    sheet.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('landing-checkout-sheet-open');

    setTimeout(() => {
        if (!sheet.classList.contains('is-open')) {
            backdrop.hidden = true;
        }
        syncMobileCartVisibility();
    }, 320);
}

function abrirModalCheckout() {
    const modalEl = document.getElementById('modalCheckout');
    if (!modalEl) return;

    renderBarraSeleccionados();
    syncCheckoutTransferButtonLabel();

    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();
    activarMetodoTransferencia();
}

function confirmarCheckoutDesdeSheet() {
    cerrarCheckoutSheetMobile();
    abrirModalCheckout();
}

window.cerrarCheckoutSheetMobile = cerrarCheckoutSheetMobile;
window.confirmarCheckoutDesdeSheet = confirmarCheckoutDesdeSheet;

function actualizarUI() {

    const cant = estado.cantidadSeleccionada;

    const total = totalAPagarCOP();

    actualizarPrecioVisual();

    const fmt = n =>
        new Intl.NumberFormat('es-CO', {
            style: 'currency',
            currency: 'COP',
            maximumFractionDigits: 0
        }).format(n);

    $('#cantTicketsDesktop').text(cant);

    const mobileQty = document.getElementById('lblCantidadMobileCart');
    if (mobileQty) {
        mobileQty.textContent = `${cant} num${cant !== 1 ? 's' : ''}`;
    }

    $('#totalDineroDesktop, #lblTotalMobile, #resumenTotal').text(fmt(total));

    syncCheckoutTransferButtonLabel();

    actualizarPromoVisual(cant);

    $('#resumenNumeros').html(
        cant
            ? `<span class="fw-bold">${cant} nro${cant > 1 ? 's' : ''}</span>`
            : '<span class="text-muted">Sin selección</span>'
    );

    renderBarraSeleccionados();

    actualizarCheckoutSheet();

    const comprasOk = comprasPermitidas();

    $('#btnPagarDesktop, #btnPagarMobile').prop('disabled', !comprasOk || !cant);

    syncMobileCartVisibility();

}

async function buscarClientePorCelular(tel) {

    const fd = new FormData();

    fd.append('action', 'buscar_cliente_checkout');
    fd.append('phone_customer', tel);

    const r = await fetch(estado.rutas.web, {
        method: 'POST',
        body: fd
    });

    const j = await r.json();

    if (!j.success) {
        if (j.blocked) {
            aplicarBloqueoClienteInactivo(true, j.message || mensajeClienteInactivo());
        } else if (j.message) {
            toastError(j.message);
        }
        return;
    }

    aplicarBloqueoClienteInactivo(false);

    if (j.data) {

        const c = j.data;

        $('#nombreCliente').val(c.name_customer);
        $('#apellidoCliente').val(c.lastname_customer);
        $('#emailCliente').val(c.email_customer);

        if (c.department_customer) {
            $('#departamento')
                .val(c.department_customer)
                .trigger('change');
            if (c.city_customer) {
                setTimeout(() => {
                    $('#ciudad').val(c.city_customer);
                }, 50);
            }
        }

    }

}

function cargarDepartamentos() {
    const $d = $('#departamento');
    $d.empty().append('<option value="">Seleccione...</option>');
    Object.keys(datosColombia)
        .sort()
        .forEach(d => $d.append(new Option(d, d)));
}

function cargarCiudades(dep) {
    const $c = $('#ciudad');
    $c.empty().append('<option value="">Seleccione...</option>');
    if (dep && datosColombia[dep]) {
        datosColombia[dep].forEach(c =>
            $c.append(new Option(c.display || c, c.value || c))
        );
    }
}

function showSiteToast(msg, type, hint) {
    const main = String(msg ?? '').trim();
    const extra = String(hint ?? '').trim();
    if (!main || typeof Toastify === 'undefined') return;

    const isError = type === 'error';
    const text = extra ? `${isError ? '✕' : '✓'} ${main}\n${extra}` : `${isError ? '✕' : '✓'} ${main}`;

    Toastify({
        text,
        duration: isError ? (extra ? 6500 : 4500) : 3500,
        gravity: 'top',
        position: 'center',
        stopOnFocus: true,
        style: {
            background: isError ? '#dc3545' : '#198754',
            color: '#fff',
            fontWeight: '600',
            fontSize: '15px',
            lineHeight: '1.45',
            borderRadius: '10px',
            boxShadow: '0 8px 24px rgba(15,23,42,0.2)',
            maxWidth: 'min(420px, 92vw)',
            whiteSpace: 'pre-line',
        },
    }).showToast();
}

function toastError(msg, hint) {
    showSiteToast(msg, 'error', hint);
}

function toastSuccess(msg) {
    showSiteToast(msg, 'success');
}

function esEmailValido(email) {

    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);

}

function setLoadingBtn(btnId, loading = true) {

    const btn = document.getElementById(btnId);

    if (!btn) return;

    btn.disabled = loading;

    btn.querySelector('.spinner-border')
        ?.classList.toggle('d-none', !loading);

}

async function abrirCheckout() {

    if (!comprasPermitidas()) {
        avisarComprasNoPermitidas();
        return;
    }

    await refrescarRifaAntesPago();
    sincronizarCantidadCheckout();

    const min = minCantidadCompra();

    if (esRifaManual()) {
        if (estado.numerosSeleccionados.length < min) {
            toastError(`Selecciona al menos ${min} nro${min > 1 ? 's' : ''}`);
            return;
        }
        estado.cantidadSeleccionada = estado.numerosSeleccionados.length;
    } else if (!estado.cantidadSeleccionada || estado.cantidadSeleccionada < min) {
        toastError(`La compra mínima es de ${min} nro${min > 1 ? 's' : ''}`);
        return;
    }

    if (!esRifaManual() && estado.cantidadSeleccionada > estado.disponiblesCount) {
        toastError('No hay suficientes nros disponibles');
        return;
    }

    const total = totalAPagarCOP();
    if (total <= 0) {
        toastError('No se pudo calcular el total. Recarga la página e intenta de nuevo.');
        return;
    }

    $('#totalPagarInput').val(total);

    const fmt = n =>
        new Intl.NumberFormat('es-CO', {
            style: 'currency',
            currency: 'COP',
            maximumFractionDigits: 0
        }).format(n);
    $('#resumenTotal').text(fmt(total));
    actualizarPromoVisual(estado.cantidadSeleccionada);
    actualizarCheckoutSheet();

    if (window.matchMedia('(max-width: 991.98px)').matches) {
        abrirCheckoutSheetMobile();
        return;
    }

    abrirModalCheckout();

}

function activarMetodoTransferencia() {
    const transferencia = document.getElementById('metodoTransferencia');
    const footer = document.getElementById('checkoutFooterActions');
    const footerTransfer = document.getElementById('footerMetodoTransferencia');

    if (transferencia) {
        transferencia.classList.remove('d-none');
        transferencia.classList.add('show');
    }
    if (footer) {
        footer.classList.remove('d-none');
    }
    if (footerTransfer) {
        footerTransfer.classList.remove('d-none');
    }
    syncCheckoutTransferButtonLabel();
}

function validarFormularioCheckout() {

const min = minCantidadCompra();

if (!estado.cantidadSeleccionada || estado.cantidadSeleccionada < min) {

    toastError(`La compra mínima es de ${min} nro${min > 1 ? 's' : ''}`);

    return false;

}

    const datos = {

        nombre: $('#nombreCliente').val().trim(),
        apellido: $('#apellidoCliente').val().trim(),
        celular: $('#celularCliente').val().replace(/\D/g, ''),
        email: $('#emailCliente').val().trim(),
        departamento: $('#departamento').val(),
        ciudad: $('#ciudad').val()

    };

    if (datos.celular.length !== 10)
        return toastError("Celular inválido"), false;

    if (!datos.nombre)
        return toastError("Ingresa tu nombre"), false;

    if (!datos.apellido)
        return toastError("Ingresa tu apellido"), false;

    if (!esEmailValido(datos.email))
        return toastError("Correo inválido"), false;

    if (!datos.departamento)
        return toastError("Selecciona departamento"), false;

    if (!datos.ciudad)
        return toastError("Selecciona ciudad"), false;

    return datos;

}

function syncCheckoutTransferButtonLabel() {
    const btn = document.getElementById('btnConfirmarTransferencia');
    if (!btn) return;

    const total = totalAPagarCOP();
    const fmt = (n) =>
        new Intl.NumberFormat('es-CO', {
            style: 'currency',
            currency: 'COP',
            maximumFractionDigits: 0,
        }).format(n);

    btn.textContent = total > 0
        ? `Confirmar pago · ${fmt(total)}`
        : 'Confirmar pago';
}

function initCheckoutModalUi() {
    const fileInput = document.getElementById('comprobantePago');
    if (!fileInput || fileInput.dataset.crBound) return;

    fileInput.dataset.crBound = '1';
    fileInput.addEventListener('change', () => {
        const file = fileInput.files?.[0] || null;
        if (!file) {
            setComprobanteUploadState(null);
            return;
        }

        setComprobanteUploadState(file);
        toastSuccess('Comprobante cargado correctamente');
    });
}

async function procesarTransferencia(e) {

    e.preventDefault();

    if (!comprasPermitidas()) {
        avisarComprasNoPermitidas();
        return;
    }

    const datos = validarFormularioCheckout();
    if (!datos) return;

    await refrescarRifaAntesPago();
    sincronizarCantidadCheckout();

    if (!validarSeleccionManualCheckout()) return;

    const file = document.getElementById('comprobantePago').files[0];

    if (!file) {
        toastError(
            'Sube la captura de tu transferencia Bre-B.',
            'Después de pagar, toma una foto o captura del comprobante y adjúntala aquí.'
        );
        return;
    }

    if (estado.cantidadSeleccionada < minCantidadCompra()) {
        toastError(`Mínimo ${minCantidadCompra()} nro${minCantidadCompra() > 1 ? 's' : ''}`);
        return;
    }

    const total = totalAPagarCOP();
    if (total <= 0) {
        toastError('No se pudo calcular el total. Recarga la página e intenta de nuevo.');
        return;
    }

    const formData = new FormData();

    formData.append('action', 'crear_transferencia_completa');

    formData.append('id_raffle', estado.rifa.id);
    formData.append('quantity', estado.cantidadSeleccionada);
    formData.append('amount', total);

    formData.append('name_customer', datos.nombre);
    formData.append('lastname_customer', datos.apellido);
    formData.append('phone_customer', datos.celular);
    formData.append('email_customer', datos.email);
    formData.append('department_customer', datos.departamento);
    formData.append('city_customer', datos.ciudad);

    formData.append('comprobante', file);

    formData.append('source_transfer', ORIGEN);
    formData.append('meta_fbp', typeof MetaEvents !== 'undefined' ? MetaEvents.getFbp() : '');
    formData.append('meta_fbc', typeof MetaEvents !== 'undefined' ? MetaEvents.getFbc() : '');

    appendTicketIdsToPayload(formData);
    appendPublicCsrf(formData);

    showPreloader();

    let redirecting = false;

    try {

        const res = await fetch('front/ajax/web.ajax.php', {
            method: 'POST',
            body: formData
        });

        const text = await res.text();
        let json;
        try {
            json = JSON.parse(text.trim());
        } catch (parseErr) {
            throw new Error('Respuesta inválida del servidor.');
        }

        if (!json.success) {
            aplicarNumerosNoDisponiblesCheckout(json);
            toastError(json.message || 'No pudimos confirmar tu pago.', json.hint || '');
            return;
        }

        redirecting = true;
        window.location.href = `transferencia.php?code=${encodeURIComponent(json.code_transfer)}&from_checkout=1`;

    } catch (e) {

        toastError(
            e instanceof Error ? e.message : 'No pudimos confirmar tu pago.',
            'Revisa tu conexión e intenta de nuevo.'
        );

    } finally {
        if (!redirecting) {
            hidePreloader();
        }
    }
}

function copiarTexto(id) {

    const el = document.getElementById(id);

    if (!el) {
        toastError('No se encontró el texto a copiar.');
        return;
    }

    const texto = el.innerText;

    if (navigator.clipboard && window.isSecureContext) {

        navigator.clipboard.writeText(texto)
            .then(() => mostrarToastCopiado(texto))
            .catch(() => copiarFallback(texto));

    } else {

        copiarFallback(texto);
    }
}

function copiarFallback(texto) {

    const textarea = document.createElement("textarea");
    textarea.value = texto;
    textarea.style.position = "fixed";
    textarea.style.opacity = "0";

    document.body.appendChild(textarea);
    textarea.focus();
    textarea.select();

    try {
        document.execCommand("copy");
        mostrarToastCopiado(texto);
    } catch (err) {
        alert("No se pudo copiar automáticamente 😢");
    }

    document.body.removeChild(textarea);
}

function mostrarToastCopiado(texto) {
    toastSuccess('Copiado: ' + texto);
}

function normalizeWhatsappUrl(chatUrl, phoneRaw) {
    const direct = String(chatUrl ?? '').trim();
    if (direct !== '') {
        return direct;
    }

    let digits = String(phoneRaw ?? '').replace(/\D/g, '');
    if (digits === '') {
        return null;
    }

    if (digits.length === 10) {
        digits = '57' + digits;
    }

    return `https://wa.me/${digits}?text=${encodeURIComponent('Hola, quiero información')}`;
}

function aplicarRedSocial(selector, url) {
    const clean = String(url ?? '').trim();

    document.querySelectorAll(selector).forEach(el => {
        const wrap = el.closest('[data-social-wrap]') || el.closest('.btn-social');

        if (clean === '') {
            el.removeAttribute('href');
            if (wrap) {
                wrap.classList.add('d-none');
            }
            return;
        }

        el.href = clean;
        el.classList.remove('d-none');
        if (wrap) {
            wrap.classList.remove('d-none');
        }
    });
}

function aplicarRedesDesdeSettings() {
    const contact = estado.contactConfig || {};
    const pick = (settingKey, contactKey) => {
        if (typeof getSetting === 'function') {
            const fromDb = getSetting(settingKey);
            if (String(fromDb ?? '').trim() !== '') {
                return fromDb;
            }
        }
        return contact[contactKey] ?? '';
    };

    aplicarRedSocial('.social-instagram', pick('social_instagram_url', 'social_instagram_url'));
    aplicarRedSocial('.social-facebook', pick('social_facebook_url', 'social_facebook_url'));
    aplicarRedSocial('.social-tiktok', pick('social_tiktok_url', 'social_tiktok_url'));
    aplicarRedSocial(
        '.social-whatsapp',
        normalizeWhatsappUrl(
            pick('whatsapp_chat_url', 'whatsapp_chat_url'),
            pick('whatsapp', 'whatsapp')
        )
    );

    const brebKey = String(pick('transfer_breb_key', 'transfer_breb_key') || '').trim();
    const brebName = String(pick('transfer_breb_name', 'transfer_breb_name') || '').trim();
    const brebEl = document.getElementById('breb');
    const brebNameEl = document.getElementById('brebName');
    if (brebEl && brebKey) {
        brebEl.textContent = brebKey;
    }
    if (brebNameEl && brebName) {
        brebNameEl.textContent = brebName;
    }

    const bancolombia = String(pick('transfer_bancolombia', 'transfer_bancolombia') || '').trim();
    const bancolombiaName = String(pick('transfer_bancolombia_name', 'transfer_bancolombia_name') || '').trim();
    const bancolombiaEl = document.getElementById('bancolombia');
    if (bancolombiaEl && bancolombia) {
        bancolombiaEl.textContent = bancolombia;
        const nameEl = bancolombiaEl.parentElement?.querySelector('.cr-cuenta-card__name');
        if (nameEl && bancolombiaName) nameEl.textContent = bancolombiaName;
    }

    const nequi = String(pick('transfer_nequi', 'transfer_nequi') || '').trim();
    const nequiName = String(pick('transfer_nequi_name', 'transfer_nequi_name') || '').trim();
    const nequiEl = document.getElementById('nequi');
    if (nequiEl && nequi) {
        nequiEl.textContent = nequi;
        const nameEl = nequiEl.parentElement?.querySelector('.cr-cuenta-card__name');
        if (nameEl && nequiName) nameEl.textContent = nequiName;
    }
}

async function cargarPorcentajeBackend() {
    if (!estado.rifa.id) {
        return 0;
    }

    try {
        const fd = new FormData();
        fd.append('action', 'progreso');
        fd.append('id_raffle', estado.rifa.id);

        const res = await fetch(estado.rutas.web, {
            method: 'POST',
            body: fd
        });

        const text = await res.text();
        let json;
        try {
            json = JSON.parse(text.trim());
        } catch (parseErr) {
            return 0;
        }

        if (!json.success) {
            return 0;
        }

        return Number(json.porcentaje) || 0;
    } catch (e) {
        return 0;
    }
}

function obtenerOrigenURL() {
    const params = new URLSearchParams(window.location.search);

    return params.get('cp') || null;
}

document.addEventListener('DOMContentLoaded', function () {

    let confetiInterval = null;
    let myConfetti      = null;

    const canvas  = document.getElementById('confetti-canvas');
    const seccion = document.querySelector('.container-ganadores');

    if (canvas && seccion) {

        myConfetti = confetti.create(canvas, {
            resize: true,
            useWorker: true
        });

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                entry.isIntersecting ? iniciarConfeti() : detenerConfeti();
            });
        }, { threshold: 0.3 });

        observer.observe(seccion);
    }

    function iniciarConfeti() {
        if (confetiInterval) return;

        confetiInterval = setInterval(() => {

            myConfetti({
                particleCount: 10,
                angle: 60,
                spread: 80,
                origin: { x: 0, y: 0 },
                colors: ['#f2b500', '#00C853', '#FFFFFF']
            });

            myConfetti({
                particleCount: 10,
                angle: 120,
                spread: 80,
                origin: { x: 1, y: 0 },
                colors: ['#f2b500', '#00C853', '#FFFFFF']
            });

        }, 180);
    }

    function detenerConfeti() {
        clearInterval(confetiInterval);
        confetiInterval = null;
    }

});
