/**
 * Progressive Web App Service Worker
 * Handles offline functionality, caching, and push notifications
 * Enables fast loading and offline-first experience
 */

const CACHE_NAME = 'side-quest-v1';
const OFFLINE_URL = './offline.html';

// URLs to cache on install
const urlsToCache = [
  './',
  './index.php',
  './manifest.json',
  './offline.html',
  './assets/images/favicon.png',
  './assets/images/icon-192.png',
  './assets/images/icon-512.png'
];

// Install event - cache essential assets
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME).then(cache => {
      return cache.addAll(urlsToCache).catch(() => {
        console.log('Initial cache failed - some resources unavailable');
        return Promise.resolve();
      });
    })
  );
  self.skipWaiting();
});

// Activate event - clean up old cache versions
self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(cacheNames => {
      return Promise.all(
        cacheNames.map(cacheName => {
          if (cacheName !== CACHE_NAME) {
            return caches.delete(cacheName);
          }
        })
      );
    })
  );
  self.clients.claim();
});

// Fetch event - network first for dynamic content, cache first for static
self.addEventListener('fetch', event => {
  const { request } = event;
  
  // Skip non-GET requests
  if (request.method !== 'GET') {
    return;
  }

  // Handle PHP files and API calls - network first
  if (request.url.includes('.php') || request.url.includes('/api/')) {
    event.respondWith(
      fetch(request)
        .then(response => {
          // Cache successful responses
          if (response && response.status === 200) {
            const responseClone = response.clone();
            caches.open(CACHE_NAME).then(cache => {
              cache.put(request, responseClone);
            });
          }
          return response;
        })
        .catch(() => {
          // Return offline page if network fails
          return caches.match(OFFLINE_URL);
        })
    );
    return;
  }

  // Handle static assets - cache first
  event.respondWith(
    caches.match(request)
      .then(response => {
        // Return cached response if available
        if (response) {
          return response;
        }

        // Try network for uncached assets
        return fetch(request).then(response => {
          // Don't cache non-successful responses
          if (!response || response.status !== 200) {
            return response;
          }

          // Cache successful responses
          const responseClone = response.clone();
          caches.open(CACHE_NAME).then(cache => {
            cache.put(request, responseClone);
          });
          return response;
        });
      })
      .catch(() => {
        // Return cached response or offline page on network failure
        return caches.match(request) || caches.match(OFFLINE_URL);
      })
  );
});

// Handle push notifications
self.addEventListener('push', event => {
  if (!event.data) {
    console.log('Push notification with no data');
    return;
  }

  try {
    const data = event.data.json();
    const options = {
      body: data.body || 'New notification',
      icon: data.icon || './assets/images/icon-192.png',
      badge: './assets/images/favicon.png',
      tag: data.tag || 'notification',
      requireInteraction: data.requireInteraction || false,
      actions: data.actions || [
        {
          action: 'open',
          title: 'Open'
        }
      ],
      data: data.data || {}
    };

    event.waitUntil(
      self.registration.showNotification(data.title || 'Side Quest', options)
    );
  } catch (e) {
    console.error('Error parsing push notification:', e);
    event.waitUntil(
      self.registration.showNotification('Side Quest', {
        body: event.data.text(),
        icon: './assets/images/icon-192.png'
      })
    );
  }
});

// Handle notification clicks
self.addEventListener('notificationclick', event => {
  event.notification.close();

  if (event.action === 'open' || !event.action) {
    event.waitUntil(
      clients.matchAll({ type: 'window', includeUncontrolled: true })
        .then(clientList => {
          // Check if app is already open
          for (let client of clientList) {
            if (client.url === '/' && 'focus' in client) {
              return client.focus();
            }
          }
          // Open app if not running
          if (clients.openWindow) {
            return clients.openWindow(event.notification.data.url || './index.php');
          }
        })
    );
  }
});

// Periodic background sync for game updates
self.addEventListener('sync', event => {
  if (event.tag === 'sync-games') {
    event.waitUntil(syncGameData());
  }
});

async function syncGameData() {
  try {
    const response = await fetch('./chess/api_professional.php?action=sync');
    return response.json();
  } catch (error) {
    console.log('Background sync failed:', error);
  }
}

    event.waitUntil(
      self.registration.showNotification('Side Quest Notification', {
        body: event.data.text()
      })
    );
  }
});

// Handle notification clicks
self.addEventListener('notificationclick', event => {
  event.notification.close();

  const urlToOpen = event.notification.data.url || '/boringlife/';
  
  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then(clientList => {
      // Check if window is already open
      for (let i = 0; i < clientList.length; i++) {
        const client = clientList[i];
        if (client.url === urlToOpen && 'focus' in client) {
          return client.focus();
        }
      }
      // Open new window if not found
      if (clients.openWindow) {
        return clients.openWindow(urlToOpen);
      }
    })
  );
});

// Handle notification close
self.addEventListener('notificationclose', event => {
  console.log('Notification closed:', event.notification.tag);
});
