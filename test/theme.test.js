/**
 * Test per theme.js
 * Funzioni testate: toggleTheme, updateTheme
 */

beforeEach(() => {
    localStorage.clear();
    document.documentElement.removeAttribute('data-theme');
    document.documentElement.removeAttribute('data-bs-theme');
    document.body.innerHTML = '';
});

const { toggleTheme, updateTheme } = require('../public/js/theme');

describe('updateTheme', () => {
    test('imposta tema light di default', () => {
        updateTheme();
        expect(document.documentElement.getAttribute('data-theme')).toBe('light');
        expect(document.documentElement.getAttribute('data-bs-theme')).toBe('light');
    });

    test('imposta tema dark quando salvato', () => {
        localStorage.setItem('theme', 'dark');
        updateTheme();
        expect(document.documentElement.getAttribute('data-theme')).toBe('dark');
        expect(document.documentElement.getAttribute('data-bs-theme')).toBe('dark');
    });

    test('aggiorna icona per tema light', () => {
        document.body.innerHTML = '<img id="theme-icon" src="" />';
        localStorage.setItem('theme', 'light');
        updateTheme();
        expect(document.getElementById('theme-icon').src).toContain('light_mode.svg');
    });

    test('aggiorna icona per tema dark', () => {
        document.body.innerHTML = '<img id="theme-icon" src="" />';
        localStorage.setItem('theme', 'dark');
        updateTheme();
        expect(document.getElementById('theme-icon').src).toContain('dark_mode.svg');
    });

    test('non crasha se icona non esiste', () => {
        expect(() => updateTheme()).not.toThrow();
    });
});

describe('toggleTheme', () => {
    test('da light a dark', () => {
        localStorage.setItem('theme', 'light');
        toggleTheme();
        expect(localStorage.getItem('theme')).toBe('dark');
        expect(document.documentElement.getAttribute('data-theme')).toBe('dark');
    });

    test('da dark a light', () => {
        localStorage.setItem('theme', 'dark');
        toggleTheme();
        expect(localStorage.getItem('theme')).toBe('light');
        expect(document.documentElement.getAttribute('data-theme')).toBe('light');
    });

    test('da undefined (default) a dark', () => {
        toggleTheme();
        expect(localStorage.getItem('theme')).toBe('dark');
    });
});
