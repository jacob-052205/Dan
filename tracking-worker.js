// Service Worker for Background Tracking
const CACHE_NAME = 'wastewise-tracking-v1';
const TRACKING_API = 'live_tracking_api.php';

self.addEventListener('install', (event) => {
    console.log('Tracking Service Worker installed');
    event.waitUntil(self.skipWaiting());
});

self.addEventListener('activate', (event) => {
    console.log('Tracking Service Worker activated');
    event.waitUntil(self.clients.claim());
});

self.addEventListener('fetch', (event) => {
    // Handle tracking API requests
    if (event.request.url.includes(TRACKING_API)) {
        event.respondWith(
            fetch(event.request)
                .then(response => {
                    // Clone the response to store in cache
                    const responseClone = response.clone();
                    caches.open(CACHE_NAME).then(cache => {
                        cache.put(event.request, responseClone);
                    });
                    return response;
                })
                .catch(() => {
                    // If network fails, try cache
                    return caches.match(event.request);
                })
        );
    }
});

// Background sync for tracking updates
self.addEventListener('sync', (event) => {
    if (event.tag === 'tracking-update') {
        console.log('Background sync: Updating tracking data');
        event.waitUntil(updateTrackingData());
    }
});

async function updateTrackingData() {
    try {
        const response = await fetch(TRACKING_API + '?action=get_locations');
        const data = await response.json();
        
        // Send data to all clients
        const clients = await self.clients.matchAll();
        clients.forEach(client => {
            client.postMessage({
                type: 'TRACKING_UPDATE',
                data: data,
                timestamp: Date.now()
            });
        });
        
        // Show notification for completed collections
        if (data.reports) {
            data.reports.forEach(report => {
                if (report.status === 'collected') {
                    self.registration.showNotification('Collection Completed', {
                        body: 'Your waste has been collected successfully!',
                        icon: '/favicon.ico',
                        badge: '/favicon.ico'
                    });
                }
            });
        }
        
        return data;
    } catch (error) {
        console.error('Background sync failed:', error);
    }
}

// Periodic background updates
setInterval(() => {
    updateTrackingData();
}, 30000); // Every 30 seconds