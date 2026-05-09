/**
 * Service Worker per ACTV Live
 * Gestisce cache offline per asset statici, dati GTFS e API responses.
 * Strategia: Network-first per API, Cache-first per asset statici.
 */

const CACHE_VERSION = 'v1';
const STATIC_CACHE = `actv-static-${CACHE_VERSION}`;
const DATA_CACHE = `actv-data-${CACHE_VERSION}`;
const API_CACHE = `actv-api-${CACHE_VERSION}`;

const STATIC_ASSETS = [
    '/',
    '/css/style.css',
    '/css/structure/structure-style.css',
    '/css/home.css',
    '/css/structure/structure-home.css',
    '/css/stop.css',
    '/css/structure/structure-stop.css',
    '/css/routeFinder.css',
    '/css/structure/structure-routeFinder.css',
    '/css/routeResults.css',
    '/css/structure/structure-routeResults.css',
    '/css/routeDetails.css',
    '/css/structure/structure-routeDetails.css',
    '/css/stationSelector.css',
    '/css/structure/structure-stationSelector.css',
    '/css/cookie-notice.css',
    '/js/utils.js',
    '/js/script-home.js',
    '/js/stop.js',
    '/js/routeFinder.js',
    '/js/routeResults.js',
    '/js/routeDetails.js',
    '/js/stationSelector.js',
    '/js/cookie-notice.js',
    '/js/theme.js',
    '/pwa/web-app-manifest-192x192.png',
    '/pwa/web-app-manifest-512x512.png',
    '/pwa/favicon-96x96.png',
    '/pwa/site.webmanifest'
];

// API paths to cache
const CACHEABLE_API_PATTERNS = [
    '/api/stops',
    '/api/stop-lines',
    '/api/plan-route',
    '/api/gtfs-stops'
];

// Install: pre-cache static assets
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(STATIC_CACHE).then((cache) => {
            return cache.addAll(STATIC_ASSETS).catch((err) => {
                console.warn('SW: some static assets failed to cache:', err);
            });
        })
    );
    self.skipWaiting();
});

// Activate: clean old caches
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => {
            return Promise.all(
                keys.filter((key) => {
                    return key.startsWith('actv-') &&
                           key !== STATIC_CACHE &&
                           key !== DATA_CACHE &&
                           key !== API_CACHE;
                }).map((key) => caches.delete(key))
            );
        }).then(() => self.clients.claim())
    );
});

// Fetch: apply caching strategies
self.addEventListener('fetch', (event) => {
    const url = new URL(event.request.url);

    // Skip non-GET requests
    if (event.request.method !== 'GET') return;

    // Skip external requests (except ACTV real-time API)
    if (url.origin !== self.location.origin &&
        !url.hostname.includes('oraritemporeale.actv.it')) {
        return;
    }

    // API requests: Network-first, fallback to cache
    if (isCacheableApi(url)) {
        event.respondWith(networkFirstStrategy(event.request, API_CACHE));
        return;
    }

    // ACTV real-time API: Network-first with short cache
    if (url.hostname.includes('oraritemporeale.actv.it')) {
        event.respondWith(networkFirstStrategy(event.request, API_CACHE));
        return;
    }

    // Static assets: Cache-first, fallback to network
    if (isStaticAsset(url)) {
        event.respondWith(cacheFirstStrategy(event.request, STATIC_CACHE));
        return;
    }

    // HTML pages: Network-first for fresh content
    if (event.request.headers.get('accept')?.includes('text/html')) {
        event.respondWith(networkFirstStrategy(event.request, STATIC_CACHE));
        return;
    }
});

// Push notification handler
self.addEventListener('push', (event) => {
    let data = { title: 'ACTV Live', body: 'Aggiornamento disponibile' };

    if (event.data) {
        try {
            data = event.data.json();
        } catch (e) {
            data.body = event.data.text();
        }
    }

    event.waitUntil(
        self.registration.showNotification(data.title || 'ACTV Live', {
            body: data.body || '',
            icon: '/pwa/web-app-manifest-192x192.png',
            badge: '/pwa/favicon-96x96.png',
            vibrate: [200, 100, 200],
            tag: data.tag || 'actv-notification',
            data: data.url || '/'
        })
    );
});

// Notification click handler
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

// Message handler for cache management
self.addEventListener('message', (event) => {
    if (event.data && event.data.type === 'CLEAR_CACHE') {
        event.waitUntil(
            caches.keys().then((keys) =>
                Promise.all(keys.map((key) => caches.delete(key)))
            ).then(() => {
                if (event.ports && event.ports[0]) {
                    event.ports[0].postMessage({ cleared: true });
                }
            })
        );
    }

    if (event.data && event.data.type === 'CACHE_GTFS') {
        event.waitUntil(cacheGtfsData());
    }
});

/**
 * Caching Strategies
 */

async function networkFirstStrategy(request, cacheName) {
    try {
        const response = await fetch(request);
        if (response.ok) {
            const cache = await caches.open(cacheName);
            cache.put(request, response.clone());
        }
        return response;
    } catch (error) {
        const cached = await caches.match(request);
        if (cached) return cached;
        return offlineFallback();
    }
}

async function cacheFirstStrategy(request, cacheName) {
    const cached = await caches.match(request);
    if (cached) return cached;

    try {
        const response = await fetch(request);
        if (response.ok) {
            const cache = await caches.open(cacheName);
            cache.put(request, response.clone());
        }
        return response;
    } catch (error) {
        return offlineFallback();
    }
}

function offlineFallback() {
    return new Response(
        `<!DOCTYPE html>
        <html lang="it">
        <head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Offline - ACTV Live</title>
        <style>
            body { font-family: 'Inter', sans-serif; background: #F5F5F5; display: flex;
                   align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
            .offline-card { background: white; border-radius: 15px; padding: 2rem; text-align: center;
                           max-width: 400px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
            h2 { color: #009E61; }
            p { color: #666; }
            button { background: #009E61; color: white; border: none; border-radius: 10px;
                    padding: 12px 24px; font-weight: 700; cursor: pointer; margin-top: 1rem; }
        </style>
        </head>
        <body>
            <div class="offline-card">
                <h2>Sei offline</h2>
                <p>La connessione non e' disponibile. Alcune funzionalita' potrebbero essere limitate.</p>
                <button onclick="location.reload()">Riprova</button>
            </div>
        </body>
        </html>`,
        { headers: { 'Content-Type': 'text/html' } }
    );
}

/**
 * Helper functions
 */

function isCacheableApi(url) {
    return CACHEABLE_API_PATTERNS.some((pattern) => url.pathname.startsWith(pattern));
}

function isStaticAsset(url) {
    return url.pathname.match(/\.(js|css|png|jpg|svg|ico|webmanifest|woff2?)$/);
}

async function cacheGtfsData() {
    try {
        const cache = await caches.open(DATA_CACHE);
        const endpoints = ['/api/stops', '/api/gtfs-stops?return=true'];
        await Promise.all(
            endpoints.map(async (ep) => {
                try {
                    const response = await fetch(ep);
                    if (response.ok) await cache.put(ep, response);
                } catch (e) {
                    console.warn('SW: failed to cache', ep, e);
                }
            })
        );
    } catch (e) {
        console.warn('SW: GTFS cache failed:', e);
    }
}
