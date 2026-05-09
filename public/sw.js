/**
 * Service Worker per ACTV Live
 * Gestisce le notifiche push per ritardi e aggiornamenti.
 */

const SW_VERSION = '1.0.0';

// Evento install
self.addEventListener('install', (event) => {
    self.skipWaiting();
});

// Evento activate
self.addEventListener('activate', (event) => {
    event.waitUntil(self.clients.claim());
});

// Gestione notifiche push
self.addEventListener('push', (event) => {
    let data = { title: 'ACTV Live', body: 'Aggiornamento disponibile' };

    if (event.data) {
        try {
            data = event.data.json();
        } catch (e) {
            data.body = event.data.text();
        }
    }

    const options = {
        body: data.body || '',
        icon: '/pwa/web-app-manifest-192x192.png',
        badge: '/pwa/favicon-96x96.png',
        vibrate: [200, 100, 200],
        tag: data.tag || 'actv-notification',
        data: data.url || '/',
        actions: data.actions || []
    };

    event.waitUntil(
        self.registration.showNotification(data.title || 'ACTV Live', options)
    );
});

// Click sulla notifica
self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const url = event.notification.data || '/';

    event.waitUntil(
        self.clients.matchAll({ type: 'window' }).then((clients) => {
            for (const client of clients) {
                if (client.url.includes(self.location.origin) && 'focus' in client) {
                    return client.focus();
                }
            }
            return self.clients.openWindow(url);
        })
    );
});
