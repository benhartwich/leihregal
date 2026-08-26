const CACHE = 'app-shell-v2';
const APP_SHELL = [
    '/',
    '/dashboard',
    '/medien',
    '/ausleihen',
    '/assistent',
];

self.addEventListener('install', e => {
    e.waitUntil(
        caches.open(CACHE).then(c => c.addAll(APP_SHELL)).then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', e => {
    e.waitUntil(
        caches.keys().then(keys =>
            Promise.all(keys.filter(k => k !== CACHE).map(k => caches.delete(k)))
        ).then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', e => {
    // Only cache GET requests for same-origin pages; pass through Livewire/API requests
    const url = new URL(e.request.url);
    if (e.request.method !== 'GET' || url.pathname.startsWith('/livewire')) {
        return;
    }

    e.respondWith(
        fetch(e.request)
            .then(res => {
                if (res.ok && url.origin === self.location.origin) {
                    const clone = res.clone();
                    caches.open(CACHE).then(c => c.put(e.request, clone));
                }
                return res;
            })
            .catch(() => caches.match(e.request).then(r => r || caches.match('/dashboard')))
    );
});


// ── Web-Push (Phase 8) ──────────────────────────────────────────────────────

self.addEventListener('push', e => {
    if (!e.data) return;

    let daten;
    try {
        daten = e.data.json();
    } catch (_) {
        daten = { title: '', body: e.data.text() };
    }

    e.waitUntil(
        self.registration.showNotification(daten.title || 'Neue Nachricht', {
            body: daten.body || '',
            icon: daten.icon || '/icon-192.png',
            badge: daten.badge || '/icon-192.png',
            // Gleiches tag ersetzt eine noch offene Meldung derselben Art,
            // statt den Sperrbildschirm zuzustapeln.
            tag: daten.tag || 'allgemein',
            data: { url: daten.url || '/dashboard' },
        })
    );
});

self.addEventListener('notificationclick', e => {
    e.notification.close();
    const ziel = (e.notification.data && e.notification.data.url) || '/dashboard';

    e.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then(offene => {
            // Ist die App schon offen, dorthin wechseln statt einen
            // weiteren Tab aufzumachen.
            for (const client of offene) {
                if (client.url.includes(ziel) && 'focus' in client) return client.focus();
            }
            if (clients.openWindow) return clients.openWindow(ziel);
        })
    );
});