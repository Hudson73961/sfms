/* Smart Farmer service worker — lets pages open without internet.
   Data changes made offline are queued by app.js and synced later. */
const V = 'v2';
const STATIC = 'sf-static-' + V, PAGES = 'sf-pages-' + V;
const SCOPE = self.registration.scope;
const SCOPE_PATH = new URL(SCOPE).pathname;
const P = p => new URL(p, SCOPE).href;
const PRECACHE = ['offline.html', 'manifest.json', 'assets/css/style.css', 'assets/js/app.js', 'assets/js/messages.js',
  'assets/js/crops.js', 'assets/js/logout.js', 'assets/img/icon.svg', 'assets/img/field-banner.jpg',
  'assets/img/market-produce.jpg', 'assets/img/livestock.jpg'].map(P);
const NO_CACHE_PAGES = ['login.php', 'register.php', 'logout.php', 'change-password.php'];

self.addEventListener('install', e => {
  e.waitUntil(caches.open(STATIC).then(c => c.addAll(PRECACHE)).then(() => self.skipWaiting()));
});
self.addEventListener('activate', e => {
  e.waitUntil(caches.keys()
    .then(ks => Promise.all(ks.filter(k => k !== STATIC && k !== PAGES).map(k => caches.delete(k))))
    .then(() => self.clients.claim()));
});

self.addEventListener('message', e => {
  const d = e.data || {};
  if (d.type === 'clear') e.waitUntil(caches.delete(PAGES));
  if (d.type === 'prefetch' && Array.isArray(d.urls)) {
    e.waitUntil(caches.open(PAGES).then(cache => Promise.all(d.urls.map(async u => {
      try {
        const res = await fetch(u, { credentials: 'same-origin' });
        if (res.ok && !res.redirected) await cache.put(u, res);
      } catch { /* offline — ignore */ }
    }))));
  }
});

self.addEventListener('fetch', e => {
  const req = e.request;
  if (req.method !== 'GET') return;
  const u = new URL(req.url);
  if (u.origin !== location.origin) return;
  const rel = u.pathname.startsWith(SCOPE_PATH) ? u.pathname.slice(SCOPE_PATH.length) : u.pathname;
  if (rel.startsWith('api/')) return;                       // live data: network only (the page handles offline itself)

  if (req.mode === 'navigate') { e.respondWith(pageStrategy(req, rel)); return; }
  if (rel.startsWith('assets/') || rel === 'manifest.json') e.respondWith(staleWhileRevalidate(req));
});

async function pageStrategy(req, rel) {
  const cache = await caches.open(PAGES);
  try {
    const res = await fetch(req);
    if (res.ok && res.type === 'basic' && !res.redirected && !NO_CACHE_PAGES.includes(rel)) cache.put(req, res.clone());
    return res;
  } catch {
    const hit = await cache.match(req, { ignoreSearch: false });
    if (hit) return hit;
    const off = await caches.match(P('offline.html'));
    return off || new Response('Offline', { status: 503 });
  }
}
async function staleWhileRevalidate(req) {
  const cache = await caches.open(STATIC);
  const hit = await cache.match(req);
  const net = fetch(req).then(res => { if (res.ok) cache.put(req, res.clone()); return res; }).catch(() => null);
  return hit || (await net) || new Response('', { status: 504 });
}
