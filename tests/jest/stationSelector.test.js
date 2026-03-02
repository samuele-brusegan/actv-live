/**
 * Test per stationSelector.js
 * Funzioni testate: addToRecent, createStopCardHTML
 */

beforeEach(() => {
    localStorage.clear();
    document.body.innerHTML = '';
    // Usiamo history.pushState per cambiare l'URL in jsdom
    window.history.pushState({}, 'Test', '?type=origin');
});

const { addToRecent, createStopCardHTML } = require('../public/js/stationSelector');

describe('addToRecent', () => {
    test('aggiunge una fermata alla cronologia', () => {
        const stop = { id: '611', name: 'Giovannacci Ulloa', type: 'stop' };
        addToRecent(stop);
        const recent = JSON.parse(localStorage.getItem('recent_stops'));
        expect(recent).toHaveLength(1);
        expect(recent[0].name).toBe('Giovannacci Ulloa');
    });

    test('non aggiunge duplicati', () => {
        const stop = { id: '611', name: 'Giovannacci Ulloa', type: 'stop' };
        addToRecent(stop);
        addToRecent(stop);
        const recent = JSON.parse(localStorage.getItem('recent_stops'));
        expect(recent).toHaveLength(1);
    });

    test('posiziona le nuove fermate in cima', () => {
        addToRecent({ id: '100', name: 'Stop A', type: 'stop' });
        addToRecent({ id: '200', name: 'Stop B', type: 'stop' });
        const recent = JSON.parse(localStorage.getItem('recent_stops'));
        expect(recent[0].name).toBe('Stop B');
        expect(recent[1].name).toBe('Stop A');
    });

    test('non salva null', () => {
        addToRecent(null);
        const recent = JSON.parse(localStorage.getItem('recent_stops') || '[]');
        expect(recent).toHaveLength(0);
    });

    test('mantiene massimo 10 fermate', () => {
        for (let i = 0; i < 15; i++) {
            addToRecent({ id: String(i), name: `Stop ${i}`, type: 'stop' });
        }
        const recent = JSON.parse(localStorage.getItem('recent_stops'));
        expect(recent.length).toBeLessThanOrEqual(10);
    });

    test('gestisce indirizzi (tipo address)', () => {
        const addr = { id: 'addr_123', name: 'Via Roma 1', type: 'address' };
        addToRecent(addr);
        const recent = JSON.parse(localStorage.getItem('recent_stops'));
        expect(recent[0].type).toBe('address');
    });
});

describe('createStopCardHTML', () => {
    test('ritorna stringa vuota per null', () => {
        expect(createStopCardHTML(null, false)).toBe('');
    });

    test('genera HTML per tipo address', () => {
        const addr = { type: 'address', name: 'Via Roma 1', parsedName: 'Via Roma 1, Venezia' };
        const html = createStopCardHTML(addr, false);
        expect(html).toContain('📍');
        expect(html).toContain('Via Roma 1, Venezia');
        expect(html).toContain('Indirizzo');
    });

    test('genera HTML per fermata', () => {
        const stop = { id: '611', name: 'Giovannacci Ulloa', type: 'stop' };
        const html = createStopCardHTML(stop, false);
        expect(html).toContain('Giovannacci Ulloa');
        expect(html).toContain('stop-card');
    });

    test('rimuove web-aut dal nome visualizzato', () => {
        const stop = { id: '611', name: 'Test web-aut', type: 'stop' };
        const html = createStopCardHTML(stop, false);
        expect(html).toContain('<div class="stop-name">Test</div>');
    });
});
