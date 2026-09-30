import { Controller } from '@hotwired/stimulus';
import { deviceLabel, isIos, isStandalone, urlBase64ToUint8Array } from '../lib/pwa.js';

/*
 * Push-Benachrichtigungen für DIESES Gerät ein- und ausschalten (Profil).
 *
 * Zustände: unsupported · ios-install (iPhone: erst als App installieren) · blocked · off · on
 * Texte kommen übersetzt aus dem Template (data-push-messages-value).
 */
export default class extends Controller {
    static targets = ['status', 'enable', 'disable', 'test', 'feedback'];
    static values = {
        publicKey: String,
        token: String,
        subscribeUrl: String,
        unsubscribeUrl: String,
        testUrl: String,
        messages: Object,
    };

    async connect() {
        this.onMessage = (event) => {
            if (event.data?.type === 'push-resubscribe' && Notification.permission === 'granted') {
                this.subscribe().catch(() => {});
            }
        };
        navigator.serviceWorker?.addEventListener('message', this.onMessage);
        await this.refresh();
    }

    disconnect() {
        navigator.serviceWorker?.removeEventListener('message', this.onMessage);
    }

    get supported() {
        return window.isSecureContext && 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
    }

    async refresh() {
        if (!this.supported) {
            return this.show(isIos() && !isStandalone() ? 'ios-install' : 'unsupported');
        }
        if (Notification.permission === 'denied') {
            return this.show('blocked');
        }
        try {
            const registration = await this.registration();
            let subscription = await registration.pushManager.getSubscription();
            if (subscription && !this.matchesServerKey(subscription)) {
                // Server hat neue Schlüssel – altes Abo ist wertlos, neu anmelden
                await subscription.unsubscribe();
                subscription = Notification.permission === 'granted' ? await this.subscribe() : null;
            } else if (subscription) {
                // Server auf Stand bringen (z. B. nach Gerätewechsel des Kontos)
                await this.post(this.subscribeUrlValue, this.payload(subscription));
            }
            this.show(subscription ? 'on' : 'off');
        } catch (e) {
            this.show('off');
        }
    }

    async enable() {
        this.busy(true);
        try {
            const permission = await Notification.requestPermission();
            if (permission !== 'granted') {
                this.show(permission === 'denied' ? 'blocked' : 'off');
                return;
            }
            await this.subscribe();
            this.show('on');
        } catch (e) {
            this.feedback(this.messagesValue.error);
        } finally {
            this.busy(false);
        }
    }

    async disable() {
        this.busy(true);
        try {
            const registration = await this.registration();
            const subscription = await registration.pushManager.getSubscription();
            if (subscription) {
                await this.post(this.unsubscribeUrlValue, { endpoint: subscription.endpoint });
                await subscription.unsubscribe();
            }
            this.show('off');
        } catch (e) {
            this.feedback(this.messagesValue.error);
        } finally {
            this.busy(false);
        }
    }

    async test() {
        this.busy(true);
        try {
            const result = await this.post(this.testUrlValue, {});
            this.feedback(result.ok ? this.messagesValue.testSent : this.messagesValue.testFailed);
        } catch (e) {
            this.feedback(this.messagesValue.error);
        } finally {
            this.busy(false);
        }
    }

    // ---------- intern ----------

    async registration() {
        return navigator.serviceWorker.getRegistration('/').then((r) => r || navigator.serviceWorker.register('/sw.js'))
            .then(() => navigator.serviceWorker.ready);
    }

    async subscribe() {
        const registration = await this.registration();
        const subscription = await registration.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: urlBase64ToUint8Array(this.publicKeyValue),
        });
        await this.post(this.subscribeUrlValue, this.payload(subscription));
        return subscription;
    }

    payload(subscription) {
        return { ...subscription.toJSON(), device: deviceLabel() };
    }

    matchesServerKey(subscription) {
        const key = subscription.options?.applicationServerKey;
        if (!key) return true;
        const current = urlBase64ToUint8Array(this.publicKeyValue);
        const existing = new Uint8Array(key);
        return existing.length === current.length && existing.every((byte, i) => byte === current[i]);
    }

    async post(url, data) {
        const response = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': this.tokenValue },
            body: JSON.stringify(data),
            credentials: 'same-origin',
        });
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        return response.json();
    }

    show(state) {
        this.state = state;
        const m = this.messagesValue;
        const texts = { unsupported: m.unsupported, 'ios-install': m.iosInstall, blocked: m.blocked, off: m.off, on: m.on };
        this.statusTarget.textContent = texts[state] ?? '';
        this.statusTarget.dataset.state = state;
        this.enableTarget.hidden = state !== 'off';
        this.disableTarget.hidden = state !== 'on';
        this.testTarget.hidden = state !== 'on';
        this.feedbackTarget.hidden = true;
    }

    feedback(text) {
        this.feedbackTarget.textContent = text;
        this.feedbackTarget.hidden = false;
    }

    busy(isBusy) {
        [this.enableTarget, this.disableTarget, this.testTarget].forEach((button) => { button.disabled = isBusy; });
    }
}
