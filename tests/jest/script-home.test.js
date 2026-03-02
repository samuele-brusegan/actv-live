/**
 * Test per script-home.js
 * Funzioni testate: calculateDistance, createFavoriteCardHTML, createPopupContent
 */
const { calculateDistance, createFavoriteCardHTML, createPopupContent } = require('../public/js/script-home');

describe('calculateDistance', () => {
    test('distanza tra lo stesso punto è 0', () => {
        expect(calculateDistance(45.4384, 12.3359, 45.4384, 12.3359)).toBe(0);
    });

    test('distanza tra Venezia e Mestre è ~8-10 km', () => {
        // Venezia S. Marco → Mestre stazione
        const dist = calculateDistance(45.4341, 12.3388, 45.4825, 12.2317);
        expect(dist).toBeGreaterThan(7);
        expect(dist).toBeLessThan(12);
    });

    test('distanza tra due punti vicini < 1km', () => {
        // Due punti nella stessa via a Mestre
        const dist = calculateDistance(45.4900, 12.2400, 45.4910, 12.2410);
        expect(dist).toBeLessThan(1);
        expect(dist).toBeGreaterThan(0);
    });

    test('distanza è simmetrica (A→B = B→A)', () => {
        const d1 = calculateDistance(45.0, 12.0, 46.0, 13.0);
        const d2 = calculateDistance(46.0, 13.0, 45.0, 12.0);
        expect(d1).toBeCloseTo(d2, 5);
    });
});

describe('createFavoriteCardHTML', () => {
    test('genera HTML con singolo ID', () => {
        const stop = { id: '4825', name: 'Piazzale Roma' };
        const html = createFavoriteCardHTML(stop);
        expect(html).toContain('Piazzale Roma');
        expect(html).toContain('4825');
        expect(html).toContain('★ Preferita');
        expect(html).toContain('stop-card');
    });

    test('genera HTML con più IDs', () => {
        const stop = { ids: ['2701', '2951', '3564'], name: 'Mogliano Centro' };
        const html = createFavoriteCardHTML(stop);
        expect(html).toContain('2701');
        expect(html).toContain('2951');
        expect(html).toContain('3564');
        expect(html).toContain('Mogliano Centro');
    });

    test('URL encoda correttamente il nome', () => {
        const stop = { id: '100', name: "Ca' Marcello" };
        const html = createFavoriteCardHTML(stop);
        expect(html).toContain(encodeURIComponent("Ca' Marcello"));
    });

    test('genera link corretto con IDs multipli', () => {
        const stop = { ids: ['100', '200'], name: 'Test' };
        const html = createFavoriteCardHTML(stop);
        expect(html).toContain('id=100-200');
    });
});

describe('createPopupContent', () => {
    test('genera popup senza distanza', () => {
        const station = { id: '611-web-aut', name: 'Giovannacci Ulloa' };
        const html = createPopupContent(station);
        expect(html).toContain('Giovannacci Ulloa');
        expect(html).toContain('Vedi passaggi');
        expect(html).not.toContain('Distanza');
    });

    test('genera popup con distanza', () => {
        const station = { id: '611-web-aut', name: 'Giovannacci Ulloa', distance: 0.52 };
        const html = createPopupContent(station, true);
        expect(html).toContain('0.52 km');
        expect(html).toContain('Distanza');
    });

    test('rimuove -web-aut dall\'ID nel link', () => {
        const station = { id: '611-web-aut', name: 'Test' };
        const html = createPopupContent(station);
        expect(html).toContain('id=611');
        expect(html).not.toContain('id=611-web-aut');
    });
});
