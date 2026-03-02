/**
 * Test per StopCard.js
 * Funzioni testate: StopCard.create, StopCard.createMultiple
 */
const StopCard = require('../public/components/StopCard');

describe('StopCard.create', () => {
    test('genera HTML per fermata semplice', () => {
        const stop = { id: '611', name: 'Giovannacci Ulloa', lines: [] };
        const html = StopCard.create(stop);
        expect(html).toContain('Giovannacci Ulloa');
        expect(html).toContain('611');
        expect(html).toContain('stop-card');
    });

    test('mostra icona preferito se isFavorite', () => {
        const stop = { id: '611', name: 'Test', lines: [] };
        const html = StopCard.create(stop, { isFavorite: true });
        expect(html).toContain('★');
    });

    test('mostra freccia se non è preferito', () => {
        const stop = { id: '611', name: 'Test', lines: [] };
        const html = StopCard.create(stop, { isFavorite: false });
        expect(html).toContain('›');
    });

    test('genera badge per le linee', () => {
        const stop = {
            id: '611',
            name: 'Test',
            lines: [
                { alias: '5E', line: '5E' },
                { alias: '7', line: '7' }
            ]
        };
        const html = StopCard.create(stop);
        expect(html).toContain('5E');
        expect(html).toContain('7');
    });

    test('mostra "Nessuna linea" senza linee', () => {
        const stop = { id: '611', name: 'Test', lines: [] };
        const html = StopCard.create(stop);
        expect(html).toContain('Nessuna linea');
    });

    test('gestisce ID con -web-aut', () => {
        const stop = { id: '611-web-aut', name: 'Test', lines: [] };
        const html = StopCard.create(stop);
        expect(html).toContain('611');
        expect(html).not.toContain('web-aut');
    });

    test('gestisce stop con ID composto da -', () => {
        const stop = { id: '2701-2951-3564', name: 'Mogliano Centro', lines: [] };
        const html = StopCard.create(stop);
        expect(html).toContain('2701');
        expect(html).toContain('2951');
        expect(html).toContain('3564');
    });

    test('gestisce "terminal" nel nome ID', () => {
        const stop = { id: 'terminal-cialdini', name: 'Terminal Cialdini', lines: [] };
        const html = StopCard.create(stop);
        expect(html).toContain('Terminal cialdini');
    });

    test('nasconde IDs quando showIds è false', () => {
        const stop = { id: '611', name: 'Test', lines: [] };
        const html = StopCard.create(stop, { showIds: false });
        expect(html).not.toContain('stop-ids-container');
    });
});

describe('StopCard.createMultiple', () => {
    test('genera HTML per più fermate', () => {
        const stops = [
            { id: '611', name: 'Stop A', lines: [] },
            { id: '704', name: 'Stop B', lines: [] }
        ];
        const html = StopCard.createMultiple(stops);
        expect(html).toContain('Stop A');
        expect(html).toContain('Stop B');
    });

    test('ritorna stringa vuota per array vuoto', () => {
        expect(StopCard.createMultiple([])).toBe('');
    });
});
