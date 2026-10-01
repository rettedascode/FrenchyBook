import { Controller } from '@hotwired/stimulus';

/*
 * Macht aus einem <select> (mit <optgroup>s) eine Auswahl mit Suchfeld – z. B. für die
 * 86 Kölner Stadtteile. Das echte <select> bleibt (unsichtbar) im Formular und wird
 * mitgeschickt; ohne JavaScript funktioniert alles wie vorher.
 *
 * Gesucht wird ohne Rücksicht auf Groß-/Kleinschreibung und Umlaute („mulheim“ findet „Mülheim“),
 * auch nach dem Bezirk („porz“ zeigt alle Stadtteile in Porz).
 */
export default class extends Controller {
    static values = { search: String, empty: String };

    connect() {
        const select = this.element;
        this.uid = select.id || `ss-${Math.random().toString(36).slice(2)}`;

        this.wrapper = document.createElement('div');
        this.wrapper.className = 'ss';
        select.after(this.wrapper);
        select.classList.add('ss-native');
        select.tabIndex = -1;
        select.setAttribute('aria-hidden', 'true');

        this.toggle = document.createElement('button');
        this.toggle.type = 'button';
        this.toggle.className = 'ss-toggle';
        this.toggle.id = `${this.uid}-toggle`;
        this.toggle.setAttribute('aria-haspopup', 'listbox');
        this.toggle.setAttribute('aria-expanded', 'false');
        if (select.getAttribute('aria-describedby')) this.toggle.setAttribute('aria-describedby', select.getAttribute('aria-describedby'));
        this.toggle.innerHTML = '<span class="ss-value"></span><svg class="ss-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>';
        this.wrapper.append(this.toggle);

        // Das <label for="…"> soll den neuen Knopf ansprechen
        document.querySelectorAll(`label[for="${CSS.escape(select.id)}"]`).forEach((label) => { label.htmlFor = this.toggle.id; });

        this.panel = document.createElement('div');
        this.panel.className = 'ss-panel';
        this.panel.hidden = true;
        this.input = document.createElement('input');
        this.input.type = 'search';
        this.input.className = 'ss-search';
        this.input.placeholder = this.searchValue || '…';
        this.input.setAttribute('aria-label', this.searchValue || '');
        this.input.setAttribute('aria-controls', `${this.uid}-list`);
        this.input.autocomplete = 'off';
        this.input.enterKeyHint = 'done';
        this.list = document.createElement('ul');
        this.list.className = 'ss-list';
        this.list.id = `${this.uid}-list`;
        this.list.setAttribute('role', 'listbox');
        this.panel.append(this.input, this.list);
        this.wrapper.append(this.panel);

        // Einträge aus dem <select> übernehmen
        this.items = [];
        for (const option of select.options) {
            if (option.value === '') {
                this.placeholder = option.textContent.trim();
                continue;
            }
            const group = option.parentElement.tagName === 'OPTGROUP' ? option.parentElement.label : '';
            this.items.push({ value: option.value, label: option.textContent.trim(), group, key: this.normalize(`${option.textContent} ${group}`) });
        }

        this.toggle.addEventListener('click', () => (this.panel.hidden ? this.open() : this.close()));
        this.input.addEventListener('input', () => this.render());
        this.input.addEventListener('keydown', (event) => this.keydown(event));
        this.list.addEventListener('mousedown', (event) => event.preventDefault());
        this.list.addEventListener('click', (event) => {
            const li = event.target.closest('[role="option"]');
            if (li) this.choose(li.dataset.value);
        });
        this.outside = (event) => { if (!this.wrapper.contains(event.target)) this.close(); };
        document.addEventListener('click', this.outside);

        this.updateToggle();
    }

    disconnect() {
        document.removeEventListener('click', this.outside);
        this.wrapper?.remove();
        this.element.classList.remove('ss-native');
        this.element.removeAttribute('aria-hidden');
        this.element.removeAttribute('tabindex');
    }

    normalize(text) {
        return text.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/ß/g, 'ss');
    }

    open() {
        this.panel.hidden = false;
        this.toggle.setAttribute('aria-expanded', 'true');
        this.input.value = '';
        this.render();
        this.input.focus();
        this.list.querySelector('[aria-selected="true"]')?.scrollIntoView({ block: 'center' });
    }

    close(focusToggle = false) {
        if (this.panel.hidden) return;
        this.panel.hidden = true;
        this.toggle.setAttribute('aria-expanded', 'false');
        if (focusToggle) this.toggle.focus();
    }

    render() {
        const words = this.normalize(this.input.value.trim()).split(/\s+/).filter(Boolean);
        const matches = this.items.filter((item) => words.every((w) => item.key.includes(w)));
        this.list.innerHTML = '';
        let group = null;
        for (const item of matches) {
            if (item.group && item.group !== group) {
                group = item.group;
                const head = document.createElement('li');
                head.className = 'ss-group';
                head.setAttribute('role', 'presentation');
                head.textContent = group;
                this.list.append(head);
            }
            const li = document.createElement('li');
            li.className = 'ss-option';
            li.id = `${this.uid}-opt-${item.value}`;
            li.setAttribute('role', 'option');
            li.dataset.value = item.value;
            li.textContent = item.label;
            li.setAttribute('aria-selected', String(item.value === this.element.value));
            this.list.append(li);
        }
        if (matches.length === 0) {
            const empty = document.createElement('li');
            empty.className = 'ss-empty';
            empty.textContent = this.emptyValue || '–';
            this.list.append(empty);
        }
        this.setActive(this.list.querySelector('[aria-selected="true"]') ?? this.list.querySelector('[role="option"]'));
    }

    setActive(li) {
        this.list.querySelectorAll('.is-active').forEach((el) => el.classList.remove('is-active'));
        this.active = li;
        if (li) {
            li.classList.add('is-active');
            this.input.setAttribute('aria-activedescendant', li.id);
            li.scrollIntoView({ block: 'nearest' });
        } else {
            this.input.removeAttribute('aria-activedescendant');
        }
    }

    keydown(event) {
        const options = [...this.list.querySelectorAll('[role="option"]')];
        const index = options.indexOf(this.active);
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            this.setActive(options[Math.min(index + 1, options.length - 1)] ?? options[0]);
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            this.setActive(options[Math.max(index - 1, 0)]);
        } else if (event.key === 'Enter') {
            event.preventDefault();
            if (this.active) this.choose(this.active.dataset.value);
        } else if (event.key === 'Escape') {
            event.preventDefault();
            this.close(true);
        } else if (event.key === 'Tab') {
            this.close();
        }
    }

    choose(value) {
        this.element.value = value;
        this.element.dispatchEvent(new Event('change', { bubbles: true }));
        this.updateToggle();
        this.close(true);
    }

    updateToggle() {
        const item = this.items.find((i) => i.value === this.element.value);
        const label = this.toggle.querySelector('.ss-value');
        label.textContent = item ? (item.group && item.group !== item.label ? `${item.label} (${item.group})` : item.label) : (this.placeholder || '');
        this.toggle.classList.toggle('is-placeholder', !item);
    }
}
