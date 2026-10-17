/* Rahandaz PWA: only the public offline document and official logo may enter CacheStorage. */
const BASE = "__PWA_BASE__";
const VERSION = "__PWA_VERSION__";
const PREFIX = `rahandaz-pwa:${BASE}:`;
const CACHE = PREFIX + VERSION;
const OFFLINE = '/assets/pwa/offline.html';
const PUBLIC_FILES = [OFFLINE, '/branding/logo-symbol.svg'];

self.addEventListener('install', (event) => {
    event.waitUntil((async () => {
        const cache = await caches.open(CACHE);
        await cache.addAll(PUBLIC_FILES.map((path) => new Request(path, { credentials: 'omit', cache: 'reload' })));
        // Never skip waiting automatically: an open management form must not be reloaded.
    })());
});

self.addEventListener('activate', (event) => {
    event.waitUntil((async () => {
        for (const name of await caches.keys()) {
            if (name.startsWith(PREFIX) && name !== CACHE) await caches.delete(name);
        }
        await self.clients.claim();
    })());
});

self.addEventListener('message', (event) => {
    if (event.data?.type === 'RAHANDAZ_APPLY_UPDATE') event.waitUntil(self.skipWaiting());
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    const url = new URL(request.url);
    if (request.method !== 'GET' || url.origin !== self.location.origin) return;
    // Inertia JSON, APIs, POSTs, auth pages and private assets are NEVER cached.
    if (request.mode === 'navigate' && url.pathname.startsWith(BASE + '/') && !request.headers.has('X-Inertia')) {
        event.respondWith((async () => {
            try {
                return await fetch(request, { cache: 'no-store' });
            } catch {
                return (await caches.open(CACHE)).match(OFFLINE).then((response) => response ?? Response.error());
            }
        })());
    } else if (!url.search && PUBLIC_FILES.includes(url.pathname) && !request.headers.has('X-Inertia')) {
        event.respondWith((async () => (await caches.open(CACHE)).match(url.pathname).then((response) => response ?? fetch(request)))());
    }
});
