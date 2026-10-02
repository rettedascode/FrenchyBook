import { Controller } from '@hotwired/stimulus';

/* Buchseite: Tippen aufs Cover dreht das Buch um (Vorderseite ↔ Rückseite). */
export default class extends Controller {
    static targets = ['inner', 'label'];
    static values = { front: String, back: String };

    toggle() {
        const flipped = this.element.classList.toggle('is-flipped');
        this.innerTarget.setAttribute('aria-pressed', String(flipped));
        this.labelTarget.textContent = flipped ? this.backValue : this.frontValue;
    }
}
