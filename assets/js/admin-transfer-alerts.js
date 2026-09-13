(function () {
    const ENDPOINT = '/front/ajax/transferencias.ajax.php';
    const STORAGE_MAX_ID = 'cr_transfer_alert_max_id';
    const POLL_VISIBLE_MS = 8000;
    const POLL_HIDDEN_MS = 15000;
    const TRANSFERENCIAS_URL = '/front/transferencias.php';

    let audioCtx = null;
    let lastMaxId = 0;
    let seeded = false;
    let pollTimer = null;
    let channel = null;

    function canWatchTransfers() {
        return typeof adminCanCap === 'function' && adminCanCap('transferencias.view');
    }

    function isIosChrome() {
        const ua = navigator.userAgent || '';
        return isIos() && /CriOS|FxiOS|EdgiOS|OPiOS|DuckDuckGo|GSA\//i.test(ua);
    }

    function notificationsSupported() {
        return typeof window !== 'undefined'
            && 'Notification' in window
            && typeof Notification.requestPermission === 'function';
    }

    function formatMoney(n) {
        return '$' + Number(n || 0).toLocaleString('es-CO', {
            maximumFractionDigits: 0,
            minimumFractionDigits: 0,
        });
    }

    function getAudioContext() {
        if (audioCtx) {
            return audioCtx;
        }
        const Ctor = window.AudioContext || window.webkitAudioContext;
        if (!Ctor) {
            return null;
        }
        audioCtx = new Ctor();
        return audioCtx;
    }

    function unlockAudio() {
        const ctx = getAudioContext();
        if (!ctx) {
            return;
        }
        if (ctx.state === 'suspended') {
            ctx.resume().catch(() => {});
        }
    }

    function playTone(ctx, freq, start, duration, gainValue) {
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.type = 'sine';
        osc.frequency.setValueAtTime(freq, start);
        gain.gain.setValueAtTime(0.0001, start);
        gain.gain.exponentialRampToValueAtTime(gainValue, start + 0.02);
        gain.gain.exponentialRampToValueAtTime(0.0001, start + duration);
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.start(start);
        osc.stop(start + duration + 0.03);
    }

    function playTilin() {
        const ctx = getAudioContext();
        if (!ctx) {
            return;
        }
        if (ctx.state === 'suspended') {
            ctx.resume().then(() => playTilinNow(ctx)).catch(() => {});
            return;
        }
        playTilinNow(ctx);
    }

    function playTilinNow(ctx) {
        const t0 = ctx.currentTime + 0.01;
        playTone(ctx, 1046.5, t0, 0.12, 0.18);
        playTone(ctx, 1567.98, t0 + 0.11, 0.18, 0.14);
    }

    function faviconHref() {
        const link = document.querySelector('link[rel="icon"], link[data-site-favicon]');
        return link ? link.href : '';
    }

    function updateSidebarBadge(count) {
        const badge = document.getElementById('sidebarTransferPendingCount');
        const link = document.getElementById('navLinkTransferencias');
        if (!badge) {
            return;
        }
        const n = Math.max(0, Number(count) || 0);
        if (n <= 0) {
            badge.textContent = '0';
            badge.classList.add('d-none');
            badge.setAttribute('aria-hidden', 'true');
            link?.removeAttribute('aria-label');
            return;
        }
        badge.textContent = n > 99 ? '99+' : String(n);
        badge.classList.remove('d-none');
        badge.setAttribute('aria-hidden', 'false');
        if (link) {
            link.setAttribute('aria-label', `Transferencias, ${n} pendientes`);
        }
    }

    function rememberMaxId(maxId) {
        lastMaxId = maxId;
        try {
            localStorage.setItem(STORAGE_MAX_ID, String(maxId));
        } catch (e) {}
        if (channel) {
            try {
                channel.postMessage({ type: 'seen', maxId });
            } catch (e) {}
        }
    }

    function firstName(item) {
        const raw = String(item?.name || '').trim();
        const first = raw.split(/\s+/)[0] || '';
        return first || 'Alguien';
    }

    function totalAmount(items) {
        return items.reduce((sum, item) => sum + (Number(item.amount) || 0), 0);
    }

    function alertBody(items) {
        const valor = formatMoney(totalAmount(items));
        if (items.length === 1) {
            return `Aprueba la transferencia · ${valor}`;
        }
        return `Aprueba las transferencias · ${valor}`;
    }

    function alertTitle(items) {
        if (items.length === 1) {
            return `${firstName(items[0])} acaba de comprar`;
        }
        return `${items.length} personas acaban de comprar`;
    }

    function showBrowserNotification(items) {
        if (!notificationsSupported() || Notification.permission !== 'granted') {
            return;
        }
        const title = alertTitle(items);
        const options = {
            body: alertBody(items),
            tag: 'cr-nueva-transferencia',
            icon: faviconHref() || undefined,
            data: { url: TRANSFERENCIAS_URL },
        };
        const viaWorker = navigator.serviceWorker?.ready
            ?.then((reg) => {
                if (reg && typeof reg.showNotification === 'function') {
                    return reg.showNotification(title, options);
                }
                throw new Error('no-sw-notify');
            });
        if (viaWorker) {
            viaWorker.catch(() => {
                try {
                    new Notification(title, options);
                } catch (e) {}
            });
            return;
        }
        try {
            new Notification(title, options);
        } catch (e) {}
    }

    function announceNewTransfers(items, count) {
        if (!items.length) {
            updateSidebarBadge(count);
            return;
        }
        playTilin();
        showBrowserNotification(items);
        updateSidebarBadge(count);
        window.dispatchEvent(new CustomEvent('cr:nueva-transferencia', {
            detail: { items, count },
        }));
    }

    function handleSnapshot(data, { notify }) {
        const count = Number(data.count) || 0;
        const maxId = Number(data.max_id) || 0;
        const items = Array.isArray(data.items) ? data.items : [];

        updateSidebarBadge(count);

        if (!seeded) {
            seeded = true;
            if (lastMaxId <= 0) {
                rememberMaxId(maxId);
                return;
            }
        }

        if (!notify || maxId <= lastMaxId) {
            if (maxId > lastMaxId) {
                rememberMaxId(maxId);
            }
            return;
        }

        const newcomers = items
            .filter((item) => Number(item.id) > lastMaxId)
            .sort((a, b) => Number(a.id) - Number(b.id));
        rememberMaxId(maxId);
        announceNewTransfers(newcomers.length ? newcomers : items.slice(0, 1), count);
    }

    async function poll(notify) {
        if (typeof adminFetchJson !== 'function') {
            return;
        }
        const fd = new FormData();
        fd.append('action', 'pendientes_alerta');
        const data = await adminFetchJson(ENDPOINT, { body: fd });
        if (!data || data.success !== true) {
            return;
        }
        handleSnapshot(data, { notify: notify !== false });
    }

    function scheduleNext() {
        clearTimeout(pollTimer);
        const delay = document.hidden ? POLL_HIDDEN_MS : POLL_VISIBLE_MS;
        pollTimer = setTimeout(async () => {
            try {
                await poll(true);
            } catch (e) {}
            scheduleNext();
        }, delay);
    }

    function isIos() {
        return /iPad|iPhone|iPod/i.test(navigator.userAgent)
            || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    }

    function isStandalone() {
        return window.matchMedia('(display-mode: standalone)').matches
            || window.navigator.standalone === true;
    }

    function iosVersionLabel() {
        const m = String(navigator.userAgent || '').match(/OS (\d+)[._](\d+)/);
        if (!m) {
            return 'desconocida';
        }
        return m[1] + '.' + m[2];
    }

    function iosSupportsWebPush() {
        const m = String(navigator.userAgent || '').match(/OS (\d+)[._](\d+)/);
        if (!m) {
            return !isIos();
        }
        const major = Number(m[1]);
        const minor = Number(m[2]);
        return major > 16 || (major === 16 && minor >= 4);
    }

    function alertDebugText() {
        const perm = notificationsSupported() ? Notification.permission : 'no-existe';
        return [
            'Modo app: ' + (isStandalone() ? 'sí (ícono de inicio)' : 'no (estás en Safari/Chrome)'),
            'Google Chrome iPhone: ' + (isIosChrome() ? 'sí' : 'no'),
            'API avisos: ' + (notificationsSupported() ? 'sí' : 'no'),
            'Permiso: ' + perm,
            'Service Worker: ' + ('serviceWorker' in navigator ? 'sí' : 'no'),
            'iOS: ' + iosVersionLabel(),
        ].join('\n');
    }

    function urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
        const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        const raw = window.atob(base64);
        const output = new Uint8Array(raw.length);
        for (let i = 0; i < raw.length; i += 1) {
            output[i] = raw.charCodeAt(i);
        }
        return output;
    }

    async function ensureServiceWorker() {
        if (!('serviceWorker' in navigator)) {
            return null;
        }
        const reg = await navigator.serviceWorker.register('/sw-admin.js', { scope: '/' });
        await navigator.serviceWorker.ready;
        return navigator.serviceWorker.ready;
    }

    function notifyUser(mensaje) {
        const text = String(mensaje || '') + '\n\n' + alertDebugText();
        if (typeof alertify !== 'undefined') {
            alertify.alert('Avisos de compras', text.replace(/\n/g, '<br>'));
            return;
        }
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'info',
                title: 'Avisos de compras',
                html: text.replace(/\n/g, '<br>'),
                confirmButtonText: 'Entendido',
            });
            return;
        }
        window.alert(text);
    }

    function explainHowToEnable() {
        if (!iosSupportsWebPush() && isIos()) {
            notifyUser('Tu iPhone necesita iOS 16.4 o superior para avisos web. Actualízalo en Ajustes → General → Actualización de software.');
            return;
        }
        if (isIosChrome()) {
            notifyUser('Sigues en Google Chrome. En iPhone eso no activa avisos. Cierra esta app, abre Safari (brújula), entra al panel, Compartir → Agregar a pantalla de inicio, y abre el ícono nuevo.');
            return;
        }
        if (isIos() && !isStandalone()) {
            notifyUser('No estás dentro del ícono de inicio: estás en una pestaña de Safari. Cierra Safari, toca el ícono “Panel” en la pantalla de inicio y ahí pulsa Activar avisos. Si el ícono es el de Chrome, bórralo y créalo otra vez desde Safari.');
            return;
        }
        if (!notificationsSupported()) {
            notifyUser('Este iPhone no dejó activar avisos. Borra el ícono, agrégalo otra vez desde Safari y abre solo ese ícono. Si sigue igual, el código nuevo tiene que estar ya en el sitio que abres desde el celular.');
            return;
        }
        if (Notification.permission === 'denied') {
            notifyUser('Los avisos quedaron bloqueados. En el iPhone: Ajustes → Notificaciones → Panel (o Safari) → Permitir avisos.');
        }
    }

    function shouldShowEnableButton() {
        if (!canWatchTransfers()) {
            return false;
        }
        if (!notificationsSupported()) {
            return true;
        }
        return Notification.permission !== 'granted';
    }

    function bindEnableButton(btn) {
        if (!btn || btn.dataset.bound === '1') {
            return;
        }
        btn.dataset.bound = '1';
        btn.addEventListener('click', async () => {
            unlockAudio();
            playTilin();
            if (isIosChrome() || (isIos() && !iosSupportsWebPush())) {
                explainHowToEnable();
                return;
            }
            if (isIos() && !isStandalone()) {
                explainHowToEnable();
                return;
            }
            try {
                const reg = await ensureServiceWorker();
                const vapid = String(window.ADMIN_VAPID_PUBLIC_KEY || '');
                if (reg && reg.pushManager && vapid) {
                    await reg.pushManager.subscribe({
                        userVisibleOnly: true,
                        applicationServerKey: urlBase64ToUint8Array(vapid),
                    });
                } else if (notificationsSupported()) {
                    await Notification.requestPermission();
                }
                const granted = notificationsSupported() && Notification.permission === 'granted';
                if (!granted) {
                    explainHowToEnable();
                    renderEnableButton();
                    return;
                }
                if (reg && typeof reg.showNotification === 'function') {
                    await reg.showNotification('Avisos activados', {
                        body: 'Cuando alguien compre, te avisaremos aquí.',
                        tag: 'cr-avisos-ok',
                        data: { url: TRANSFERENCIAS_URL },
                    });
                }
            } catch (e) {
                notifyUser('No se pudieron activar: ' + (e && e.message ? e.message : 'error del navegador'));
            }
            renderEnableButton();
        });
    }

    function renderEnableButton() {
        const item = document.getElementById('adminTransferAlertItem');
        const show = shouldShowEnableButton();
        if (item) {
            item.classList.toggle('d-none', !show);
            bindEnableButton(document.getElementById('btnActivarAvisosTransfer'));
            return;
        }
        if (!show || document.getElementById('btnActivarAvisosTransfer')) {
            return;
        }
        const nav = document.querySelector('.app-header .navbar-nav');
        if (!nav) {
            return;
        }
        const li = document.createElement('li');
        li.className = 'nav-item';
        li.id = 'adminTransferAlertItem';
        li.innerHTML = `<button type="button" class="btn btn-sm btn-outline-secondary admin-transfer-alert-btn" id="btnActivarAvisosTransfer" title="Avisar cuando llegue un comprobante">
            <i class="ti ti-bell"></i>
            <span class="admin-transfer-alert-btn__label">Activar avisos</span>
        </button>`;
        nav.appendChild(li);
        bindEnableButton(document.getElementById('btnActivarAvisosTransfer'));
    }

    function initChannel() {
        if (typeof BroadcastChannel === 'undefined') {
            return;
        }
        try {
            channel = new BroadcastChannel('cr-transfer-alerts');
            channel.onmessage = (event) => {
                const maxId = Number(event.data?.maxId) || 0;
                if (maxId > lastMaxId) {
                    lastMaxId = maxId;
                    try {
                        localStorage.setItem(STORAGE_MAX_ID, String(maxId));
                    } catch (e) {}
                }
            };
        } catch (e) {
            channel = null;
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        if (!canWatchTransfers()) {
            return;
        }

        try {
            lastMaxId = Number(localStorage.getItem(STORAGE_MAX_ID) || 0) || 0;
        } catch (e) {
            lastMaxId = 0;
        }

        initChannel();
        renderEnableButton();

        ['pointerdown', 'keydown', 'click', 'touchstart'].forEach((evt) => {
            window.addEventListener(evt, unlockAudio, { once: true, passive: true });
        });
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) {
                poll(true).catch(() => {});
            }
        });

        poll(false).catch(() => {}).finally(scheduleNext);
    });
})();
