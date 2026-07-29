const CACHE_NAME = 'portal-fiscal-static-v20260729';
const STATIC_ASSETS = [
    '/favicon.svg',
    '/favicon-16.png',
    '/favicon-32.png',
    '/favicon-48.png',
    '/favicon-192.png',
    '/favicon-512.png',
    '/apple-touch-icon.png',
    '/pwa-maskable-512.png',
    '/site.webmanifest',
    '/css/portal.css',
    '/js/portal.js',
    '/images/bakoftec-logo.png',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => cache.addAll(STATIC_ASSETS))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key))))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;

    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    if (url.origin !== self.location.origin) {
        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(fetch(request));

        return;
    }

    if (url.pathname.startsWith('/css/') || url.pathname.startsWith('/js/')) {
        event.respondWith(
            fetch(request)
                .then((response) => {
                    const copy = response.clone();
                    caches.open(CACHE_NAME).then((cache) => cache.put(request, copy));

                    return response;
                })
                .catch(() => caches.match(request, { ignoreSearch: true }))
        );

        return;
    }

    event.respondWith(
        caches.match(request, { ignoreSearch: true })
            .then((cached) => cached || fetch(request))
    );
});
