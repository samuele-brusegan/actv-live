/**
 * Test per stop.js
 * Funzioni testate: getFavorites, isFavorite, createPassageCard
 * 
 * Nota: stationId viene inizializzato al caricamento del modulo stop.js.
 * Dobbiamo settare l'URL PRIMA del require.
 */

// 1. Settiamo l'URL iniziale per jsdom
window.history.pushState({}, 'Test', '?id=4825&name=Piazzale%20Roma');

// 2. Mock console.error per evitare output sporco durante i test di errore
let consoleSpy;
beforeAll(() => {
    consoleSpy = jest.spyOn(console, 'error').mockImplementation(() => { });
});
afterAll(() => {
    consoleSpy.mockRestore();
});

beforeEach(() => {
    localStorage.clear();
});

// 3. Carichiamo il modulo (ora stationId sarà "4825")
const { getFavorites, isFavorite, createPassageCard } = require('../public/js/stop');

describe('getFavorites', () => {
    test('ritorna array vuoto se non ci sono preferiti', () => {
        expect(getFavorites()).toEqual([]);
    });

    test('ritorna i preferiti salvati', () => {
        const favs = [{ id: '611', name: 'Giovannacci Ulloa' }];
        localStorage.setItem('favorite_stops', JSON.stringify(favs));
        expect(getFavorites()).toEqual(favs);
    });

    test('ritorna array vuoto per JSON corrotto', () => {
        localStorage.setItem('favorite_stops', 'invalid json{{{');
        expect(getFavorites()).toEqual([]);
        // Verifica che console.error sia stato chiamato
        expect(consoleSpy).toHaveBeenCalled();
    });

    test('gestisce array con oggetti multipli', () => {
        const favs = [
            { id: '611', name: 'Stop A' },
            { id: '704', name: 'Stop B' },
        ];
        localStorage.setItem('favorite_stops', JSON.stringify(favs));
        expect(getFavorites()).toHaveLength(2);
    });
});

describe('isFavorite', () => {
    test('ritorna true se la fermata è nei preferiti (ID singolo)', () => {
        const favs = [{ id: '4825', name: 'Piazzale Roma' }];
        localStorage.setItem('favorite_stops', JSON.stringify(favs));
        expect(isFavorite()).toBe(true);
    });

    test('ritorna false se la fermata non è nei preferiti', () => {
        const favs = [{ id: '9999', name: 'Altra Fermata' }];
        localStorage.setItem('favorite_stops', JSON.stringify(favs));
        expect(isFavorite()).toBe(false);
    });

    test('ritorna true con formato ids array', () => {
        const favs = [{ ids: ['4825', '4826'], name: 'Piazzale Roma' }];
        localStorage.setItem('favorite_stops', JSON.stringify(favs));
        expect(isFavorite()).toBe(true);
    });

    test('ritorna false senza preferiti', () => {
        expect(isFavorite()).toBe(false);
    });
});

describe('createPassageCard', () => {
    test('crea card con dati ACTV realistici', () => {
        const passage = {
            lineId: 5240,
            line: '6L_UM',
            destination: 'CORRENTI',
            time: "4'",
            real: true,
            stop: '611',
            timingPoints: [
                { time: '13:14', stop: 'Giovannacci Ulloa' },
                { time: '13:15', stop: 'Lavelli Paolucci' }
            ]
        };
        const card = createPassageCard(passage);
        expect(card).toBeInstanceOf(HTMLDivElement);
        expect(card.className).toBe('passage-card');
        expect(card.innerHTML).toContain('CORRENTI');
        expect(card.innerHTML).toContain('6L');
    });

    test('gestisce linea notturna', () => {
        const passage = {
            line: 'N1_UM',
            destination: 'TEST',
            time: '23:00',
            real: false,
            stop: '100',
            timingPoints: [{ time: '23:00', stop: 'Test' }]
        };
        const card = createPassageCard(passage);
        expect(card.innerHTML).toContain('badge-night');
    });

    test('gestisce tempo "departure"', () => {
        const passage = {
            line: '5_UM',
            destination: 'TEST',
            time: 'departure',
            real: true,
            stop: '100',
            timingPoints: [{ time: '10:00', stop: 'Test' }]
        };
        const card = createPassageCard(passage);
        expect(card.innerHTML).toContain('Ora');
    });
});
