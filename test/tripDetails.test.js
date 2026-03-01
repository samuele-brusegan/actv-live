/**
 * Test per tripDetails.js
 * Funzioni testate: formatMinutesRemaining, mergeStops
 */

// Usiamo history.pushState per cambiare l'URL in jsdom
window.history.pushState({}, 'Test', '?tripId=123');

const { formatMinutesRemaining, mergeStops, state } = require('../public/js/tripDetails');

describe('formatMinutesRemaining', () => {
    test('ritorna la stringa originale se non contiene ":"', () => {
        expect(formatMinutesRemaining('info')).toBe('info');
    });

    test('ritorna la stringa originale per null', () => {
        expect(formatMinutesRemaining(null)).toBeNull();
    });

    test('ritorna la stringa originale per undefined', () => {
        expect(formatMinutesRemaining(undefined)).toBeUndefined();
    });

    test('ritorna "< 1 min" se l\'orario è adesso', () => {
        const now = new Date();
        const h = String(now.getHours()).padStart(2, '0');
        const m = String(now.getMinutes()).padStart(2, '0');
        expect(formatMinutesRemaining(`${h}:${m}`)).toBe('< 1 min');
    });

    test('ritorna "X min" per orario nel futuro prossimo', () => {
        const future = new Date();
        future.setMinutes(future.getMinutes() + 10);
        const h = String(future.getHours()).padStart(2, '0');
        const m = String(future.getMinutes()).padStart(2, '0');
        const result = formatMinutesRemaining(`${h}:${m}`);
        expect(result).toMatch(/\d+ min/);
    });

    test('ritorna "Passato" per orario nel passato', () => {
        const past = new Date();
        past.setMinutes(past.getMinutes() - 10);
        const h = String(past.getHours()).padStart(2, '0');
        const m = String(past.getMinutes()).padStart(2, '0');
        const result = formatMinutesRemaining(`${h}:${m}`);
        expect(result).toMatch(/Passato/);
    });

    test('gestisce formato con ore e minuti per tempi oltre 60 min', () => {
        const future = new Date();
        future.setMinutes(future.getMinutes() + 90);
        const h = String(future.getHours()).padStart(2, '0');
        const m = String(future.getMinutes()).padStart(2, '0');
        const result = formatMinutesRemaining(`${h}:${m}`);
        expect(result).toMatch(/h.*min/);
    });
});

describe('mergeStops', () => {
    beforeEach(() => {
        state.stopsGTFS = [];
        state.stopsJSON = [];
    });

    test('ritorna array vuoto senza dati', () => {
        const result = mergeStops();
        expect(result).toEqual([]);
    });

    test('usa solo dati GTFS quando non ci sono dati real-time', () => {
        state.stopsGTFS = [
            { stop_name: 'Fermata A', stop_id: '100', arrival_time: '10:00', departure_time: '10:00' },
            { stop_name: 'Fermata B', stop_id: '200', arrival_time: '10:05', departure_time: '10:05' }
        ];
        state.stopsJSON = [];
        const result = mergeStops();
        expect(result).toHaveLength(2);
        expect(result[0].hasRealTime).toBe(false);
        expect(result[0].hasGTFS).toBe(true);
    });

    test('fonde dati GTFS e real-time per la stessa fermata', () => {
        state.stopsGTFS = [
            { stop_name: 'Fermata A', stop_id: '100', arrival_time: '10:00', departure_time: '10:00' }
        ];
        state.stopsJSON = [
            { stop: 'Fermata A', time: '10:02' }
        ];
        const result = mergeStops();
        expect(result).toHaveLength(1);
        expect(result[0].hasRealTime).toBe(true);
        expect(result[0].arrival_time).toBe('10:02');
    });

    test('aggiunge fermate JSON non presenti in GTFS', () => {
        state.stopsGTFS = [
            { stop_name: 'Fermata A', stop_id: '100', arrival_time: '10:00', departure_time: '10:00' }
        ];
        state.stopsJSON = [
            { stop: 'Fermata A', time: '10:02' },
            { stop: 'Fermata Extra', time: '10:10' }
        ];
        const result = mergeStops();
        expect(result).toHaveLength(2);
        expect(result[1].hasGTFS).toBe(false);
        expect(result[1].hasRealTime).toBe(true);
    });
});
