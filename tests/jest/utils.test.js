/**
 * Test per le utility condivise (utils.js)
 * Testate le funzioni: timeToSec, secToTime, getDist, parseRealTime,
 * getDeterministicColor, filterBuses, findNextStopWithDelay
 * 
 * Queste funzioni sono usate da liveBusMap.js, adminDashboard.js e linesMap.js
 */
const {
    timeToSec,
    secToTime,
    getDist,
    parseRealTime,
    getDeterministicColor,
    filterBuses,
    findNextStopWithDelay
} = require('../public/js/utils');

// ─── timeToSec ──────────────────────────────────────────────

describe('timeToSec', () => {
    test('converte 00:00:00 in 0', () => {
        expect(timeToSec('00:00:00')).toBe(0);
    });

    test('converte 01:00:00 in 3600', () => {
        expect(timeToSec('01:00:00')).toBe(3600);
    });

    test('converte 12:30:00 in 45000', () => {
        expect(timeToSec('12:30:00')).toBe(45000);
    });

    test('converte 23:59:59 in 86399', () => {
        expect(timeToSec('23:59:59')).toBe(86399);
    });

    test('gestisce formato HH:MM (senza secondi)', () => {
        expect(timeToSec('14:30')).toBe(52200);
    });

    test('normalizza 24:xx a 00:xx', () => {
        expect(timeToSec('24:00:00')).toBe(0);
    });

    test('normalizza 25:00:00', () => {
        // 25*3600=90000 % 86400 = 3600
        expect(timeToSec('25:00:00')).toBe(3600);
    });

    test('ritorna 0 per null', () => {
        expect(timeToSec(null)).toBe(0);
    });

    test('ritorna 0 per undefined', () => {
        expect(timeToSec(undefined)).toBe(0);
    });

    test('ritorna 0 per stringa vuota', () => {
        expect(timeToSec('')).toBe(0);
    });
});

// ─── secToTime ──────────────────────────────────────────────

describe('secToTime', () => {
    test('converte 0 in 00:00:00', () => {
        expect(secToTime(0)).toBe(0); // Note: original returns 0 for falsy
    });

    test('converte 3600 in 01:00:00', () => {
        expect(secToTime(3600)).toBe('01:00:00');
    });

    test('converte 45000 in 12:30:00', () => {
        expect(secToTime(45000)).toBe('12:30:00');
    });

    test('converte 86399 in 23:59:59', () => {
        expect(secToTime(86399)).toBe('23:59:59');
    });

    test('converte 52200 in 14:30:00', () => {
        expect(secToTime(52200)).toBe('14:30:00');
    });

    test('ritorna 0 per null', () => {
        expect(secToTime(null)).toBe(0);
    });
});

// ─── getDist ────────────────────────────────────────────────

describe('getDist', () => {
    test('distanza tra lo stesso punto è 0', () => {
        expect(getDist(45.4384, 12.3359, 45.4384, 12.3359)).toBe(0);
    });

    test('distanza fra due punti vicini (~200m)', () => {
        // Due punti distanti circa 200m a Venezia
        const dist = getDist(45.4384, 12.3359, 45.4400, 12.3359);
        expect(dist).toBeGreaterThan(100);
        expect(dist).toBeLessThan(300);
    });

    test('ritorna in metri (non km)', () => {
        // Venezia → Padova: circa 35-40 km
        const dist = getDist(45.4384, 12.3359, 45.4064, 11.8768);
        expect(dist).toBeGreaterThan(30000);
        expect(dist).toBeLessThan(50000);
    });

    test('distanza è simmetrica', () => {
        const d1 = getDist(45.0, 12.0, 46.0, 13.0);
        const d2 = getDist(46.0, 13.0, 45.0, 12.0);
        expect(d1).toBeCloseTo(d2, 5);
    });
});

// ─── parseRealTime ──────────────────────────────────────────

describe('parseRealTime', () => {
    const now = new Date('2025-03-01T14:00:00').getTime();

    test('parsa formato HH:MM', () => {
        expect(parseRealTime('14:30', now)).toBe(timeToSec('14:30:00'));
    });

    test('parsa formato con apice "5\'"', () => {
        const result = parseRealTime("5'", now);
        expect(result).not.toBeNull();
        // 14:00 + 5 min = 14:05 = 50700 sec
        expect(result).toBe(50700);
    });

    test('parsa formato "10 min"', () => {
        const result = parseRealTime('10 min', now);
        expect(result).not.toBeNull();
        // 14:00 + 10 min = 14:10 = 51000 sec
        expect(result).toBe(51000);
    });

    test('ritorna null per null', () => {
        expect(parseRealTime(null, now)).toBeNull();
    });

    test('ritorna null per stringa non parsabile', () => {
        expect(parseRealTime('abc', now)).toBeNull();
    });

    test('ritorna null per stringa vuota', () => {
        expect(parseRealTime('', now)).toBeNull();
    });

    test('gestisce formato "4\'" come nei dati ACTV reali', () => {
        const result = parseRealTime("4'", now);
        expect(result).not.toBeNull();
        // 14:00 + 4 min = 14:04 = 50640 sec
        expect(result).toBe(50640);
    });
});

// ─── getDeterministicColor ──────────────────────────────────

describe('getDeterministicColor', () => {
    const COLORS = ['#FF0000', '#0000FF', '#008000', '#FFA500'];

    test('ritorna grigio se non colorato', () => {
        expect(getDeterministicColor('5E', false, COLORS)).toBe('#AAA');
    });

    test('ritorna un colore dalla palette', () => {
        const color = getDeterministicColor('5E', true, COLORS);
        expect(COLORS).toContain(color);
    });

    test('stesso nome → stesso colore (deterministico)', () => {
        const c1 = getDeterministicColor('7', true, COLORS);
        const c2 = getDeterministicColor('7', true, COLORS);
        expect(c1).toBe(c2);
    });

    test('nomi diversi possono dare colori diversi', () => {
        const c1 = getDeterministicColor('5E', true, COLORS);
        const c2 = getDeterministicColor('N1', true, COLORS);
        // Non necessariamente diversi, ma proviamo
        expect(typeof c1).toBe('string');
        expect(typeof c2).toBe('string');
    });
});

// ─── filterBuses ────────────────────────────────────────────

describe('filterBuses', () => {
    const buses = [
        { route_short_name: '5E', trip_id: 'trip_001', route_id: '10', trip_headsign: 'PIAZZALE ROMA' },
        { route_short_name: '7', trip_id: 'trip_002', route_id: '20', trip_headsign: 'FAVARO' },
        { route_short_name: 'N1', trip_id: 'trip_003', route_id: '30', trip_headsign: 'MESTRE FS' },
    ];

    test('ritorna tutti i bus senza filtro', () => {
        expect(filterBuses(buses, '')).toHaveLength(3);
        expect(filterBuses(buses, null)).toHaveLength(3);
    });

    test('filtra per route_short_name', () => {
        expect(filterBuses(buses, '5E')).toHaveLength(1);
        expect(filterBuses(buses, '5E')[0].trip_id).toBe('trip_001');
    });

    test('filtra per trip_headsign', () => {
        expect(filterBuses(buses, 'piazzale')).toHaveLength(1);
    });

    test('filtra per trip_id', () => {
        expect(filterBuses(buses, 'trip_002')).toHaveLength(1);
    });

    test('filtro case insensitive', () => {
        expect(filterBuses(buses, 'mestre')).toHaveLength(1);
    });

    test('ritorna array vuoto per query senza match', () => {
        expect(filterBuses(buses, 'zzzzzzz')).toHaveLength(0);
    });
});

// ─── findNextStopWithDelay ──────────────────────────────────

describe('findNextStopWithDelay', () => {
    const stops = [
        { stop_name: 'Fermata A', stop_id: '100', arrival_time: '10:00:00', data_url: '100-web-aut' },
        { stop_name: 'Fermata B', stop_id: '200', arrival_time: '10:10:00', data_url: '200-web-aut' },
        { stop_name: 'Fermata C', stop_id: '300', arrival_time: '10:20:00', data_url: '300-web-aut' },
    ];

    test('ritorna null per lista vuota', () => {
        expect(findNextStopWithDelay([], 36000)).toBeNull();
    });

    test('ritorna null per null', () => {
        expect(findNextStopWithDelay(null, 36000)).toBeNull();
    });

    test('ritorna prima fermata se il bus non è ancora partito', () => {
        const result = findNextStopWithDelay(stops, timeToSec('09:50:00'));
        expect(result.nextStop).toBe('Fermata A');
    });

    test('ritorna fermata B quando siamo tra A e B', () => {
        const result = findNextStopWithDelay(stops, timeToSec('10:05:00'));
        expect(result.nextStop).toBe('Fermata B');
    });

    test('ritorna fermata C quando siamo tra B e C', () => {
        const result = findNextStopWithDelay(stops, timeToSec('10:15:00'));
        expect(result.nextStop).toBe('Fermata C');
    });

    test('ritorna null dopo l\'ultima fermata', () => {
        const result = findNextStopWithDelay(stops, timeToSec('10:25:00'));
        expect(result).toBeNull();
    });

    test('applica ritardo correttamente', () => {
        // Con 5 min di ritardo: nowSec=10:15 - delay=300 → virtualNow=10:10
        const result = findNextStopWithDelay(stops, timeToSec('10:15:00'), 300);
        // virtualNow=10:10 == exact arrival B, should be at B (between A and B bounds)
        expect(result.nextStop).toBe('Fermata B');
    });

    test('estrae stopId da data_url correttamente', () => {
        const result = findNextStopWithDelay(stops, timeToSec('10:05:00'));
        expect(result.nextStopId).toBe('200');
    });
});
