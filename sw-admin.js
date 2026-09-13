/* Panel admin: avisos de transferencias en iOS/Safari (PWA). */
self.addEventListener('install', (event) => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('push', (event) => {
    let data = {};
    try {
        data = event.data ? event.data.json() : {};
    } catch (e) {
        data = { body: event.data ? event.data.text() : '' };
    }
    const title = data.title || 'Nueva compra';
    const options = {
        body: data.body || 'Hay un comprobante por revisar',
        tag: data.tag || 'cr-nueva-transferencia',
        data: { url: data.url || '/front/transferencias.php' },
    };
    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = (event.notification.data && event.notification.data.url)
        ? event.notification.data.url
        : '/front/transferencias.php';

    event.waitUntil((async () => {
        const windows = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
        for (const client of windows) {
            if ('focus' in client) {
                await client.focus();
                if ('navigate' in client && !/transferencias\.php/.test(client.url || '')) {
                    await client.navigate(url);
                }
                return;
            }
        }
        if (self.clients.openWindow) {
            await self.clients.openWindow(url);
        }
    })());
});
