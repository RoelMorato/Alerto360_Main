const CACHE_NAME = 'alerto360-v1';
const urlsToCache = [
  '/alerto360/',
  '/alerto360/login.php',
  '/alerto360/user_dashboard.php',
  '/alerto360/responder_dashboard.php',
  '/alerto360/admin_dashboard.php',
  '/alerto360/style.css',
  'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css',
  'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js',
  'https://unpkg.com/leaflet/dist/leaflet.css',
  'https://unpkg.com/leaflet/dist/leaflet.js',
  'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css'
];

self.addEventListener('install', function(event) {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(function(cache) {
        return cache.addAll(urlsToCache);
      })
  );
});

self.addEventListener('fetch', function(event) {
  event.respondWith(
    caches.match(event.request)
      .then(function(response) {
        if (response) {
          return response;
        }
        return fetch(event.request);
      }
    )
  );
});

// Background sync for offline incident reports
self.addEventListener('sync', function(event) {
  if (event.tag === 'incident-sync') {
    event.waitUntil(syncIncidents());
  }
});

function syncIncidents() {
  // Sync offline incident reports when connection is restored
  return new Promise((resolve) => {
    // Implementation for syncing offline data
    resolve();
  });
}
