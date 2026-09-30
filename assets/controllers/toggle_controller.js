import { Controller } from '@hotwired/stimulus';

/* Klappt einen Bereich auf und zu (z. B. das Filter-Panel) – barrierearm mit aria-expanded. */
export default class extends Controller {
    static targets = ['button', 'panel'];

    toggle() {
        const open = this.panelTarget.hidden;
        this.panelTarget.hidden = !open;
        this.buttonTarget.setAttribute('aria-expanded', String(open));
    }
}
