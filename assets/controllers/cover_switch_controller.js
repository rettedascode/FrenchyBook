import { Controller } from '@hotwired/stimulus';

/* Buchseite: zwischen Vorder- und Rückseite des Buchs umschalten. */
export default class extends Controller {
    static targets = ['panel', 'button'];

    show({ params: { index } }) {
        this.panelTargets.forEach((panel, i) => { panel.hidden = i !== index; });
        this.buttonTargets.forEach((button, i) => {
            button.classList.toggle('is-active', i === index);
            button.setAttribute('aria-pressed', String(i === index));
        });
    }
}
