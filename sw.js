/* WellCore v8 — Service Worker
   Estrategia: Cache-first para assets estaticos, Network-first para API.
   Cache se actualiza en background (stale-while-revalidate).
   M35: Push Notifications VAPID — handlers de push y notificationclick. */

var CACHE_NAME = 'wc-v8-1';
var STATIC_ASSETS = [
  '/css/wellcore-v5.css',
  '/css/wellcore-v6.css',
  '/css/wellcore-v7.css',
  '/css/wellcore-v8.css',
  '/js/api.js',
  '/js/wellcore-v6.js',
  '/js/wellcore-v7.js',
  '/js/wellcore-v8.js',
  '/images/logo/imagotipo-blanco.png'
];

self.addEventListener('install', function(event) {
  self.skipWaiting();
  event.waitUntil(
    caches.open(CACHE_NAME).then(function(cache) {
      return cache.addAll(STATIC_ASSETS);
    }).catch(function() {
      // Si algun asset no existe, no bloquear install
    })
  );
});

self.addEventListener('activate', function(event) {
  event.waitUntil(
    caches.keys().then(function(keys) {
      return Promise.all(
        keys.filter(function(k) { return k !== CACHE_NAME; })
            .map(function(k) { return caches.delete(k); })
      );
    }).then(function() {
      return self.clients.claim();
    })
  );
});

// ===== M35: Push Notifications =====
self.addEventListener('push', function(event) {
  var data = {};
  if (event.data) {
    try { data = event.data.json(); } catch(e) { data = { body: event.data.text() }; }
  }
  var title   = data.title || 'WellCore Fitness';
  var options = {
    body:  data.body  || 'Tienes una actualizacion en tu portal',
    icon:  '/images/icon-192.png',
    badge: '/images/icon-192.png',
    tag:   'wc-push',
    renotify: true,
    data:  { url: data.url || '/cliente.html' }
  };
  event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', function(event) {
  event.notification.close();
  var targetUrl = (event.notification.data && event.notification.data.url)
                ? event.notification.data.url
                : '/cliente.html';
  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function(list) {
      for (var i = 0; i < list.length; i++) {
        var c = list[i];
        if (c.url.indexOf(targetUrl) !== -1 && 'focus' in c) return c.focus();
      }
      if (clients.openWindow) return clients.openWindow(targetUrl);
    })
  );
});

self.addEventListener('fetch', function(event) {
  var url = event.request.url;

  // Network-only para API calls, auth y cross-origin
  if (url.includes('/api/') || url.includes('chrome-extension') || !url.startsWith('http')) {
    return;
  }
  // Network-only para peticiones POST/non-GET
  if (event.request.method !== 'GET') {
    return;
  }

  // Cache-first para assets estaticos (CSS, JS, imagenes)
  var isStatic = /\.(css|js|png|jpg|jpeg|webp|svg|woff2?|ico)(\?.*)?$/.test(url);
  if (isStatic) {
    event.respondWith(
      caches.match(event.request).then(function(cached) {
        if (cached) {
          // Actualizar cache en background
          fetch(event.request).then(function(response) {
            if (response && response.status === 200) {
              caches.open(CACHE_NAME).then(function(cache) {
                cache.put(event.request, response);
              });
            }
          }).catch(function() {});
          return cached;
        }
        return fetch(event.request).then(function(response) {
          if (response && response.status === 200) {
            var clone = response.clone();
            caches.open(CACHE_NAME).then(function(cache) { cache.put(event.request, clone); });
          }
          return response;
        });
      })
    );
    return;
  }

  // Network-first para HTML (siempre fresco)
  event.respondWith(
    fetch(event.request).catch(function() {
      return caches.match(event.request);
    })
  );
});
