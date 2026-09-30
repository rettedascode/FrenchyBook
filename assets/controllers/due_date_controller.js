import { Controller } from '@hotwired/stimulus';

/* Schnellauswahl fürs Rückgabedatum: „2 Wochen“, „4 Wochen“, „Kein Datum“. */
export default class extends Controller {
    static targets = ['input'];

    set({ params: { days } }) {
        if (!days) {
            this.inputTarget.value = '';
            return;
        }
        const date = new Date();
        date.setDate(date.getDate() + days);
        const pad = (n) => String(n).padStart(2, '0');
        this.inputTarget.value = `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
    }
}
