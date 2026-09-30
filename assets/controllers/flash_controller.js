import { Controller } from '@hotwired/stimulus';

/* Blendet Flash-Meldungen nach ein paar Sekunden sanft aus. */
export default class extends Controller {
    static values = { timeout: { type: Number, default: 5000 } };

    connect() {
        this.timer = setTimeout(() => this.close(), this.timeoutValue);
        this.element.addEventListener('mouseenter', () => clearTimeout(this.timer));
        this.element.addEventListener('focusin', () => clearTimeout(this.timer));
    }

    disconnect() {
        clearTimeout(this.timer);
    }

    close() {
        this.element.classList.add('is-leaving');
        this.element.addEventListener('animationend', () => this.element.remove(), { once: true });
        setTimeout(() => this.element.remove(), 400);
    }
}
