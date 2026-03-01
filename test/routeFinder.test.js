/**
 * Test per routeFinder.js
 * Funzioni testate: searchState, swapStations
 */

beforeEach(() => {
    localStorage.clear();
    document.body.innerHTML = `
        <div id="origin-value"></div>
        <div id="destination-value"></div>
        <div id="display-date"></div>
        <div id="display-time"></div>
        <div id="date-modal" style="display:none"></div>
        <div id="time-modal" style="display:none"></div>
    `;
});

const { searchState, swapStations, updateDisplayDateTime, searchRoutes } = require('../public/js/routeFinder');

describe('searchState', () => {
    test('stato iniziale ha origin e destination null', () => {
        expect(searchState.origin).toBeNull();
        expect(searchState.destination).toBeNull();
    });

    test('selectedDate è un oggetto Date', () => {
        expect(searchState.selectedDate).toBeInstanceOf(Date);
    });

    test('selectedMinute è arrotondato ai 5 minuti', () => {
        expect(searchState.selectedMinute % 5).toBe(0);
    });
});

describe('swapStations', () => {
    test('scambia origin e destination', () => {
        searchState.origin = { id: '100', name: 'Stop A' };
        searchState.destination = { id: '200', name: 'Stop B' };

        swapStations();

        expect(searchState.origin.name).toBe('Stop B');
        expect(searchState.destination.name).toBe('Stop A');
    });

    test('funziona con una stazione null', () => {
        searchState.origin = { id: '100', name: 'Stop A' };
        searchState.destination = null;

        swapStations();

        expect(searchState.origin).toBeNull();
        expect(searchState.destination.name).toBe('Stop A');
    });

    test('doppio swap ritorna allo stato iniziale', () => {
        searchState.origin = { id: '100', name: 'Stop A' };
        searchState.destination = { id: '200', name: 'Stop B' };

        swapStations();
        swapStations();

        expect(searchState.origin.name).toBe('Stop A');
        expect(searchState.destination.name).toBe('Stop B');
    });
});

describe('searchRoutes', () => {
    test('mostra alert se origin è mancante', () => {
        searchState.origin = null;
        searchState.destination = { id: '200', name: 'Stop B' };

        const alertMock = jest.spyOn(window, 'alert').mockImplementation(() => { });
        searchRoutes();
        expect(alertMock).toHaveBeenCalledWith('Inserisci sia la partenza che la destinazione.');
        alertMock.mockRestore();
    });

    test('mostra alert se destination è mancante', () => {
        searchState.origin = { id: '100', name: 'Stop A' };
        searchState.destination = null;

        const alertMock = jest.spyOn(window, 'alert').mockImplementation(() => { });
        searchRoutes();
        expect(alertMock).toHaveBeenCalledWith('Inserisci sia la partenza che la destinazione.');
        alertMock.mockRestore();
    });
});
