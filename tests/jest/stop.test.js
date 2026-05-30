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
const { getFavorites, isFavorite, createPassageCard, switchTab, renderStopLines, mergePassages, isLikelyStrike, lineDestKey } = require('../../public/js/stop');

describe('mergePassages (fallback previsti)', () => {
    test('aggiunge i previsti per linee non coperte dal real-time', () => {
        const rt = [{ line: '2_US', destination: 'Mestre', real: true }];
        const sched = [
            { line: '2', destination: 'Mestre', real: false, time: '10:05' },   // già coperto -> escluso
            { line: '5E', destination: 'Marghera', real: false, time: '10:08' } // mancante -> incluso
        ];
        const merged = mergePassages(rt, sched);
        expect(merged.length).toBe(2);
        expect(merged.some(p => p.line === '5E')).toBe(true);
    });

    test('con real-time null usa solo i previsti', () => {
        const sched = [{ line: '5E', destination: 'Marghera', real: false, time: '10:08' }];
        expect(mergePassages(null, sched).length).toBe(1);
    });

    test('deduplica i previsti identici', () => {
        const sched = [
            { line: '5E', destination: 'Marghera', time: '10:08' },
            { line: '5E', destination: 'Marghera', time: '10:08' }
        ];
        expect(mergePassages([], sched).length).toBe(1);
    });
});

describe('isLikelyStrike', () => {
    test('vero se real-time vuoto ma esistono corse previste', () => {
        expect(isLikelyStrike([], [{ line: '2' }])).toBe(true);
    });
    test('falso se ci sono passaggi real-time', () => {
        expect(isLikelyStrike([{ line: '2' }], [{ line: '2' }])).toBe(false);
    });
    test('falso se real-time è null (errore di rete)', () => {
        expect(isLikelyStrike(null, [{ line: '2' }])).toBe(false);
    });
    test('falso se non ci sono nemmeno corse previste', () => {
        expect(isLikelyStrike([], [])).toBe(false);
    });
});

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

describe('switchTab', () => {
    beforeEach(() => {
        document.body.innerHTML = `
            <button class="stop-tab active" data-tab="passages"></button>
            <button class="stop-tab" data-tab="lines"></button>
            <div id="tab-passages" class="tab-content active"></div>
            <div id="tab-lines" class="tab-content"></div>
        `;
    });

    test('attiva il tab linee', () => {
        switchTab('lines');
        expect(document.querySelector('[data-tab="lines"]').classList.contains('active')).toBe(true);
        expect(document.getElementById('tab-lines').classList.contains('active')).toBe(true);
        expect(document.querySelector('[data-tab="passages"]').classList.contains('active')).toBe(false);
    });

    test('torna al tab passaggi', () => {
        switchTab('lines');
        switchTab('passages');
        expect(document.querySelector('[data-tab="passages"]').classList.contains('active')).toBe(true);
        expect(document.getElementById('tab-passages').classList.contains('active')).toBe(true);
    });
});

describe('renderStopLines', () => {
    test('renderizza le linee nel container', () => {
        const container = document.createElement('div');
        const lines = [
            {
                route_short_name: '2_US',
                route_long_name: 'Lido - P.le Roma',
                departures: [
                    { time: '08:30', destination: 'P.le Roma' },
                    { time: '09:00', destination: 'P.le Roma' }
                ]
            },
            {
                route_short_name: 'N1_UM',
                route_long_name: 'Notturno 1',
                departures: [{ time: '23:30', destination: 'Lido' }]
            }
        ];
        renderStopLines(lines, container);
        expect(container.innerHTML).toContain('badge-blue');
        expect(container.innerHTML).toContain('badge-night');
        expect(container.innerHTML).toContain('08:30');
        expect(container.innerHTML).toContain('P.le Roma');
        expect(container.querySelectorAll('.line-card').length).toBe(2);
    });

    test('gestisce container null', () => {
        expect(() => renderStopLines([], null)).not.toThrow();
    });
});
