import { safeGet, safeSet } from './pwa.js';

/*
 * Darstellung: 'auto' (wie das Gerät), 'light' oder 'dark' – pro Gerät gespeichert.
 */
const KEY = 'frenchybook-theme';
const COLORS = { light: '#f6f5f2', dark: '#121317' };

export function currentTheme() {
    const t = safeGet(KEY);
    return t === 'light' || t === 'dark' ? t : 'auto';
}

export function applyTheme(theme = currentTheme()) {
    const root = document.documentElement;
    const metas = document.querySelectorAll('meta[name="theme-color"]');
    if (theme === 'light' || theme === 'dark') {
        root.dataset.theme = theme;
        metas.forEach((m) => { m.content = COLORS[theme]; });
    } else {
        delete root.dataset.theme;
        metas.forEach((m) => { m.content = (m.media || '').includes('dark') ? COLORS.dark : COLORS.light; });
    }
}

export function setTheme(theme) {
    safeSet(KEY, theme === 'light' || theme === 'dark' ? theme : 'auto');
    applyTheme(theme);
}
