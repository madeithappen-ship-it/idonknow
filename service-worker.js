/**
 * Service Worker for Push Notifications
 * Handles background notifications and offline functionality
 */

const CACHE_NAME = 'side-quest-v1';
const urlsToCache = [
  '/boringlife/',
  '/boringlife/index.php',
  '/boringlife/assets/css/style.css',
  '/boringlife/assets/js/cookies.js'
];

// Install event - cache assets
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME).then(cache => {
      return cache.addAll(urlsToCache).catch(() => {
        // Not all URLs may be available, that's okay
        return Promise.resolve();
      });
    })
  );
  self.skipWaiting();
});

// Activate event - clean up old caches
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

// Fetch event - serve from cache, fallback to network
self.addEventListener('fetch', event => {
  if (event.request.method !== 'GET') {
    return;
  }

  event.respondWith(
    caches.match(event.request).then(response => {
      if (response) {
        return response;
      }
      return fetch(event.request).then(response => {
        if (!response || response.status !== 200 || response.type === 'basic') {
          return response;
        }
        const responseToCache = response.clone();
        caches.open(CACHE_NAME).then(cache => {
          cache.put(event.request, responseToCache);
        });
        return response;
      });
    })
    .catch(() => {
      // Return offline page if available
      return caches.match('/boringlife/index.php');
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
      icon: data.icon || '/boringlife/assets/images/icon.svg',
      badge: '/boringlife/assets/images/badge.svg',
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
