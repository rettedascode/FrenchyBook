import { Controller } from '@hotwired/stimulus';

/*
 * Einfaches, barrierearmes Dropdown-Menü (z. B. Sprachauswahl).
 * - Öffnen/Schließen per Klick, Enter oder Leertaste
 * - Schließt bei Klick daneben und mit Escape (Fokus zurück auf den Button)
 * - Pfeiltasten, Pos1 und Ende bewegen den Fokus im Menü
 */
export default class extends Controller {
    static targets = ['button', 'menu', 'item'];

    connect() {
        this.onOutsideClick = (event) => {
            if (!this.element.contains(event.target)) this.close();
        };
    }

    disconnect() {
        document.removeEventListener('click', this.onOutsideClick);
    }

    get isOpen() {
        return !this.menuTarget.hidden;
    }

    toggle() {
        this.isOpen ? this.close() : this.open();
    }

    open(focusIndex = null) {
        this.menuTarget.hidden = false;
        this.buttonTarget.setAttribute('aria-expanded', 'true');
        document.addEventListener('click', this.onOutsideClick);
        const current = this.itemTargets.findIndex((item) => item.getAttribute('aria-current') === 'true');
        this.focusItem(focusIndex ?? Math.max(0, current));
    }

    close(returnFocus = false) {
        if (!this.isOpen) return;
        this.menuTarget.hidden = true;
        this.buttonTarget.setAttribute('aria-expanded', 'false');
        document.removeEventListener('click', this.onOutsideClick);
        if (returnFocus) this.buttonTarget.focus();
    }

    keydown(event) {
        const items = this.itemTargets;
        const index = items.indexOf(document.activeElement);

        switch (event.key) {
            case 'Escape':
                if (this.isOpen) {
                    event.preventDefault();
                    this.close(true);
                }
                break;
            case 'ArrowDown':
                event.preventDefault();
                this.isOpen ? this.focusItem((index + 1) % items.length) : this.open(0);
                break;
            case 'ArrowUp':
                event.preventDefault();
                this.isOpen ? this.focusItem((index - 1 + items.length) % items.length) : this.open(items.length - 1);
                break;
            case 'Home':
                if (this.isOpen) { event.preventDefault(); this.focusItem(0); }
                break;
            case 'End':
                if (this.isOpen) { event.preventDefault(); this.focusItem(items.length - 1); }
                break;
            case 'Tab':
                this.close();
                break;
        }
    }

    focusItem(index) {
        this.itemTargets[index]?.focus();
    }
}
