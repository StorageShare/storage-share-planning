const CACHE_NAME = 'planning-offline-v1';
const STATIC_CACHE = 'static-offline-v1';

const STATIC_ASSETS = [
    '/',
    '/css/app.css',
    '/js/app.js',
    '/offline.html',
    '/manifest.json'
];

const CACHEABLE_ROUTES = [
    '/my-planning',
    '/dashboard'
];

const API_CACHE_PATTERNS = [
    '/api/v1/offline/planning/',
    '/api/v1/offline/sync/'
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        Promise.all([
            caches.open(STATIC_CACHE).then(cache => cache.addAll(STATIC_ASSETS)),
            caches.open(CACHE_NAME)
        ])
    );
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then(cacheNames => {
            return Promise.all(
                cacheNames.map(cacheName => {
                    if (cacheName !== CACHE_NAME && cacheName !== STATIC_CACHE) {
                        return caches.delete(cacheName);
                    }
                })
            );
        })
    );
    self.clients.claim();
});

self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);
    
    // Skip non-GET requests
    if (request.method !== 'GET') {
        return;
    }
    
    // Handle my-planning pages
    if (url.pathname.startsWith('/my-planning')) {
        event.respondWith(handlePlanningPage(request));
        return;
    }
    
    // Handle offline planning API requests
    if (API_CACHE_PATTERNS.some(pattern => url.pathname.includes(pattern))) {
        event.respondWith(handleOfflineAPI(request));
        return;
    }
    
    // Handle static assets
    if (STATIC_ASSETS.some(asset => url.pathname === asset) || 
        url.pathname.includes('/css/') || 
        url.pathname.includes('/js/') ||
        url.pathname.includes('/images/')) {
        event.respondWith(handleStaticAsset(request));
        return;
    }
    
    // Handle other cacheable routes
    if (CACHEABLE_ROUTES.some(route => url.pathname.startsWith(route))) {
        event.respondWith(handleCacheableRoute(request));
        return;
    }
});

async function handlePlanningPage(request) {
    try {
        // Try network first
        const networkResponse = await fetch(request);
        if (networkResponse.ok) {
            // Cache the response
            const cache = await caches.open(CACHE_NAME);
            cache.put(request, networkResponse.clone());
            return networkResponse;
        }
        throw new Error('Network response not ok');
    } catch (error) {
        // Try cache
        const cachedResponse = await caches.match(request);
        if (cachedResponse) {
            return cachedResponse;
        }
        
        // If no cache, return offline page
        const offlineResponse = await caches.match('/offline.html');
        if (offlineResponse) {
            return offlineResponse;
        }
        
        // Fallback response
        return new Response('Offline - Deze pagina is niet beschikbaar zonder internet', {
            status: 503,
            headers: { 'Content-Type': 'text/html' }
        });
    }
}

async function handleOfflineAPI(request) {
    const cache = await caches.open(CACHE_NAME);
    
    try {
        // For offline API endpoints, try network first
        const networkResponse = await fetch(request);
        if (networkResponse.ok) {
            // Cache successful responses
            cache.put(request, networkResponse.clone());
            return networkResponse;
        }
        throw new Error('Network response not ok');
    } catch (error) {
        // Try cache
        const cachedResponse = await caches.match(request);
        if (cachedResponse) {
            return cachedResponse;
        }
        
        // Return error response for API calls
        return new Response(JSON.stringify({
            error: 'Offline',
            message: 'Deze functie is niet beschikbaar zonder internet'
        }), {
            status: 503,
            headers: { 'Content-Type': 'application/json' }
        });
    }
}

async function handleStaticAsset(request) {
    // Cache first strategy for static assets
    const cachedResponse = await caches.match(request);
    if (cachedResponse) {
        return cachedResponse;
    }
    
    try {
        const networkResponse = await fetch(request);
        if (networkResponse.ok) {
            const cache = await caches.open(STATIC_CACHE);
            cache.put(request, networkResponse.clone());
        }
        return networkResponse;
    } catch (error) {
        return new Response('Asset not available offline', { status: 404 });
    }
}

async function handleCacheableRoute(request) {
    try {
        // Network first for dynamic content
        const networkResponse = await fetch(request);
        if (networkResponse.ok) {
            const cache = await caches.open(CACHE_NAME);
            cache.put(request, networkResponse.clone());
            return networkResponse;
        }
        throw new Error('Network response not ok');
    } catch (error) {
        // Try cache
        const cachedResponse = await caches.match(request);
        if (cachedResponse) {
            return cachedResponse;
        }
        
        // Fallback to offline page
        const offlineResponse = await caches.match('/offline.html');
        return offlineResponse || new Response('Offline', { status: 503 });
    }
}

self.addEventListener('message', (event) => {
    if (event.data && event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
    
    if (event.data && event.data.type === 'CACHE_PLANNING_DATA') {
        // Handle manual caching requests
        const { planningId, url } = event.data;
        caches.open(CACHE_NAME).then(cache => {
            return fetch(url).then(response => {
                if (response.ok) {
                    cache.put(url, response);
                }
            });
        });
    }
});

// Handle sync events for background sync
self.addEventListener('sync', (event) => {
    if (event.tag === 'background-sync') {
        event.waitUntil(doBackgroundSync());
    }
});

async function doBackgroundSync() {
    const clients = await self.clients.matchAll();
    clients.forEach(client => {
        client.postMessage({
            type: 'BACKGROUND_SYNC_TRIGGERED'
        });
    });
} 