/* Kleine Hilfsfunktionen rund um die installierte App (PWA). */

/** Läuft FrenchyBook gerade als installierte App (ohne Browserleiste)? */
export const isStandalone = () =>
    window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;

/** iPhone oder iPad (auch iPadOS, das sich als Mac ausgibt)? */
export const isIos = () =>
    /iPad|iPhone|iPod/.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);

/** Kurze Gerätebeschreibung für die Übersicht der Push-Geräte, z. B. „Android · Chrome“. */
export function deviceLabel() {
    const ua = navigator.userAgent;
    const os = isIos() ? 'iOS'
        : /Android/.test(ua) ? 'Android'
        : /Windows/.test(ua) ? 'Windows'
        : /Mac OS X/.test(ua) ? 'macOS'
        : /Linux/.test(ua) ? 'Linux' : '';
    const browser = /SamsungBrowser/.test(ua) ? 'Samsung Internet'
        : /Edg\//.test(ua) ? 'Edge'
        : /Firefox\//.test(ua) ? 'Firefox'
        : /CriOS|Chrome\//.test(ua) ? 'Chrome'
        : /Safari\//.test(ua) ? 'Safari' : '';
    return [os, browser].filter(Boolean).join(' · ') + (isStandalone() ? ' (App)' : '');
}

/** VAPID-Schlüssel (Base64URL) → Uint8Array für pushManager.subscribe() */
export function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const raw = window.atob(base64);
    return Uint8Array.from([...raw].map((c) => c.charCodeAt(0)));
}

export function safeGet(key) {
    try { return window.localStorage.getItem(key); } catch { return null; }
}

export function safeSet(key, value) {
    try { window.localStorage.setItem(key, value); } catch { /* privater Modus – egal */ }
}
