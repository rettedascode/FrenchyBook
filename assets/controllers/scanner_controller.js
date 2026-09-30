import { Controller } from '@hotwired/stimulus';

/*
 * ISBN-Barcode-Scanner mit der Handykamera.
 *
 * - Nutzt den eingebauten BarcodeDetector des Browsers (Chrome/Android), wenn vorhanden,
 *   sonst die Bibliothek ZXing (z. B. Safari/iPhone). ZXing wird erst beim Scannen geladen.
 * - Akzeptiert nur echte Buch-Barcodes (EAN-13 mit 978/979 und gültiger Prüfziffer).
 * - Kamera braucht HTTPS (oder localhost).
 */
export default class extends Controller {
    static targets = ['overlay', 'video', 'status', 'input', 'form', 'scanArea', 'divider', 'unsupported', 'unsupportedText'];
    // Übersetzte Texte kommen aus dem Template (data-scanner-messages-value)
    static values = { autostart: Boolean, messages: Object };

    connect() {
        this.onKeydown = (e) => { if (e.key === 'Escape') this.stop(); };

        if (!this.isSupported()) {
            this.showUnsupported(window.isSecureContext ? this.messagesValue.noCamera : this.messagesValue.needsHttps);
            return;
        }
        if (this.autostartValue) {
            this.start();
        }
    }

    disconnect() {
        this.stop();
    }

    isSupported() {
        return window.isSecureContext && !!navigator.mediaDevices?.getUserMedia;
    }

    async start() {
        if (this.running) return;
        this.running = true;
        this.found = false;
        this.overlayTarget.hidden = false;
        this.overlayTarget.classList.remove('is-found');
        this.setStatus(this.messagesValue.starting);
        document.addEventListener('keydown', this.onKeydown);

        try {
            if (await this.hasNativeDetector()) {
                await this.startNative();
            } else {
                await this.startZxing();
            }
            if (this.running) this.setStatus(this.messagesValue.aim);
        } catch (error) {
            this.fail(error);
        }
    }

    stop() {
        this.running = false;
        clearTimeout(this.loopTimer);
        this.zxingControls?.stop();
        this.zxingControls = null;
        this.stream?.getTracks().forEach((t) => t.stop());
        this.stream = null;
        if (this.hasVideoTarget) this.videoTarget.srcObject = null;
        if (this.hasOverlayTarget) this.overlayTarget.hidden = true;
        document.removeEventListener('keydown', this.onKeydown);
    }

    // ---------- Native BarcodeDetector ----------

    async hasNativeDetector() {
        if (!('BarcodeDetector' in window)) return false;
        try {
            const formats = await window.BarcodeDetector.getSupportedFormats();
            return formats.includes('ean_13');
        } catch {
            return false;
        }
    }

    async startNative() {
        this.stream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: { ideal: 'environment' }, width: { ideal: 1280 }, height: { ideal: 720 } },
            audio: false,
        });
        if (!this.running) { this.stop(); return; }
        this.videoTarget.srcObject = this.stream;
        await this.videoTarget.play();

        const detector = new window.BarcodeDetector({ formats: ['ean_13'] });
        const tick = async () => {
            if (!this.running) return;
            try {
                const codes = await detector.detect(this.videoTarget);
                for (const code of codes) {
                    if (this.handleCode(code.rawValue)) return;
                }
            } catch { /* einzelnes Bild nicht lesbar – weiter */ }
            this.loopTimer = setTimeout(tick, 120);
        };
        tick();
    }

    // ---------- ZXing (Fallback) ----------

    async startZxing() {
        const [{ BrowserMultiFormatReader }, { DecodeHintType, BarcodeFormat }] = await Promise.all([
            import('@zxing/browser'),
            import('@zxing/library'),
        ]);
        const hints = new Map();
        hints.set(DecodeHintType.POSSIBLE_FORMATS, [BarcodeFormat.EAN_13]);
        hints.set(DecodeHintType.TRY_HARDER, true);
        const reader = new BrowserMultiFormatReader(hints, { delayBetweenScanAttempts: 120 });

        this.zxingControls = await reader.decodeFromConstraints(
            { video: { facingMode: { ideal: 'environment' }, width: { ideal: 1280 }, height: { ideal: 720 } }, audio: false },
            this.videoTarget,
            (result) => { if (result) this.handleCode(result.getText()); },
        );
        if (!this.running) this.stop();
    }

    // ---------- Treffer ----------

    handleCode(raw) {
        if (this.found) return true;
        const code = String(raw).replace(/\D/g, '');
        if (!isValidIsbn13(code)) {
            this.setStatus(this.messagesValue.notIsbn);
            return false;
        }

        this.found = true;
        this.overlayTarget.classList.add('is-found');
        this.setStatus(`${this.messagesValue.found} ${code}`);
        navigator.vibrate?.(80);

        this.inputTarget.value = code;
        setTimeout(() => {
            this.stop();
            this.formTarget.requestSubmit();
        }, 350);

        return true;
    }

    fail(error) {
        this.stop();
        const m = this.messagesValue;
        const messages = {
            NotAllowedError: m.denied,
            NotFoundError: m.notFound,
            NotReadableError: m.busy,
            OverconstrainedError: m.failed,
        };
        this.showUnsupported(messages[error?.name] ?? m.failed, true);
        this.inputTarget.focus();
    }

    setStatus(text) {
        if (this.hasStatusTarget) this.statusTarget.textContent = text;
    }

    showUnsupported(text, keepButton = false) {
        this.unsupportedTextTarget.textContent = text;
        this.unsupportedTarget.hidden = false;
        if (!keepButton) {
            this.scanAreaTarget.hidden = true;
            this.dividerTarget.hidden = true;
        }
    }
}

function isValidIsbn13(code) {
    if (!/^97[89]\d{10}$/.test(code)) return false;
    let sum = 0;
    for (let i = 0; i < 12; i++) {
        sum += Number(code[i]) * (i % 2 === 0 ? 1 : 3);
    }
    return (10 - (sum % 10)) % 10 === Number(code[12]);
}
