import { Controller } from '@hotwired/stimulus';

/*
 * Schickt das Filterformular automatisch ab – beim Tippen mit kurzer Verzögerung,
 * bei Chips und Auswahlfeldern sofort. Turbo lädt dann nur die Trefferliste neu.
 */
export default class extends Controller {
    static targets = ['form'];
    static values = { delay: { type: Number, default: 350 } };

    connect() {
        // Leere Felder und Standardwerte nicht in die URL schreiben (/buecher?q=tolkien statt ?q=tolkien&status=&genre=…)
        this.cleanUrl = (event) => {
            for (const [key, value] of [...event.formData.entries()]) {
                if (value === '' || (key === 'sort' && value === 'new')) event.formData.delete(key);
            }
        };
        this.form?.addEventListener('formdata', this.cleanUrl);
    }

    get form() {
        return this.hasFormTarget ? this.formTarget : this.element.closest('form');
    }

    search() {
        clearTimeout(this.timer);
        this.timer = setTimeout(() => this.form.requestSubmit(), this.delayValue);
    }

    submit(event) {
        // Das Suchfeld löst beim Verlassen „change“ aus – das ist schon per „input“ erledigt.
        if (event?.target?.type === 'search') {
            return;
        }
        clearTimeout(this.timer);
        this.form.requestSubmit();
    }

    disconnect() {
        clearTimeout(this.timer);
        this.form?.removeEventListener('formdata', this.cleanUrl);
    }
}
