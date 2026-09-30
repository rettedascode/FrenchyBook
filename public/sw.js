/*
 * FrenchyBook – Service Worker
 *
 * - Offline: Ist keine Verbindung da, erscheint eine freundliche Offline-Seite statt eines Browser-Fehlers.
 * - Schneller Start: Versionierte Dateien (/assets/…) kommen aus dem Cache, Cover-Bilder ebenso.
 * - Push: Zeigt Benachrichtigungen an und öffnet beim Antippen die passende Seite.
 *
 * Persönliche Seiten (HTML) werden bewusst NICHT zwischengespeichert – auf geteilten
 * Geräten soll niemand nach dem Abmelden fremde Daten aus dem Cache sehen.
 *
 * Bei Änderungen an dieser Datei VERSION erhöhen, dann werden alte Caches aufgeräumt.
 */
const VERSION = 'fb-2026-09-28-1';
const STATIC_CACHE = `${VERSION}-static`;
const IMAGE_CACHE = `${VERSION}-images`;
const OFFLINE_URL = '/offline.html';
const PRECACHE = [OFFLINE_URL, '/favicon.svg', '/icon-192.png', '/badge-96.png'];
const MAX_IMAGES = 300;

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(STATIC_CACHE)
            .then((cache) => cache.addAll(PRECACHE))
            .then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil((async () => {
        const keys = await caches.keys();
        await Promise.all(keys.filter((key) => !key.startsWith(VERSION)).map((key) => caches.delete(key)));
        if (self.registration.navigationPreload) {
            await self.registration.navigationPreload.enable();
        }
        await self.clients.claim();
    })());
});

self.addEventListener('fetch', (event) => {
    const { request } = event;
    if (request.method !== 'GET') return;

    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return;

    // Versionierte Assets ändern sich nie → Cache zuerst
    if (url.pathname.startsWith('/assets/')) {
        event.respondWith(cacheFirst(request, STATIC_CACHE));
        return;
    }

    // Cover & Thumbnails → sofort aus dem Cache, im Hintergrund aktualisieren
    if (url.pathname.startsWith('/media/cache/') || url.pathname.startsWith('/uploads/covers/')) {
        event.respondWith(staleWhileRevalidate(event, IMAGE_CACHE));
        return;
    }

    // Seitenaufrufe (auch Turbo-Navigationen) → Netz, bei Offline die Offline-Seite
    const isPage = request.mode === 'navigate'
        || (request.headers.get('Accept') || '').includes('text/html');
    if (isPage && !request.headers.get('Turbo-Frame')) {
        event.respondWith(networkWithOfflineFallback(event));
    }
});

async function cacheFirst(request, cacheName) {
    const cached = await caches.match(request);
    if (cached) return cached;
    const response = await fetch(request);
    if (response.ok) {
        const cache = await caches.open(cacheName);
        cache.put(request, response.clone());
    }
    return response;
}

async function staleWhileRevalidate(event, cacheName) {
    const cache = await caches.open(cacheName);
    const cached = await cache.match(event.request);
    const network = fetch(event.request)
        .then(async (response) => {
            if (response.ok) {
                await cache.put(event.request, response.clone());
                trimCache(cacheName, MAX_IMAGES);
            }
            return response;
        })
        .catch(() => cached);
    if (cached) {
        event.waitUntil(network);
        return cached;
    }
    return network;
}

async function networkWithOfflineFallback(event) {
    try {
        const preloaded = await event.preloadResponse;
        if (preloaded) return preloaded;
        return await fetch(event.request);
    } catch (error) {
        const offline = await caches.match(OFFLINE_URL);
        return offline || new Response('Offline', { status: 503, headers: { 'Content-Type': 'text/plain; charset=utf-8' } });
    }
}

async function trimCache(cacheName, maxEntries) {
    const cache = await caches.open(cacheName);
    const keys = await cache.keys();
    for (let i = 0; i < keys.length - maxEntries; i++) {
        await cache.delete(keys[i]);
    }
}

// ---------- Push-Benachrichtigungen ----------

self.addEventListener('push', (event) => {
    let data = {};
    try {
        data = event.data ? event.data.json() : {};
    } catch (e) {
        data = { body: event.data ? event.data.text() : '' };
    }

    event.waitUntil(self.registration.showNotification(data.title || 'FrenchyBook', {
        body: data.body || '',
        icon: '/icon-192.png',
        badge: '/badge-96.png',
        tag: data.tag || undefined,
        renotify: !!data.tag,
        lang: data.lang || 'de',
        data: { url: data.url || '/' },
    }));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const target = new URL(event.notification.data?.url || '/', self.location.origin).href;

    event.waitUntil((async () => {
        const windows = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
        // Offenes FrenchyBook-Fenster wiederverwenden
        for (const client of windows) {
            if (new URL(client.url).origin === self.location.origin && 'focus' in client) {
                await client.focus();
                if ('navigate' in client) {
                    return client.navigate(target);
                }
                return;
            }
        }
        return self.clients.openWindow(target);
    })());
});

// Wenn der Push-Dienst das Abo erneuert, beim Server nachtragen lassen (nächster Seitenaufruf gleicht ab)
self.addEventListener('pushsubscriptionchange', (event) => {
    event.waitUntil(self.clients.matchAll({ type: 'window' }).then((clients) => {
        clients.forEach((client) => client.postMessage({ type: 'push-resubscribe' }));
    }));
});
