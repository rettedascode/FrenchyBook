import { Controller } from '@hotwired/stimulus';

/*
 * Hilfeseite: Ein Link wie /hilfe#ausleihen klappt das passende Thema auf und scrollt hin.
 * Klicks im Inhaltsverzeichnis machen dasselbe, ohne die Seite neu zu laden.
 */
export default class extends Controller {
    connect() {
        this.onHashChange = () => this.openFromHash();
        window.addEventListener('hashchange', this.onHashChange);
        this.openFromHash();
    }

    disconnect() {
        window.removeEventListener('hashchange', this.onHashChange);
    }

    open(event) {
        const id = event.currentTarget.getAttribute('href').slice(1);
        const topic = document.getElementById(id);
        if (!topic) return;
        event.preventDefault();
        history.replaceState(null, '', '#' + id);
        this.reveal(topic);
    }

    top(event) {
        event.preventDefault();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    openFromHash() {
        const id = decodeURIComponent(window.location.hash.slice(1));
        const topic = id ? document.getElementById(id) : null;
        if (topic) this.reveal(topic);
    }

    reveal(topic) {
        if (topic.tagName === 'DETAILS') topic.open = true;
        const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        requestAnimationFrame(() => topic.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'start' }));
        topic.querySelector('summary')?.focus({ preventScroll: true });
    }
}
