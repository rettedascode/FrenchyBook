import { Controller } from '@hotwired/stimulus';
import { currentTheme, setTheme } from '../lib/theme.js';

/* Profil: Auswahl Automatisch / Hell / Dunkel – wirkt sofort. */
export default class extends Controller {
    connect() {
        const input = this.element.querySelector(`input[value="${currentTheme()}"]`);
        if (input) input.checked = true;
    }

    change(event) {
        setTheme(event.target.value);
    }
}
