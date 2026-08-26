/**
 * Web-Push: Gerät an- und abmelden (Phase 8).
 *
 * Der Service Worker wird bereits im Layout registriert; hier geht es nur um
 * das Abo beim Push-Dienst des Browsers und dessen Abgleich mit dem Server.
 */

/** VAPID-Public-Key liegt als Base64url vor, die Browser-API will ein Uint8Array. */
function base64UrlZuUint8Array(base64Url) {
    const padding = '='.repeat((4 - (base64Url.length % 4)) % 4);
    const base64  = (base64Url + padding).replace(/-/g, '+').replace(/_/g, '/');
    const roh     = window.atob(base64);

    return Uint8Array.from([...roh].map((z) => z.charCodeAt(0)));
}

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

export const push = {
    /** Unterstützt der Browser überhaupt Web-Push? */
    verfuegbar() {
        return 'serviceWorker' in navigator
            && 'PushManager' in window
            && 'Notification' in window;
    },

    /** 'granted' | 'denied' | 'default' | 'nicht-verfuegbar' */
    status() {
        if (!this.verfuegbar()) return 'nicht-verfuegbar';
        return Notification.permission;
    },

    async istAngemeldet() {
        if (!this.verfuegbar()) return false;
        const reg = await navigator.serviceWorker.ready;
        return (await reg.pushManager.getSubscription()) !== null;
    },

    async anmelden(vapidPublicKey) {
        if (!this.verfuegbar()) {
            throw new Error('Dieser Browser unterstützt keine Push-Benachrichtigungen.');
        }

        const erlaubnis = await Notification.requestPermission();
        if (erlaubnis !== 'granted') {
            throw new Error('Sie haben Benachrichtigungen abgelehnt. Bitte in den Browser-Einstellungen erlauben.');
        }

        const reg = await navigator.serviceWorker.ready;

        // Ein bestehendes Abo wiederverwenden – ein zweites Mal subscribe()
        // mit anderem Schlüssel würde der Browser ablehnen.
        let abo = await reg.pushManager.getSubscription();

        if (!abo) {
            abo = await reg.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: base64UrlZuUint8Array(vapidPublicKey),
            });
        }

        const daten = abo.toJSON();

        const antwort = await fetch('/push/abo', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                endpoint:  abo.endpoint,
                publicKey: daten.keys.p256dh,
                authToken: daten.keys.auth,
            }),
        });

        if (!antwort.ok) {
            throw new Error('Das Abo konnte nicht gespeichert werden.');
        }

        return true;
    },

    async abmelden() {
        if (!this.verfuegbar()) return false;

        const reg = await navigator.serviceWorker.ready;
        const abo = await reg.pushManager.getSubscription();

        if (!abo) return false;

        // Erst beim Server abmelden, dann lokal – andernfalls bliebe bei
        // einem Netzfehler ein Abo zurück, an das nie wieder etwas ankommt.
        await fetch('/push/abo', {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'Accept': 'application/json',
            },
            body: JSON.stringify({ endpoint: abo.endpoint }),
        });

        await abo.unsubscribe();

        return true;
    },
};

window.appPush = push;
