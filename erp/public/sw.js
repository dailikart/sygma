/* Sygma Solar Suite service worker: lets the CRM install as an app and shows a friendly page when offline.
   Only the app's own scripts, styles and icons are cached – never customer data or pages. */
const CACHE = 'sygma-v2';
const OFFLINE = 'offline.html';
const PRECACHE = [OFFLINE, 'assets/vendor/bootstrap/css/bootstrap.min.css', 'assets/vendor/bootstrap-icons/bootstrap-icons.min.css', 'favicon.png', 'logo-sm.png'];

self.addEventListener('install', (e) => {
  e.waitUntil(caches.open(CACHE).then((c) => c.addAll(PRECACHE.map((p) => new URL(p, self.registration.scope).href))).then(() => self.skipWaiting()));
});
self.addEventListener('activate', (e) => {
  e.waitUntil(caches.keys().then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k)))).then(() => self.clients.claim()));
});
self.addEventListener('fetch', (e) => {
  const req = e.request;
  if (req.method !== 'GET' || new URL(req.url).origin !== self.location.origin) return;
  if (req.mode === 'navigate') {
    // Pages always come from the server (fresh data); offline → friendly page.
    e.respondWith(fetch(req).catch(() => caches.match(new URL(OFFLINE, self.registration.scope).href)));
    return;
  }
  if (/\/assets\/|\/favicon\.(svg|png|ico)|\/(logo(-sm)?|wordmark-light)\.png|\/apple-touch-icon\.png|\/app-icon\//.test(req.url)) {
    e.respondWith(caches.match(req).then((hit) => hit || fetch(req).then((res) => {
      if (res.ok) { const copy = res.clone(); caches.open(CACHE).then((c) => c.put(req, copy)); }
      return res;
    })));
  }
});
