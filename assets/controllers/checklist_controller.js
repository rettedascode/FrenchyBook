import { Controller } from '@hotwired/stimulus';
import { isStandalone, safeGet, safeSet } from '../lib/pwa.js';

const DISMISS_KEY = 'frenchybook-onboarding-dismissed';
const APP_KEY = 'frenchybook-app-installed';

/*
 * „Deine ersten Schritte“: hakt „App installiert“ auf dem Gerät ab, zeigt den Fortschritt
 * und blendet die Karte aus, wenn alles erledigt ist oder man „Ausblenden“ tippt.
 */
export default class extends Controller {
    static targets = ['item', 'progress', 'bar'];

    connect() {
        // Einmal als App geöffnet → merken, damit es auch im Browser abgehakt bleibt
        if (isStandalone()) safeSet(APP_KEY, '1');
        const appDone = safeGet(APP_KEY) === '1';

        this.itemTargets.forEach((item) => {
            if (item.dataset.deviceCheck !== 'app' || !appDone) return;
            item.classList.add('is-done');
            // Link entfernen und Status für Screenreader anpassen
            const link = item.querySelector('.gs-text > a:not(.gs-help)');
            if (link) link.replaceWith(link.textContent);
            const state = item.querySelector('.gs-state');
            if (state) state.textContent = this.element.dataset.doneText;
        });

        const total = this.itemTargets.length;
        const done = this.itemTargets.filter((item) => item.classList.contains('is-done')).length;
        this.progressTarget.textContent = this.progressTarget.dataset.template
            .replace('__DONE__', done).replace('__TOTAL__', total);
        this.barTarget.style.width = `${Math.round((done / total) * 100)}%`;

        this.element.hidden = done === total || safeGet(DISMISS_KEY) === '1';
        // Solange die Checkliste da ist, kein zweites „App installieren“-Banner daneben
        document.documentElement.classList.toggle('onboarding-visible', !this.element.hidden);
    }

    disconnect() {
        document.documentElement.classList.remove('onboarding-visible');
    }

    dismiss() {
        safeSet(DISMISS_KEY, '1');
        this.element.hidden = true;
        document.documentElement.classList.remove('onboarding-visible');
    }
}
