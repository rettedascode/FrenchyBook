import './stimulus_bootstrap.js';
import * as Turbo from '@hotwired/turbo';
import './styles/app.css';

/*
 * Bestätigungsdialog: Formulare mit data-turbo-confirm="…" öffnen statt des
 * Browser-Popups unseren eigenen, gut bedienbaren <dialog>.
 * Standard ist ein roter „Löschen“-Knopf; mit data-confirm-button="Text" wird
 * daraus ein normaler Knopf mit eigener Beschriftung (z. B. „Neuer Code“).
 */
Turbo.config.forms.confirm = (message, element) => {
    const dialog = document.getElementById('confirm-dialog');
    if (!dialog || typeof dialog.showModal !== 'function') {
        return Promise.resolve(window.confirm(message));
    }
    document.getElementById('confirm-dialog-message').textContent = message;
    const button = dialog.querySelector('button[value=confirm]');
    button.dataset.defaultHtml ??= button.innerHTML;
    const label = element?.dataset?.confirmButton;
    if (label) {
        button.textContent = label;
        button.className = 'btn btn-primary';
    } else {
        button.innerHTML = button.dataset.defaultHtml;
        button.className = 'btn btn-danger';
    }
    dialog.returnValue = '';
    dialog.showModal();

    return new Promise((resolve) => {
        dialog.addEventListener('close', () => resolve(dialog.returnValue === 'confirm'), { once: true });
    });
};

// ---------- App (PWA) ----------

// Service Worker: Offline-Seite, schneller Start, Push-Benachrichtigungen
if ('serviceWorker' in navigator && window.isSecureContext) {
    navigator.serviceWorker.register('/sw.js').catch(() => { /* ohne SW läuft die Seite normal weiter */ });
}

// Das Installations-Angebot des Browsers (Android/Chrome/Edge) kommt oft, bevor die Seite
// fertig ist – wir merken es uns, bis ein „App installieren“-Button es braucht.
window.addEventListener('beforeinstallprompt', (event) => {
    event.preventDefault();
    window.frenchyInstallPrompt = event;
    document.dispatchEvent(new CustomEvent('pwa:installable'));
});
window.addEventListener('appinstalled', () => {
    window.frenchyInstallPrompt = null;
    document.dispatchEvent(new CustomEvent('pwa:installed'));
});

// Nach einem Seitenwechsel den Fokus für Screenreader/Tastatur sinnvoll setzen,
// ohne die Scroll-Position zu stören.
document.addEventListener('turbo:load', () => {
    const autofocus = document.querySelector('[data-autofocus]');
    if (autofocus && window.matchMedia('(min-width: 800px)').matches) {
        autofocus.focus({ preventScroll: true });
    }
});
