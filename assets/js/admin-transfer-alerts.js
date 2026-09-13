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

    function notificationsSupported() {
        return typeof window !== 'undefined' && 'Notification' in window;
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
        try {
            const n = new Notification(alertTitle(items), {
                body: alertBody(items),
                tag: 'cr-nueva-transferencia',
                icon: faviconHref() || undefined,
            });
            n.onclick = function () {
                window.focus();
                n.close();
                if (!/transferencias\.php(?:$|\?)/.test(window.location.pathname)) {
                    window.location.href = TRANSFERENCIAS_URL;
                }
            };
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

    function renderEnableButton() {
        if (!notificationsSupported() || Notification.permission !== 'default') {
            document.getElementById('adminTransferAlertItem')?.remove();
            return;
        }
        if (document.getElementById('adminTransferAlertItem')) {
            return;
        }
        const nav = document.querySelector('.app-header .navbar-nav.flex-row');
        if (!nav) {
            return;
        }
        const li = document.createElement('li');
        li.className = 'nav-item me-2';
        li.id = 'adminTransferAlertItem';
        li.innerHTML = `<button type="button" class="btn btn-sm btn-outline-secondary admin-transfer-alert-btn" id="btnActivarAvisosTransfer" title="Avisar en el navegador cuando llegue un comprobante">
            <i class="ti ti-bell"></i>
            <span class="d-none d-md-inline ms-1">Activar avisos</span>
        </button>`;
        nav.insertBefore(li, nav.firstElementChild);
        document.getElementById('btnActivarAvisosTransfer')?.addEventListener('click', async () => {
            unlockAudio();
            try {
                await Notification.requestPermission();
            } catch (e) {}
            playTilin();
            renderEnableButton();
        });
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
