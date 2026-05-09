/**
 * Test per offline.js
 * Funzioni testate: isOnline, updateOfflineIndicator
 */

const { isOnline, updateOfflineIndicator } = require('../../public/js/offline');

beforeEach(() => {
    document.body.innerHTML = '';
});

describe('isOnline', () => {
    test('ritorna true quando navigator.onLine e\' true', () => {
        Object.defineProperty(navigator, 'onLine', { value: true, configurable: true });
        expect(isOnline()).toBe(true);
    });

    test('ritorna false quando navigator.onLine e\' false', () => {
        Object.defineProperty(navigator, 'onLine', { value: false, configurable: true });
        expect(isOnline()).toBe(false);
    });
});

describe('updateOfflineIndicator', () => {
    test('crea indicatore offline quando non connesso', () => {
        Object.defineProperty(navigator, 'onLine', { value: false, configurable: true });
        updateOfflineIndicator();
        const indicator = document.getElementById('offline-indicator');
        expect(indicator).not.toBeNull();
        expect(indicator.style.display).toBe('flex');
        expect(indicator.textContent).toContain('Offline');
    });

    test('nasconde indicatore quando connesso', () => {
        // Prima crea l\'indicatore
        Object.defineProperty(navigator, 'onLine', { value: false, configurable: true });
        updateOfflineIndicator();
        
        // Poi torna online
        Object.defineProperty(navigator, 'onLine', { value: true, configurable: true });
        updateOfflineIndicator();
        
        const indicator = document.getElementById('offline-indicator');
        expect(indicator.style.display).toBe('none');
    });

    test('non duplica indicatore su chiamate multiple', () => {
        Object.defineProperty(navigator, 'onLine', { value: false, configurable: true });
        updateOfflineIndicator();
        updateOfflineIndicator();
        updateOfflineIndicator();
        
        const indicators = document.querySelectorAll('#offline-indicator');
        expect(indicators.length).toBe(1);
    });
});
