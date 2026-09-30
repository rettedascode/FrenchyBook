import { Controller } from '@hotwired/stimulus';

const MAX_BYTES = 5 * 1024 * 1024;
const MAX_EDGE = 1800;           // längste Kante nach dem Verkleinern
const RESIZE_ABOVE = 1.5 * 1024 * 1024;
const ALLOWED = ['image/jpeg', 'image/png', 'image/webp'];

/*
 * Cover-Vorschau im Formular.
 * - Zeigt das gewählte Foto sofort an.
 * - Verkleinert große Handyfotos schon im Browser (schneller Upload, keine 5-MB-Grenze).
 * - Fällt das Open-Library-Cover weg (404), wird der Platzhalter gezeigt.
 */
export default class extends Controller {
    static targets = ['input', 'frame', 'label', 'hint', 'error', 'remoteUrl'];
    // Übersetzte Texte kommen aus dem Template (data-cover-preview-messages-value)
    static values = { messages: Object };

    /** Öffnet am Handy direkt die (Rück-)Kamera. */
    takePhoto() {
        this.inputTarget.setAttribute('capture', 'environment');
        this.inputTarget.click();
    }

    /** Öffnet die normale Auswahl (Galerie bzw. Dateien am Computer). */
    pickFromGallery() {
        this.inputTarget.removeAttribute('capture');
        this.inputTarget.click();
    }

    async preview() {
        const file = this.inputTarget.files?.[0];
        this.clearError();
        if (!file) {
            // Auswahl abgebrochen – manche Browser leeren dabei das Feld. Zuletzt gewähltes Foto behalten.
            this.restoreSelection();
            return;
        }

        if (!ALLOWED.includes(file.type)) {
            this.showError(this.messagesValue.format);
            this.restoreSelection();
            return;
        }

        let finalFile = file;
        if (file.size > RESIZE_ABOVE) {
            this.hintTarget.textContent = this.messagesValue.preparing;
            try {
                finalFile = await this.downscale(file);
                const transfer = new DataTransfer();
                transfer.items.add(finalFile);
                this.inputTarget.files = transfer.files;
            } catch (e) {
                finalFile = file; // Dann eben das Original – der Server verkleinert ohnehin.
            }
        }

        if (finalFile.size > MAX_BYTES) {
            this.showError(this.messagesValue.tooLarge);
            this.restoreSelection();
            return;
        }
        this.selectedFile = finalFile;

        // Ein eigenes Foto ersetzt den Open-Library-Vorschlag
        if (this.hasRemoteUrlTarget) {
            this.remoteUrlTarget.value = '';
        }

        const url = URL.createObjectURL(finalFile);
        this.frameTarget.innerHTML = '';
        const cover = document.createElement('div');
        cover.className = 'cover';
        const img = document.createElement('img');
        img.src = url;
        img.alt = this.messagesValue.previewAlt;
        img.onload = () => URL.revokeObjectURL(url);
        cover.appendChild(img);
        this.frameTarget.appendChild(cover);

        this.labelTarget.textContent = this.messagesValue.otherPhoto;
        this.hintTarget.textContent = this.messagesValue.willUpload;
    }

    /** Setzt das zuletzt gültige Foto wieder ins Feld (oder leert es, falls es keins gab). */
    restoreSelection() {
        if (!this.selectedFile) {
            this.inputTarget.value = '';
            return;
        }
        const transfer = new DataTransfer();
        transfer.items.add(this.selectedFile);
        this.inputTarget.files = transfer.files;
    }

    remoteFailed() {
        if (this.hasRemoteUrlTarget) {
            this.remoteUrlTarget.value = '';
        }
        this.frameTarget.innerHTML = '<div class="cover cover-placeholder" aria-hidden="true" style="--ph:#5a5f69"><span class="ph-title"></span></div>';
        this.frameTarget.querySelector('.ph-title').textContent = this.messagesValue.notFound;
        this.labelTarget.textContent = this.messagesValue.takePhoto;
        this.hintTarget.textContent = this.messagesValue.notFoundHint;
    }

    async downscale(file) {
        const bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' });
        const scale = Math.min(1, MAX_EDGE / Math.max(bitmap.width, bitmap.height));
        const canvas = document.createElement('canvas');
        canvas.width = Math.round(bitmap.width * scale);
        canvas.height = Math.round(bitmap.height * scale);
        canvas.getContext('2d').drawImage(bitmap, 0, 0, canvas.width, canvas.height);
        bitmap.close?.();

        const blob = await new Promise((resolve, reject) =>
            canvas.toBlob((b) => (b ? resolve(b) : reject(new Error('toBlob'))), 'image/jpeg', 0.86));
        const name = file.name.replace(/\.[^.]+$/, '') + '.jpg';

        return new File([blob], name, { type: 'image/jpeg', lastModified: Date.now() });
    }

    showError(message) {
        this.errorTarget.innerHTML = '';
        const li = document.createElement('li');
        li.textContent = message;
        this.errorTarget.appendChild(li);
        this.errorTarget.hidden = false;
        this.element.classList.add('has-error');
    }

    clearError() {
        this.errorTarget.hidden = true;
        this.element.classList.remove('has-error');
    }
}
