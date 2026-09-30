import { Controller } from '@hotwired/stimulus';

import { isIos, isStandalone, safeGet, safeSet } from '../lib/pwa.js';

const DISMISS_KEY = 'frenchybook-install-dismissed';

/*
 * „FrenchyBook als App installieren“
 * - Android/Chrome/Edge: Button, der den Installationsdialog des Browsers öffnet
 * - iPhone/iPad: Kurzanleitung (Apple erlaubt keinen Installations-Button)
 * - Schon installiert: Hinweis bzw. (als Banner) gar nichts
 *
 * Mit data-pwa-install-banner-value="true" ist es ein wegklickbares Banner, das nur erscheint,
 * wenn eine Installation wirklich möglich ist.
 */
export default class extends Controller {
    static targets = ['android', 'ios', 'installed', 'other'];
    static values = { banner: Boolean };

    connect() {
        this.onInstallable = () => this.render();
        this.onInstalled = () => this.render();
        document.addEventListener('pwa:installable', this.onInstallable);
        document.addEventListener('pwa:installed', this.onInstalled);
        this.render();
    }

    disconnect() {
        document.removeEventListener('pwa:installable', this.onInstallable);
        document.removeEventListener('pwa:installed', this.onInstalled);
    }

    render() {
        let state = 'other';
        if (isStandalone()) state = 'installed';
        else if (window.frenchyInstallPrompt) state = 'android';
        else if (isIos()) state = 'ios';

        if (this.bannerValue) {
            const dismissed = safeGet(DISMISS_KEY) === '1';
            this.element.hidden = dismissed || !['android', 'ios'].includes(state);
        } else {
            this.element.hidden = false;
        }

        for (const name of ['android', 'ios', 'installed', 'other']) {
            const has = this[`has${name[0].toUpperCase()}${name.slice(1)}Target`];
            if (has) this[`${name}Target`].hidden = state !== name;
        }
    }

    async install() {
        const prompt = window.frenchyInstallPrompt;
        if (!prompt) return;
        prompt.prompt();
        const { outcome } = await prompt.userChoice;
        window.frenchyInstallPrompt = null;
        if (outcome === 'accepted' && this.bannerValue) {
            this.element.hidden = true;
        }
        this.render();
    }

    dismiss() {
        safeSet(DISMISS_KEY, '1');
        this.element.hidden = true;
    }
}
