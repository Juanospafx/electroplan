const CACHE_NAME = 'electroplan-pwa-v1';
const APP_SHELL = [
  '/electroplan/offline.html',
  '/electroplan/assets/pwa-icon.svg',
  '/electroplan/assets/pwa-icon-192.png',
  '/electroplan/assets/pwa-icon-512.png',
  '/electroplan/assets/logo-text.png'
];

self.addEventListener('install', (event) => {
  event.waitUntil((async () => {
    const cache = await caches.open(CACHE_NAME);
    await Promise.allSettled(APP_SHELL.map((url) => cache.add(url)));
    await self.skipWaiting();
  })());
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  const { request } = event;
  if (request.method !== 'GET') return;

  const url = new URL(request.url);
  // NO interceptar PDFs ni el servidor externo
  if (
    url.pathname.endsWith('.pdf') ||
    url.hostname === 'androidelectro.brightronix.net'
  ) {
    return; // Dejar que el navegador lo maneje directamente
  }
  if (url.origin !== self.location.origin) return;
  if (url.pathname.startsWith('/api/') || url.pathname.startsWith('/uploads/')) return;

  if (request.mode === 'navigate') {
    event.respondWith(
      fetch(request).catch(() => caches.match('/electroplan/offline.html'))
    );
    return;
  }

  event.respondWith(
    caches.match(request).then((cached) => {
      if (cached) return cached;
      return fetch(request).then((response) => {
        const copy = response.clone();
        if (response.ok) {
          caches.open(CACHE_NAME).then((cache) => cache.put(request, copy));
        }
        return response;
      });
    })
  );
});
