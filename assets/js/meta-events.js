(function (window) {
    'use strict';

    const cfg = window.META_EVENTS_CONFIG || {};
    const AJAX_URL = cfg.ajaxUrl || 'front/ajax/meta.ajax.php';
    const sentKeys = new Set();
    const STORAGE_PREFIX = 'cs_meta_once_';
    const BLOCKED_TERMS = /sticker|stickers|rifa|rifas|raffle|raffles|sorteo|sorteos|loteria|loter[ií]a|lottery|ticket|tickets|suerte|boleta|boletas|paquete|paquetes|premio|premios|bingo|apuesta|ganador|ganadores|n[uú]mero|numeros|compra[\s\-]?web|transferencia/i;

    function containsBlockedTerms(value) {
        return BLOCKED_TERMS.test(String(value || ''));
    }

    function sanitizeCustomData(customData) {
        const data = customData || {};
        const clean = {
            currency: 'COP'
        };
        if (data.value !== undefined && !isNaN(Number(data.value))) {
            clean.value = Number(data.value) || 0;
        }
        if (data.num_items !== undefined && !isNaN(parseInt(data.num_items, 10))) {
            clean.num_items = Math.max(1, parseInt(data.num_items, 10));
        }
        if (data.content_type !== undefined) {
            clean.content_type = 'product';
        }
        return clean;
    }

    function sanitizeEventRef(eventRef) {
        if (eventRef === undefined || eventRef === null) {
            return null;
        }

        let value = String(eventRef).trim();
        if (value === '') {
            return null;
        }

        if (containsBlockedTerms(value)) {
            value = value.replace(BLOCKED_TERMS, '');
        }

        value = value.replace(/[^a-z0-9\-]+/gi, '-').replace(/-+/g, '-').replace(/^-+|-+$/g, '');
        return value || null;
    }

    function getCookie(name) {
        const match = document.cookie.match(new RegExp('(?:^|; )' + name.replace(/[.$?*|{}()[\]\\/+^]/g, '\\$&') + '=([^;]*)'));
        return match ? decodeURIComponent(match[1]) : '';
    }

    function getFbp() {
        return getCookie('_fbp');
    }

    function getFbc() {
        return getCookie('_fbc');
    }

    function isEnabled() {
        return !!(cfg.enabled && cfg.pixelId && typeof window.fbq === 'function');
    }

    function commerceData(quantity, value) {
        const qty = Math.max(1, parseInt(quantity, 10) || 1);
        return {
            currency: 'COP',
            value: Number(value) || 0,
            num_items: qty,
            content_type: 'product'
        };
    }

    function dedupeKey(eventName, eventRef) {
        return eventName + '::' + (eventRef || 'auto');
    }

    function wasSentOnce(storageKey) {
        try {
            return sessionStorage.getItem(STORAGE_PREFIX + storageKey) === '1';
        } catch (e) {
            return sentKeys.has('once::' + storageKey);
        }
    }

    function markSentOnce(storageKey) {
        sentKeys.add('once::' + storageKey);
        try {
            sessionStorage.setItem(STORAGE_PREFIX + storageKey, '1');
        } catch (e) {
            
        }
    }

    function firePixel(eventName, customData, eventId) {
        if (!isEnabled() || !eventId) {
            return;
        }

        window.fbq('track', eventName, customData || {}, { eventID: eventId });
    }

    async function track(eventName, customData, eventRef, userData, options) {
        options = options || {};

        const allowed = cfg.standardEvents || [
            'PageView',
            'ViewContent',
            'AddToCart',
            'InitiateCheckout',
            'Lead',
            'AddPaymentInfo'
        ];
        if (!allowed.includes(eventName)) {
            return null;
        }

        if (!cfg.enabled) {
            return null;
        }

        customData = sanitizeCustomData(customData);
        eventRef = sanitizeEventRef(eventRef);

        const key = dedupeKey(eventName, eventRef);
        if (!options.allowRepeat && sentKeys.has(key)) {
            return null;
        }

        if (cfg.capiEnabled === false) {
            const fallbackId = 'px-' + eventName.toLowerCase() + '-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 7);
            firePixel(eventName, customData, fallbackId);
            sentKeys.add(key);
            return { success: true, event_id: fallbackId, capi_sent: false };
        }

        const payload = new URLSearchParams();
        payload.append('action', 'track_event');
        payload.append('event_name', eventName);
        payload.append('custom_data', JSON.stringify(customData || {}));
        if (eventRef) {
            payload.append('event_ref', String(eventRef));
        }
        if (userData && typeof userData === 'object') {
            payload.append('user_data', JSON.stringify(userData));
        }

        const fbp = getFbp();
        const fbc = getFbc();
        if (fbp) payload.append('fbp', fbp);
        if (fbc) payload.append('fbc', fbc);

        try {
            const res = await fetch(AJAX_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                body: payload.toString(),
                credentials: 'same-origin'
            });

            const json = await res.json();
            if (json && json.success && json.event_id) {
                const pixelData = sanitizeCustomData(json.custom_data || customData);
                firePixel(eventName, pixelData, json.event_id);
                sentKeys.add(key);
                return json;
            }
        } catch (err) {
            // En caso de fallo de red CAPI, disparamos en navegador para no perder el evento
        }

        const clientFallbackId = 'px-' + eventName.toLowerCase() + '-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 7);
        firePixel(eventName, customData, clientFallbackId);
        sentKeys.add(key);
        return { success: true, event_id: clientFallbackId, capi_sent: false };
    }

    async function trackOnce(storageKey, eventName, customData, eventRef, userData) {
        if (wasSentOnce(storageKey)) {
            return null;
        }

        const result = await track(eventName, customData, eventRef, userData);
        if (result) {
            markSentOnce(storageKey);
        }

        return result;
    }

    function fireOnce(storageKey, eventName, customData, eventId) {
        if (wasSentOnce(storageKey)) {
            return;
        }

        firePixel(eventName, sanitizeCustomData(customData), eventId);
        markSentOnce(storageKey);
    }

    window.MetaEvents = {
        track: track,
        trackOnce: trackOnce,
        fireOnly: firePixel,
        fireOnce: fireOnce,
        commerceData: commerceData,
        getFbp: getFbp,
        getFbc: getFbc,
        isEnabled: isEnabled,
        STANDARD_EVENTS: cfg.standardEvents || []
    };
})(window);
