const CACHE_NAME = 'ecrivain-cache-v3';
const urlsToCache = [
    '/public/manifest.json',
    '/public/icons/icon-192.png',
    '/public/icons/icon-512.png',
    '/public/offline-reader.html',
    '/public/js/offline-reader.js'
];

self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => cache.addAll(urlsToCache))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(keyList =>
            Promise.all(keyList.map(key => {
                if (key !== CACHE_NAME) return caches.delete(key);
            }))
        ).then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', event => {
    const url = new URL(event.request.url);

    // Network-first for lecture pages: serve offline reader on failure
    if (/\/project\/\d+\/lecture/.test(url.pathname)) {
        event.respondWith(
            fetch(event.request).catch(() =>
                caches.match('/public/offline-reader.html')
                    .then(r => r || new Response('Hors-ligne', { status: 503 }))
            )
        );
        return;
    }

    // Never cache dynamic PHP pages or editors
    if (url.pathname.endsWith('.css') ||
        url.pathname.endsWith('.js') ||
        url.pathname.endsWith('.php') ||
        url.pathname.includes('/dashboard') ||
        url.pathname.includes('/project') ||
        url.pathname.includes('/chapter')) {
        event.respondWith(fetch(event.request));
        return;
    }

    // Cache-first for static assets (icons, manifest, offline files)
    event.respondWith(
        caches.match(event.request).then(response =>
            response || fetch(event.request).then(fetchResponse => {
                return caches.open(CACHE_NAME).then(cache => {
                    cache.put(event.request, fetchResponse.clone());
                    return fetchResponse;
                });
            })
        )
    );
});
