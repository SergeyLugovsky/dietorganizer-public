// public_html/sw.js
self.addEventListener('install', () => {
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) => Promise.all(keys.map((key) => caches.delete(key)))).then(() => self.clients.claim())
  );
});

self.addEventListener('push', (event) => {
  let data = {
    title: 'Meal reminder',
    body: 'Time for your meal',
    url: '/diary'
  };

  if (event.data) {
    try {
      const payload = event.data.json();
      data = {
        title: payload.title || data.title,
        body: payload.body || data.body,
        url: payload.url || data.url
      };
    } catch (e) {
      // ignore invalid payload
    }
  }

  const options = {
    body: data.body,
    icon: '/icons/icon-512-notification.png',
    badge: '/icons/icon-512-notification.png',
    data: { url: data.url }
  };

  event.waitUntil(self.registration.showNotification(data.title, options));
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const targetUrl = (event.notification.data && event.notification.data.url) || '/';
  const absoluteUrl = new URL(targetUrl, self.location.origin).href;

  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
      for (const client of clientList) {
        if (client.url === absoluteUrl && 'focus' in client) {
          return client.focus();
        }
      }
      if (clients.openWindow) {
        return clients.openWindow(absoluteUrl);
      }
      return null;
    })
  );
});
