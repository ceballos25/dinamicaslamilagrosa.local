const estado = {
    rifa: { id: null, precio: 0, type: 'automatic', minCantidad: 1 },
    rifasActivas: [],
    inventarioCompleto: [],
    statsInventario: null,
    disponiblesCount: 0,
    numerosSeleccionados: [],
    cantidadSeleccionada: 0,
    modoNumeros: 'automatic',
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
let transferenciaCheckoutEnCurso = false;
$(document).ready(function () {
        
    ORIGEN = obtenerOrigenURL();

    inicializarSistema();
    iniciarCuentaRegresivaEntrega();

    if (window.PhoneCo && typeof PhoneCo.bind === 'function') {
        PhoneCo.bind(document.getElementById('celularCliente'), {
            onChange: function (val) {
                if (val.length === 10) {
                    buscarClientePorCelular(val);
                } else if (val.length < 10) {
                    aplicarBloqueoClienteInactivo(false);
                }
            }
        });
    } else {
        const celEl = document.getElementById('celularCliente');
        if (celEl) {
            celEl.addEventListener('input', function () {
                const digits = String(celEl.value || '').replace(/\D/g, '').slice(0, 10);
                if (celEl.value !== digits) celEl.value = digits;
                if (digits.length === 10) {
                    buscarClientePorCelular(digits);
                } else if (digits.length < 10) {
                    aplicarBloqueoClienteInactivo(false);
                }
            });
        }
    }

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
        syncCheckoutConfirmButtonState();
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
    trackLandingViewContent();
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
    trackLandingViewContent();
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

    const heroPlaceholder = window.location.origin + '/assets/images/combo-pareja-frente.jpg';
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
        || (window.location.origin + '/assets/images/premio-nmax-mt15-2027.jpeg');
    document.querySelectorAll('[data-site-hero-gallery-2]').forEach((el) => {
        el.src = gallery2Url;
        el.dataset.fallbackSrc = window.location.origin + '/assets/images/premio-nmax-mt15-2027.jpeg';
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
        .replace(/\bstickers?\b/gi, (m) => (m.toLowerCase().endsWith('s') ? 'códigos' : 'código'))
        .replace(/\bboletas?\b/gi, 'comprobante')
        .replace(/\bn[uú]meros?\b/gi, (m) => (m.toLowerCase().endsWith('s') ? 'códigos' : 'código'))
        .replace(/\bnros\b/gi, 'códigos')
        .replace(/\bnro\b/gi, 'código');
}

function tituloHeroComboIlustrado(raw) {
    const text = String(raw || '').trim();
    const looksLikeCombo = /combo\s*pareja/i.test(text)
        || (/\bnmax\b/i.test(text) && /\bmt-?15\b/i.test(text));
    if (!looksLikeCombo) {
        return text;
    }
    if (/💑/.test(text) && /(?:🛵|🏍|🏍️)/.test(text)) {
        return text;
    }
    return '💑 ¡Combo Pareja!\n🛵 NMAX 2027 + 🏍️ MT-15';
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

    return raw.split(/\n/).map((line) => formatHeroTitleLine(line)).join('<br>');
}

function formatHeroTitleLine(line) {
    const text = String(line || '').trim();
    if (!text) return '';

    let title = escapeHeroTitleText(text);
    title = title.replace(/\*\*([^*]+)\*\*/g, '<span class="millonario">$1</span>');
    title = title.replace(/\|([^|]+)\|/g, '<span class="millonario">$1</span>');

    if (!/<span/i.test(title)) {
        title = title.replace(/Combo Pareja/gi, '<span class="millonario">Combo Pareja</span>');
        title = title.replace(/\$\s*4\s*Millones/gi, '<span class="millonario">$4 Millones</span>');
    }

    if (/<span/i.test(title)) {
        return title;
    }

    // "¡Gana el COMBO PAREJA!" / "¡Gana una Yamaha NMAX!"
    const articleMatch = text.match(/^(.*?\b(?:una|un|el|la|los|las)\s+)(.+?)([!?.…].*)?$/iu);
    if (articleMatch && articleMatch[2]) {
        return escapeHeroTitleText(articleMatch[1])
            + '<span class="millonario">' + escapeHeroTitleText(articleMatch[2].trim()) + '</span>'
            + escapeHeroTitleText(articleMatch[3] || '');
    }

    // Solo resaltar última palabra si la línea termina en exclamación/interrogación
    if (/[!?…]$/u.test(text)) {
        const lastWord = text.match(/^(.+?\s)([^\s!?.…,]+)([!?.…,\s]*)$/u);
        if (lastWord) {
            return escapeHeroTitleText(lastWord[1])
                + '<span class="millonario">' + escapeHeroTitleText(lastWord[2]) + '</span>'
                + escapeHeroTitleText(lastWord[3] || '');
        }
    }

    return title;
}

function premioMayorFactsPublicos() {
    const fromSetting = typeof getSetting === 'function'
        ? String(getSetting('landing_premio_mayor_desc', '') || '').trim()
        : '';
    const raw = fromSetting || 'Premio $20.000.000 el 19 de diciembre con la lotería de Boyacá\nSorteo a 4 cifras\nCada miércoles $500.000 con la lotería de Manizales';
    return raw.split(/\r?\n/).map((line) => line.trim()).filter(Boolean);
}

function formatPremioMayorFactsHtml() {
    return premioMayorFactsPublicos()
        .map((part) => '<li class="premio-mayor-facts__item">' + escapeHeroTitleText(part) + '</li>')
        .join('');
}

function ilustrarLineaPremioMayor(part) {
    let text = String(part || '');
    if (/final\s*probable|70\s*%\s*vendido/i.test(text)) {
        return '';
    }
    if (/boyac[aá]/i.test(text) && /juega\s+con|loter[ií]a|con la de boyac/i.test(text)) {
        return 'Por la de Boyacá para este 24 de Octubre';
    }
    if (/\bnmax\b/i.test(text) && !/🛵/.test(text)) {
        text = text.replace(/\bNMAX\b/gi, '🛵 NMAX');
    }
    if (/\bmt-?15\b/i.test(text) && !/(?:🏍|🏍️)/.test(text)) {
        text = text.replace(/\bMT-?15\b/gi, '🏍️ MT-15');
    }
    return text;
}

function aplicarTextosRifa() {
    const heroTitle = document.getElementById('landingHeroTitle');
    const fromSetting = typeof getSetting === 'function'
        ? String(getSetting('landing_hero_title', '') || '').trim()
        : '';
    if (heroTitle && fromSetting) {
        heroTitle.innerHTML = formatHeroTitleHtml(tituloHeroComboIlustrado(fromSetting));
    } else if (heroTitle) {
        const rifa = rifaActiva();
        const titulo = rifa ? String(rifa.title_raffle || '').trim() : '';
        if (titulo) heroTitle.innerHTML = formatHeroTitleHtml(tituloHeroComboIlustrado(titulo));
    }

    const setText = (id, key) => {
        const el = document.getElementById(id);
        if (!el || typeof getSetting !== 'function') return;
        const val = String(getSetting(key, '') || '').trim();
        if (val !== '') el.textContent = val;
        return val;
    };

    setText('landingPremioMayorTitulo', 'landing_premio_mayor_titulo');

    const premioDescEl = document.getElementById('landingPremioMayorDesc');
    if (premioDescEl) {
        premioDescEl.innerHTML = formatPremioMayorFactsHtml();
    }

    setText('landingPremioExtraTitulo', 'landing_premio_extra_titulo');
    setText('landingPremioExtraLabel', 'landing_premio_extra_label');
    const extraDesc = setText('landingPremioExtraDesc', 'landing_premio_extra_desc');
    const extraDescEl = document.getElementById('landingPremioExtraDesc');
    if (extraDescEl) {
        extraDescEl.classList.toggle('d-none', !extraDesc);
    }

    const taglineEl = document.getElementById('landingFooterTagline');
    if (taglineEl && typeof getSetting === 'function') {
        const tagline = String(getSetting('footer_tagline', '') || '').trim();
        const hideTagline = tagline === '' || /participa/i.test(tagline);
        taglineEl.textContent = hideTagline ? '' : tagline;
        taglineEl.classList.toggle('d-none', hideTagline);
    }
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

    const isMobileHero = keyPrefix === 'hero' && window.matchMedia('(max-width: 991.98px)').matches;
    const thumbs = new Splide(thumbsId, {
        fixedWidth: keyPrefix === 'hero' ? (isMobileHero ? 76 : 90) : 100,
        fixedHeight: keyPrefix === 'hero' ? (isMobileHero ? 68 : 60) : 60,
        gap: isMobileHero ? 8 : 10,
        rewind: true,
        pagination: false,
        isNavigation: true,
        arrows: keyPrefix !== 'hero',
        focus: 'center',
        cover: true,
        breakpoints: { 600: { fixedWidth: 64, fixedHeight: 56 } },
    });

    const mainOptions = {
        type: 'slide',
        autoplay: true,
        interval: keyPrefix === 'hero' ? 4500 : interval,
        rewind: true,
        pagination: isMobileHero,
        arrows: keyPrefix === 'hero',
        speed: 800,
        drag: true,
    };

    if (keyPrefix === 'hero') {
        Object.assign(mainOptions, {
            perPage: 1,
            gap: 0,
            padding: 0,
            trimSpace: true,
            focus: 'center',
        });
    }

    const main = new Splide(mainId, mainOptions);

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
    initHeroSlider();
    initResenasSlider();
    initVideosSlider();
}

function initHeroSlider() {
    const el = document.getElementById('crHeroSlider');
    if (!el || typeof Splide === 'undefined') return;
    if (!el.querySelector('.splide__slide')) return;

    destroySplide('heroCover');

    const slider = new Splide(el, {
        type: 'loop',
        perPage: 1,
        perMove: 1,
        arrows: true,
        pagination: true,
        drag: true,
        speed: 500,
        autoplay: true,
        interval: 4500,
        pauseOnHover: true,
        pauseOnFocus: true,
    });

    slider.mount();
    estado.splideInstances.heroCover = slider;
}

function initResenasSlider() {
    const el = document.getElementById('crResenasSlider');
    if (!el || typeof Splide === 'undefined') return;
    if (!el.querySelector('.splide__slide')) return;

    destroySplide('resenas');

    const slider = new Splide(el, {
        type: 'slide',
        rewind: true,
        perPage: 3,
        perMove: 1,
        gap: '0.85rem',
        arrows: true,
        pagination: false,
        drag: true,
        speed: 500,
        breakpoints: {
            991: { perPage: 2 },
            767: { perPage: 1, padding: 0 },
        },
    });

    slider.mount();
    estado.splideInstances.resenas = slider;
}

function initVideosSlider() {
    const el = document.getElementById('crVideosSlider');
    if (!el || typeof Splide === 'undefined') return;
    if (!el.querySelector('.splide__slide')) return;

    destroySplide('videos');

    const slider = new Splide(el, {
        type: 'slide',
        rewind: true,
        perPage: 1,
        perMove: 1,
        gap: '1rem',
        arrows: true,
        pagination: true,
        drag: true,
        speed: 450,
        autoplay: false,
        noDrag: 'video, button',
    });

    const pausar = () => {
        el.querySelectorAll('video').forEach((video) => {
            try { video.pause(); } catch (e) { /* el video puede no haber iniciado */ }
        });
    };

    slider.on('move', pausar);
    slider.mount();
    estado.splideInstances.videos = slider;
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
    syncCheckoutConfirmButtonState();
}

function aplicarBloqueoComprasWeb() {
    const ok = webComprasHabilitadas();
    const banner = document.getElementById('webComprasBloqueadasBanner');
    if (banner) {
        if (!ok) {
            let msg =
                'Las compras en línea están temporalmente deshabilitadas. Puedes seguir navegando y consultar tus códigos.';
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
            throw new Error('Respuesta inválida al cargar códigos disponibles.');
        }

        if (json.success) {

            aplicarInventarioResponse(json);
        } else {
            toastError(json.message || 'No se pudo cargar el inventario de códigos.');
        }
    } catch (e) {
        toastError(e instanceof Error ? e.message : 'Error al cargar códigos disponibles.');
    }

    hidePreloader();

}

function tipoRifaActiva() {
    const rifa = rifaActiva();
    const type = rifa ? (rifa.type_raffle || 'automatic') : (estado.rifa.type || 'automatic');
    return String(type);
}

function esRifaManual() {
    return tipoRifaActiva() === 'manual';
}

function esRifaAmbos() {
    return tipoRifaActiva() === 'both';
}

function esSeleccionManualActiva() {
    return esRifaManual() || (esRifaAmbos() && estado.modoNumeros === 'manual');
}

function hayPaqueteParaModo() {
    return (Number(estado.cantidadSeleccionada) || 0) >= minCantidadCompra();
}

function visibilidadModoAsignacion() {
    const modoBox = document.getElementById('modoAsignacionNumeros');
    if (!modoBox) return;
    modoBox.classList.toggle('d-none', !(esRifaAmbos() && hayPaqueteParaModo()));
}

function hintModoNumeros() {
    const hint = document.getElementById('modoNumerosHint');
    if (hint) {
        if (esSeleccionManualActiva()) {
            const qty = Number(estado.cantidadSeleccionada) || 0;
            hint.textContent = qty > 0
                ? `Elige ${qty} código${qty !== 1 ? 's' : ''}.`
                : 'Elige un paquete y luego tus códigos en la grilla.';
        } else {
            hint.textContent = 'Te asignamos códigos libres al confirmar el pago.';
        }
    }
    const countEl = document.getElementById(LANDING_GRILLA.countId);
    if (countEl) countEl.textContent = String(estado.numerosSeleccionados.length);
    const targetEl = document.getElementById(LANDING_GRILLA.countTargetId);
    if (targetEl) {
        targetEl.textContent = String(esRifaAmbos()
            ? (Number(estado.cantidadSeleccionada) || minCantidadCompra())
            : minCantidadCompra());
    }
}

async function aplicarModoNumerosLanding() {
    const checked = document.querySelector('input[name="modoNumeros"]:checked');
    estado.modoNumeros = checked?.value === 'manual' ? 'manual' : 'automatic';
    if (!esSeleccionManualActiva()) {
        estado.numerosSeleccionados = [];
    }
    if (esSeleccionManualActiva() && estado.inventarioCompleto.length === 0) {
        await cargarInventario();
    }
    aplicarModoRifa();
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
    if (!esSeleccionManualActiva()) {
        estado.numerosSeleccionados = [];
    }
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

    const exclusiveManual = esRifaManual();
    const pick = esSeleccionManualActiva();
    const manualBox = document.getElementById('selectorManualNumeros');
    const paquetesBox = document.getElementById('bloquePaquetesLanding');
    if (paquetesBox) paquetesBox.classList.toggle('d-none', exclusiveManual);
    visibilidadModoAsignacion();
    if (manualBox) {
        manualBox.classList.toggle('d-none', !(pick && (exclusiveManual || hayPaqueteParaModo())));
    }

    if (exclusiveManual) {
        estado.cantidadSeleccionada = estado.numerosSeleccionados.length;
        initGrillaLanding();
        renderSelectorManualNumeros();
    } else if (pick) {
        initGrillaLanding();
        renderSelectorManualNumeros();
    } else if (!esRifaAmbos()) {
        estado.numerosSeleccionados = [];
        document.querySelectorAll('.paquete-radio').forEach(r => { r.checked = false; });
    }

    hintModoNumeros();
    actualizarUI();
}

const LANDING_GRILLA = {
    gridId: 'gridNumerosManual',
    pagerId: 'gridNumerosManualPager',
    statsId: 'gridNumerosManualStats',
    countId: 'manualSeleccionCount',
    countTargetId: 'manualSeleccionCountTarget',
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
        emptyMessage: 'No hay códigos disponibles en este momento.',
    });
}

function toggleNumeroManual(idTicket) {
    const id = parseInt(idTicket, 10);
    const ticket = estado.inventarioCompleto.find(t => parseInt(t.id_ticket, 10) === id);
    if (ticket && typeof CrGrillaNumeros !== 'undefined' && !CrGrillaNumeros.isDisponible(ticket)) {
        toastError('Este código ya no está disponible.');
        return;
    }
    const idx = estado.numerosSeleccionados.indexOf(id);
    if (idx >= 0) {
        estado.numerosSeleccionados.splice(idx, 1);
    } else {
        const max = esRifaAmbos() ? (Number(estado.cantidadSeleccionada) || 0) : 0;
        if (esRifaAmbos() && max <= 0) {
            toastError('Elige un paquete primero');
            return;
        }
        if (esRifaAmbos() && estado.numerosSeleccionados.length >= max) {
            toastError(`Ya elegiste los ${max} códigos del paquete`);
            return;
        }
        estado.numerosSeleccionados.push(id);
    }
    if (esRifaManual()) {
        estado.cantidadSeleccionada = estado.numerosSeleccionados.length;
    }

    const isSelected = idx < 0;
    const patched = typeof CrGrillaNumeros !== 'undefined'
        && CrGrillaNumeros.patchSelection(LANDING_GRILLA.gridId, id, isSelected);

    if (!patched) {
        renderSelectorManualNumeros();
    }

    hintModoNumeros();
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
    if (!esSeleccionManualActiva()) {
        return true;
    }
    const min = minCantidadCompra();
    if (esRifaAmbos()) {
        const qty = Number(estado.cantidadSeleccionada) || 0;
        if (qty < min) {
            toastError('Elige un paquete y luego tus códigos en la grilla');
            return false;
        }
        if (estado.numerosSeleccionados.length !== qty) {
            toastError(`Elige ${qty} código${qty !== 1 ? 's' : ''} en la grilla`);
            return false;
        }
        return true;
    }
    if (estado.numerosSeleccionados.length < min) {
        toastError(`Selecciona al menos ${min} código${min > 1 ? 's' : ''} en la grilla`);
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
    if (!esSeleccionManualActiva() || estado.numerosSeleccionados.length === 0) {
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
        estado.cantidadSeleccionada = 0;
        estado.numerosSeleccionados = [];
        hintModoNumeros();
        aplicarModoRifa();
        return;
    }

    $('#cantidadManual').hide();

    estado.cantidadSeleccionada = parseInt(this.value, 10) || 0;
    if (esRifaAmbos() && estado.numerosSeleccionados.length > estado.cantidadSeleccionada) {
        estado.numerosSeleccionados = estado.numerosSeleccionados.slice(0, estado.cantidadSeleccionada);
    }
    hintModoNumeros();
    aplicarModoRifa();
    trackMetaAddToCart(estado.cantidadSeleccionada, totalAPagarCOP());

});

$(document).on('change', 'input[name="modoNumeros"]', function () {
    aplicarModoNumerosLanding();
});

$('#cantidadManual').on('blur', function () {

    let cant = parseInt(this.value);

    const min = minCantidadCompra();

    if (!cant) return;

    if (cant < min) {

        toastError(`Recuerda mínimo ${min} código${min > 1 ? 's' : ''} para participar`);

        this.value = min;
        cant = min;
    }

    estado.cantidadSeleccionada = cant;
    if (esRifaAmbos() && estado.numerosSeleccionados.length > cant) {
        estado.numerosSeleccionados = estado.numerosSeleccionados.slice(0, cant);
    }
    hintModoNumeros();
    aplicarModoRifa();
    trackMetaAddToCart(estado.cantidadSeleccionada, totalAPagarCOP());

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
    const unit = 8000;
    return [
        { qty: 3, price: 24000, badge: null, badge_slug: null },
        { qty: 5, price: 40000, badge: null, badge_slug: null },
        { qty: 8, price: 64000, badge: null, badge_slug: null },
        { qty: 10, price: 70000, badge: null, badge_slug: null },
        { qty: 15, price: 105000, badge: null, badge_slug: null },
        { qty: 20, price: 130000, badge: null, badge_slug: null },
    ].map((item) => ({ ...item, compare_at: item.qty * unit }));
}

function packageGridItems() {
    const p = estado.pricing;
    const fromApi = Array.isArray(p?.grid_items) ? p.grid_items : [];
    const unit = packagePricingConfig().unitPrice || 2000;
    if (fromApi.length) {
        return fromApi.map((item) => {
            const qty = Number(item.qty) || 0;
            const compareAt = Number(item.compare_at) > 0
                ? Number(item.compare_at)
                : qty * unit;
            return { ...item, compare_at: compareAt };
        });
    }

    const pkg = packagePricingConfig();
    const items = defaultPackageGridItems().map((item) => {
        const price = pkg.packages[item.qty];
        const compareAt = item.qty * (pkg.unitPrice || 2000);
        return price > 0 ? { ...item, price, compare_at: compareAt } : { ...item, compare_at: compareAt };
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
        [3, 5, 8, 10, 15, 20].forEach((qty, i) => {
            const prices = [24000, 40000, 64000, 70000, 105000, 130000];
            packages[qty] = prices[i];
        });
    }

    return {
        enabled,
        unitPrice: readPricingInt(p?.unit_price ?? 8000, 8000),
        bulkUnitPrice: readPricingInt(p?.bulk_unit_price ?? 7000, 7000),
        bulkThreshold: readPricingInt(p?.bulk_threshold ?? 10, 10),
        bulk2UnitPrice: readPricingInt(p?.bulk2_unit_price ?? 6500, 6500),
        bulk2Threshold: readPricingInt(p?.bulk2_threshold ?? 20, 20),
        minQuantity: readPricingInt(p?.min_quantity ?? 3, 3),
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
        const unit = quantity >= (pkg.bulk2Threshold || 20)
            ? (pkg.bulk2UnitPrice || 6500)
            : (quantity >= pkg.bulkThreshold ? pkg.bulkUnitPrice : pkg.unitPrice);
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
        return `Desde ${tier.bulkThreshold} códigos: ${fmtCOPShort(tier.thirdPlusUnit)} c/u`;
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
            return `2 códigos: ${fmtCOPShort(tier.secondUnit)} c/u · ${fmtCOPShort(total2)}`;
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
        return `2 códigos: ${fmtCOPShort(tier.secondUnit)} c/u cada uno`;
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

    const unit = packagePricingConfig().unitPrice || 2000;

    return items.map((item) => {
        const slug = String(item.badge_slug || '').trim();
        const slugClass = slug ? ` cr-hero-precio-tier--${slug}` : '';
        const badgeHtml = item.badge
            ? `<span class="cr-hero-precio-tier__badge badge-paquete badge-paquete--${slug}">${String(item.badge)}</span>`
            : '';
        const compareAt = Number(item.compare_at) > 0
            ? Number(item.compare_at)
            : (Number(item.qty) || 0) * unit;
        const hasSaving = compareAt > Number(item.price);
        const unitNow = item.qty > 0 ? Math.round(Number(item.price) / Number(item.qty)) : unit;
        const unitHtml = hasSaving
            ? `<span class="cr-hero-precio-tier__unit-price">${fmtCOPShort(unitNow)} c/u</span>`
            : '';
        const beforeHtml = hasSaving
            ? `<span class="cr-hero-precio-tier__antes"><em>Antes</em> <span class="cr-hero-precio-tier__antes-monto">${fmtCOPShort(compareAt)}</span></span>`
            : '';

        return `
        <div class="cr-hero-precio-tier${slugClass}${hasSaving ? ' cr-hero-precio-tier--con-ahorro' : ''}">
            ${badgeHtml}
            <span class="cr-hero-precio-tier__label">${item.qty} códigos</span>
            ${unitHtml}
            ${beforeHtml}
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
    return `<button type="button" class="cr-chip-seleccionado" data-ticket-id="${id}" onclick="toggleNumeroManual(${id})" title="Quitar código ${num}">
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
            <span class="cr-seleccionados-pack__badge">${cant} código${cant > 1 ? 's' : ''}</span>
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
            <span class="cr-seleccionados-pack__badge">${cant} código${cant > 1 ? 's' : ''}</span>
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
            ? `${cant} código${cant !== 1 ? 's' : ''}`
            : '0 códigos';
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
            <span class="text-muted small d-block mb-2">Códigos seleccionados</span>
            <div class="d-flex flex-wrap gap-1">${nums.map((n) => landingNumeroChipHtml(n)).join('')}</div>`;
        return;
    }

    el.innerHTML = `
        <span class="text-muted small d-block mb-2">Códigos</span>
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
        sheetCant.textContent = `${cant} código${cant !== 1 ? 's' : ''}`;
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

    if (!zone) {
        syncCheckoutConfirmButtonState();
        return;
    }

    if (file) {
        zone.classList.add('cr-upload-zone--ready');
        if (icon) icon.className = 'ti ti-circle-check-filled';
        if (label) label.textContent = truncateFileName(file.name);
        if (status) {
            status.textContent = 'Listo para enviar';
            status.classList.remove('d-none');
        }
        if (hint) hint.classList.add('d-none');
        syncCheckoutConfirmButtonState();
        return;
    }

    zone.classList.remove('cr-upload-zone--ready');
    if (icon) icon.className = 'ti ti-cloud-upload';
    if (label) label.textContent = 'Toca para subir tu comprobante';
    if (status) status.classList.add('d-none');
    if (hint) hint.classList.remove('d-none');
    syncCheckoutConfirmButtonState();
}

// ==========================================
// TRACKING DE META ADS (EMBUDO COMPLETO Y SEGURO)
// ==========================================
function checkoutMetaUserData() {
    const tel = (window.PhoneCo && typeof PhoneCo.normalize === 'function')
        ? PhoneCo.normalize($('#celularCliente').val() || '')
        : String($('#celularCliente').val() || '').replace(/\D/g, '').slice(0, 10);
    const nom = ($('#nombreCliente').val() || '').trim();
    const ape = ($('#apellidoCliente').val() || '').trim();
    const email = ($('#emailCliente').val() || '').trim();
    const dep = ($('#departamento').val() || '').trim();
    const ciu = ($('#ciudad').val() || '').trim();
    const data = {};

    if (tel.length === 10) {
        data.celular = tel;
    }
    if (nom.length >= 2) {
        data.nombre = nom;
    }
    if (ape.length >= 2) {
        data.apellido = ape;
    }
    if (email.includes('@')) {
        data.email = email;
    }
    if (dep) {
        data.departamento = dep;
    }
    if (ciu) {
        data.ciudad = ciu;
    }

    return Object.keys(data).length ? data : null;
}

function iniciarCuentaRegresivaEntrega() {
    const root = document.getElementById('heroCountdown');
    if (!root || root.dataset.bound === '1') {
        return;
    }

    const box = root.querySelector('.cr-countdown');
    const targetMs = Date.parse(root.getAttribute('data-target') || '');
    if (!box || !Number.isFinite(targetMs)) {
        return;
    }

    root.dataset.bound = '1';
    const daysEl = box.querySelector('[data-unit="days"]');
    const hoursEl = box.querySelector('[data-unit="hours"]');
    const minsEl = box.querySelector('[data-unit="mins"]');
    const secsEl = box.querySelector('[data-unit="secs"]');

    const pad = (n) => String(Math.max(0, n)).padStart(2, '0');

    const tick = () => {
        const diff = targetMs - Date.now();
        if (diff <= 0) {
            box.classList.add('is-done');
            if (daysEl) daysEl.textContent = '00';
            if (hoursEl) hoursEl.textContent = '00';
            if (minsEl) minsEl.textContent = '00';
            if (secsEl) secsEl.textContent = '00';
            return false;
        }

        const totalSecs = Math.floor(diff / 1000);
        const days = Math.floor(totalSecs / 86400);
        const hours = Math.floor((totalSecs % 86400) / 3600);
        const mins = Math.floor((totalSecs % 3600) / 60);
        const secs = totalSecs % 60;

        if (daysEl) daysEl.textContent = pad(days);
        if (hoursEl) hoursEl.textContent = pad(hours);
        if (minsEl) minsEl.textContent = pad(mins);
        if (secsEl) secsEl.textContent = pad(secs);
        return true;
    };

    if (tick()) {
        setInterval(tick, 1000);
    }
}

function trackLandingViewContent() {
    if (typeof MetaEvents === 'undefined') return;
    const basePrice = estado.pricing?.packages?.[0]?.price || (estado.rifa?.precio ? estado.rifa.precio * 10 : 20000);
    MetaEvents.trackOnce('landing_view_content', 'ViewContent', {
        currency: 'COP',
        value: basePrice,
        content_type: 'product'
    }, 'combo-pareja', checkoutMetaUserData());
}

function trackMetaAddToCart(cant, total) {
    if (typeof MetaEvents === 'undefined') return;
    const qty = parseInt(cant, 10) || 0;
    const val = Number(total) || 0;
    if (qty <= 0 || val <= 0) return;

    MetaEvents.track('AddToCart', {
        currency: 'COP',
        value: val,
        num_items: qty,
        content_type: 'product'
    }, 'pkg-' + qty, checkoutMetaUserData());
}

function trackMetaInitiateCheckout(cant, total) {
    if (typeof MetaEvents === 'undefined') return;
    const qty = parseInt(cant, 10) || 10;
    const val = Number(total) || 0;
    if (val <= 0) return;

    MetaEvents.trackOnce('init_checkout_' + qty + '_' + val, 'InitiateCheckout', {
        currency: 'COP',
        value: val,
        num_items: qty,
        content_type: 'product'
    }, 'init-' + qty, checkoutMetaUserData());
}

function trackMetaLead() {
    if (typeof MetaEvents === 'undefined') return;
    const userData = checkoutMetaUserData();
    const tel = userData && userData.celular ? userData.celular : '';
    const nom = userData && userData.nombre ? userData.nombre : '';

    if (tel.length === 10 && nom.length >= 2) {
        const total = totalAPagarCOP();
        MetaEvents.trackOnce('lead_' + tel, 'Lead', {
            currency: 'COP',
            value: total > 0 ? total : 20000
        }, 'lead-' + tel, userData);
    }
}

function trackMetaAddPaymentInfo() {
    if (typeof MetaEvents === 'undefined') return;
    const total = totalAPagarCOP();
    const cant = estado.cantidadSeleccionada || 10;
    if (total <= 0) return;

    MetaEvents.trackOnce('payment_info_' + total, 'AddPaymentInfo', {
        currency: 'COP',
        value: total,
        num_items: cant,
        content_type: 'product'
    }, 'payinfo-' + total, checkoutMetaUserData());
}

function logLandingEvent(event, detail) {
    const body = new URLSearchParams();
    body.append('action', 'log_landing');
    body.append('event', event);
    if (detail && detail.value != null) {
        body.append('value', String(detail.value));
    }
    if (detail && detail.num_items != null) {
        body.append('num_items', String(detail.num_items));
    }
    const ajaxUrl = (window.META_EVENTS_CONFIG && window.META_EVENTS_CONFIG.ajaxUrl)
        || '/front/ajax/meta.ajax.php';
    try {
        fetch(ajaxUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: body.toString(),
            credentials: 'same-origin',
            keepalive: true
        });
    } catch (e) {
    }
}

function trackWhatsAppPayPreference() {
    const cant = estado.cantidadSeleccionada || 10;
    const total = totalAPagarCOP();
    const value = total > 0 ? total : 20000;

    logLandingEvent('whatsapp_pay', { value, num_items: cant });

    if (typeof MetaEvents === 'undefined') {
        return;
    }

    MetaEvents.trackOnce('wa_pay_' + cant + '_' + value, 'Contact', {
        currency: 'COP',
        value,
        num_items: cant,
        content_type: 'product'
    }, 'wa-pay-' + cant, checkoutMetaUserData());
}

function abrirWhatsAppCheckout(e) {
    if (e && e.preventDefault) e.preventDefault();

    const cant = estado.cantidadSeleccionada || 10;
    const total = totalAPagarCOP();
    const fmt = n =>
        new Intl.NumberFormat('es-CO', {
            style: 'currency',
            currency: 'COP',
            maximumFractionDigits: 0
        }).format(n);
    const totalStr = fmt(total > 0 ? total : 20000);

    const nom = ($('#nombreCliente').val() || '').trim();
    const ape = ($('#apellidoCliente').val() || '').trim();
    const nombreCompleto = [nom, ape].filter(Boolean).join(' ');

    let waPhone = '';
    if (estado.contact && estado.contact.whatsapp) {
        waPhone = String(estado.contact.whatsapp).replace(/\D/g, '');
    }
    if (!waPhone && typeof getSetting === 'function') {
        waPhone = String(getSetting('whatsapp', '573133992903')).replace(/\D/g, '');
    }
    if (!waPhone) waPhone = '573133992903';
    if (waPhone.length === 10) waPhone = '57' + waPhone;

    let msg = '';
    if (nombreCompleto) {
        msg = `¡Hola! 👋 Soy ${nombreCompleto}. Quiero el paquete de ${cant} nums (${totalStr}) del COMBO PAREJA. ¿A dónde transfiero el dinero?`;
    } else {
        msg = `¡Hola! 👋 Quiero el paquete de ${cant} nums (${totalStr}) del COMBO PAREJA. ¿A dónde transfiero el dinero?`;
    }

    trackMetaLead();
    trackWhatsAppPayPreference();

    const url = 'https://api.whatsapp.com/send?phone=' + encodeURIComponent(waPhone) + '&text=' + encodeURIComponent(msg);
    window.open(url, '_blank', 'noopener,noreferrer');
}
window.abrirWhatsAppCheckout = abrirWhatsAppCheckout;

function initCheckoutUiListeners() {
    const modal = document.getElementById('modalCheckout');
    if (!modal || modal.dataset.crCheckoutBound) return;

    modal.dataset.crCheckoutBound = '1';
    modal.addEventListener('shown.bs.modal', () => {
        syncMobileCartVisibility();
        trackMetaAddPaymentInfo();
    });
    modal.addEventListener('hidden.bs.modal', () => {
        resetCheckoutModalUi();
        syncMobileCartVisibility();
    });

    const leadInputs = ['celularCliente', 'nombreCliente', 'apellidoCliente', 'emailCliente', 'ciudad'];
    leadInputs.forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener('blur', trackMetaLead);
            el.addEventListener('change', trackMetaLead);
        }
    });

    const fileEl = document.getElementById('comprobantePago');
    if (fileEl) {
        fileEl.addEventListener('change', () => {
            if (fileEl.files && fileEl.files.length > 0) {
                trackMetaAddPaymentInfo();
            }
        });
    }
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
    trackMetaInitiateCheckout(estado.cantidadSeleccionada, totalAPagarCOP());
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
        mobileQty.textContent = `${cant} código${cant !== 1 ? 's' : ''}`;
    }

    $('#totalDineroDesktop, #lblTotalMobile, #resumenTotal').text(fmt(total));

    syncCheckoutTransferButtonLabel();

    actualizarPromoVisual(cant);

    $('#resumenNumeros').html(
        cant
            ? `<span class="fw-bold">${cant} código${cant > 1 ? 's' : ''}</span>`
            : '<span class="text-muted">Sin selección</span>'
    );

    renderBarraSeleccionados();

    actualizarCheckoutSheet();

    const comprasOk = comprasPermitidas();

    $('#btnPagarDesktop, #btnPagarMobile').prop('disabled', !comprasOk || !cant);

    syncMobileCartVisibility();

}

async function buscarClientePorCelular(tel) {
    const phone = (window.PhoneCo && typeof PhoneCo.normalize === 'function')
        ? PhoneCo.normalize(tel)
        : String(tel || '').replace(/\D/g, '').slice(0, 10);

    if (!/^\d{10}$/.test(phone)) {
        return;
    }

    try {
        const fd = new FormData();
        fd.append('action', 'buscar_cliente_checkout');
        fd.append('phone_customer', phone);

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
    } catch (err) {
        // No bloquear el formulario si la búsqueda falla (red/JSON).
        console.warn('buscarClientePorCelular', err);
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
            toastError(`Selecciona al menos ${min} código${min > 1 ? 's' : ''}`);
            return;
        }
        estado.cantidadSeleccionada = estado.numerosSeleccionados.length;
    } else if (!estado.cantidadSeleccionada || estado.cantidadSeleccionada < min) {
        toastError(`La compra mínima es de ${min} código${min > 1 ? 's' : ''}`);
        return;
    }

    if (!esRifaManual() && estado.cantidadSeleccionada > estado.disponiblesCount) {
        toastError('No hay suficientes códigos disponibles');
        return;
    }

    const total = totalAPagarCOP();
    if (total <= 0) {
        toastError('No se pudo calcular el total. Recarga la página e intenta de nuevo.');
        return;
    }

    $('#totalPagarInput').val(total);

    trackMetaInitiateCheckout(estado.cantidadSeleccionada, total);

    const fmt = n =>
        new Intl.NumberFormat('es-CO', {
            style: 'currency',
            currency: 'COP',
            maximumFractionDigits: 0
        }).format(n);
    $('#resumenTotal').text(fmt(total));
    actualizarPromoVisual(estado.cantidadSeleccionada);
    actualizarCheckoutSheet();

    // Abrir directamente el checkout modal/drawer (diseñado tanto para mobile bottom-sheet como desktop)
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
    trackMetaAddPaymentInfo();
}

function validarFormularioCheckout() {

const min = minCantidadCompra();

if (!estado.cantidadSeleccionada || estado.cantidadSeleccionada < min) {

    toastError(`La compra mínima es de ${min} código${min > 1 ? 's' : ''}`);

    return false;

}

    const datos = {

        nombre: $('#nombreCliente').val().trim(),
        apellido: $('#apellidoCliente').val().trim(),
        celular: (window.PhoneCo && typeof PhoneCo.normalize === 'function')
            ? PhoneCo.normalize($('#celularCliente').val())
            : String($('#celularCliente').val() || '').replace(/\D/g, '').slice(0, 10),
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

    trackMetaLead();

    return datos;

}

function hayComprobanteCheckout() {
    const input = document.getElementById('comprobantePago');
    return !!(input && input.files && input.files.length > 0);
}

function destacarBotonConfirmarPago() {
    const btn = document.getElementById('btnConfirmarTransferencia');
    if (!btn || btn.disabled) return;
    if (btn.classList.contains('cr-cta-pago--shine')) return;

    try {
        const limpiar = () => {
            btn.classList.remove('cr-cta-pago--shine');
            btn.removeEventListener('animationend', onEnd);
            if (btn._crShineTimer) {
                clearTimeout(btn._crShineTimer);
                btn._crShineTimer = null;
            }
        };
        const onEnd = (e) => {
            if (e.target !== btn) return;
            limpiar();
        };

        btn.addEventListener('animationend', onEnd);
        btn.classList.add('cr-cta-pago--shine');
        btn._crShineTimer = setTimeout(limpiar, 1100);
    } catch (e) {
    }
}

function syncCheckoutConfirmButtonState() {
    const btn = document.getElementById('btnConfirmarTransferencia');
    if (!btn) return;

    try {
        const sinRifa = !estado.rifa.id || !estado.rifasActivas.length;
        const listo = comprasPermitidas()
            && !sinRifa
            && hayComprobanteCheckout()
            && !transferenciaCheckoutEnCurso;
        const estabaApagado = btn.disabled;

        btn.disabled = !listo;
        btn.setAttribute('aria-disabled', listo ? 'false' : 'true');
        btn.title = listo ? 'Confirma tu pago' : 'Sube el comprobante para confirmar';

        if (listo && estabaApagado) {
            destacarBotonConfirmarPago();
        }
        if (!listo) {
            btn.classList.remove('cr-cta-pago--shine');
        }
    } catch (e) {
    }
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

    syncCheckoutConfirmButtonState();
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

    if (transferenciaCheckoutEnCurso) {
        return;
    }

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
            'Adjunta el comprobante de tu transferencia.',
            'Después de transferir por Nequi, Bancolombia o Bre-B, toma una captura o foto y adjúntala aquí para verificar tu compra.'
        );
        return;
    }

    if (estado.cantidadSeleccionada < minCantidadCompra()) {
        toastError(`Mínimo ${minCantidadCompra()} código${minCantidadCompra() > 1 ? 's' : ''}`);
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

    transferenciaCheckoutEnCurso = true;
    const btnPago = document.getElementById('btnConfirmarTransferencia');
    if (btnPago) {
        btnPago.disabled = true;
    }

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

        const code = String(json.code_transfer || '').trim();
        if (!code) {
            toastError(
                'Tu pago quedó registrado, pero no pudimos abrir el seguimiento.',
                'Escríbenos por WhatsApp con tu celular y te ayudamos a ubicar la transferencia.'
            );
            return;
        }

        redirecting = true;
        window.location.href = `transferencia.php?code=${encodeURIComponent(code)}&from_checkout=1`;

    } catch (e) {

        toastError(
            e instanceof Error ? e.message : 'No pudimos confirmar tu pago.',
            'Revisa tu conexión e intenta de nuevo.'
        );

    } finally {
        if (!redirecting) {
            transferenciaCheckoutEnCurso = false;
            syncCheckoutConfirmButtonState();
            hidePreloader();
        }
    }
}

function copiarTexto(id, btnEl) {

    trackMetaAddPaymentInfo();

    const el = document.getElementById(id);

    if (!el) {
        toastError('No se encontró el texto a copiar.');
        return;
    }

    const texto = (el.innerText || el.textContent || '').trim();

    let btn = btnEl;
    if (!btn && typeof event !== 'undefined' && event?.currentTarget) {
        const ct = event.currentTarget;
        btn = ct.classList?.contains('btn-copy-cuenta') ? ct : ct.querySelector?.('.btn-copy-cuenta');
    }
    if (!btn) {
        btn = el.closest('.cr-cuenta-card')?.querySelector('.btn-copy-cuenta');
    }

    function darFeedbackBoton() {
        if (btn) {
            const originalHtml = btn.dataset.origHtml || btn.innerHTML;
            btn.dataset.origHtml = originalHtml;
            btn.innerHTML = '<i class="ti ti-check me-1"></i>¡Copiado!';
            btn.classList.add('btn-copy-cuenta--copied');
            setTimeout(() => {
                btn.innerHTML = originalHtml;
                btn.classList.remove('btn-copy-cuenta--copied');
            }, 2000);
        }
    }

    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(texto)
            .then(() => {
                darFeedbackBoton();
                mostrarToastCopiado(texto);
            })
            .catch(() => {
                copiarFallback(texto, darFeedbackBoton);
            });
    } else {
        copiarFallback(texto, darFeedbackBoton);
    }
}

function copiarFallback(texto, onSuccess) {

    const textarea = document.createElement("textarea");
    textarea.value = texto;
    textarea.style.position = "fixed";
    textarea.style.opacity = "0";

    document.body.appendChild(textarea);
    textarea.focus();
    textarea.select();

    try {
        document.execCommand("copy");
        if (typeof onSuccess === 'function') onSuccess();
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
