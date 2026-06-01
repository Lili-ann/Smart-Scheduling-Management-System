// Change this name when you want browsers to replace the old cache.
const CACHE_NAME = 'scheduler-cache-v2';

// Files saved during installation so the app can still load basic screens offline.
const STATIC_ASSETS = [
    './login.php',
    './signup.php',
    './visitor.php',
    './styles.css',
    './manifest.json',
    './captcha.png'
];

// Install runs when the browser first registers this service worker.
// It opens the cache and saves the important static files.
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => cache.addAll(STATIC_ASSETS))
            .then(() => self.skipWaiting())
    );
});

// Activate runs after install.
// It removes old caches so users do not keep outdated files forever.
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((cacheNames) => Promise.all(
                cacheNames
                    .filter((cacheName) => cacheName !== CACHE_NAME)
                    .map((cacheName) => caches.delete(cacheName))
            ))
            .then(() => self.clients.claim())
    );
});

// Fetch runs every time the page requests a file or page.
self.addEventListener('fetch', (event) => {
    const request = event.request;

    // Do not cache form submissions or other non-GET requests.
    if (request.method !== 'GET') {
        return;
    }

    const requestUrl = new URL(request.url);

    // Only handle files from this same Scheduler website.
    if (requestUrl.origin !== self.location.origin) {
        return;
    }

    // Skip action URLs like CSV export so dynamic actions always reach PHP.
    if (requestUrl.searchParams.has('action')) {
        return;
    }

    // For normal page navigation, try the network first.
    // If offline, fall back to the cached login page.
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request)
                .then((response) => response)
                .catch(() => caches.match('./login.php'))
        );
        return;
    }

    // For static files, use the cached copy first.
    // If it is not cached yet, get it from the network and save a copy.
    event.respondWith(
        caches.match(request).then((cachedResponse) => {
            if (cachedResponse) {
                return cachedResponse;
            }

            return fetch(request).then((networkResponse) => {
                if (!networkResponse || networkResponse.status !== 200) {
                    return networkResponse;
                }

                const responseClone = networkResponse.clone();
                caches.open(CACHE_NAME).then((cache) => {
                    cache.put(request, responseClone);
                });

                return networkResponse;
            });
        })
    );
});
