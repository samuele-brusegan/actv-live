/**
 * Test per routeResults.js
 * Funzioni testate: safeParseJSON, getLineBadgeDetails, formatItalianDate, formatShortTime
 */
const { safeParseJSON, getLineBadgeDetails, formatItalianDate, formatShortTime, getTransferCount, getWalkingMinutes, findBestValues, distanceMeters, formatVehicleDistance, computeVehicleProgress } = require('../../public/js/routeResults');

describe('posizione realtime del mezzo', () => {
    test('calcola la distanza tra mezzo e fermata', () => {
        expect(distanceMeters(45.4384, 12.3359, 45.4393, 12.3359)).toBeGreaterThan(90);
        expect(distanceMeters(45.4384, 12.3359, 45.4393, 12.3359)).toBeLessThan(110);
    });

    test('formatta una distanza leggibile senza chiamarla ETA', () => {
        expect(formatVehicleDistance(90)).toBe('in prossimità della fermata');
        expect(formatVehicleDistance(640)).toBe('a circa 600 m dalla fermata');
        expect(formatVehicleDistance(1420)).toBe('a circa 1,4 km dalla fermata');
    });

    test('calcola fermate mancanti e superamento della salita', () => {
        const shape = { stops: [
            { id: 'A', name: 'A', lat: 45.000, lng: 12 },
            { id: 'B', name: 'B', lat: 45.010, lng: 12 },
            { id: 'C', name: 'C', lat: 45.020, lng: 12 }
        ] };
        expect(computeVehicleProgress({ lat: 45.0001, lon: 12 }, shape, 'C').stopsToBoarding).toBe(2);
        expect(computeVehicleProgress({ lat: 45.0201, lon: 12 }, shape, 'B').boardingPassed).toBe(true);
    });
});

describe('safeParseJSON', () => {
    let consoleSpy;
    beforeAll(() => {
        consoleSpy = jest.spyOn(console, 'error').mockImplementation(() => { });
    });
    afterAll(() => {
        consoleSpy.mockRestore();
    });

    test('parsa JSON valido', () => {
        expect(safeParseJSON('{"name":"test"}')).toEqual({ name: 'test' });
    });

    test('ritorna null per JSON invalido', () => {
        expect(safeParseJSON('invalid json')).toBeNull();
    });

    test('ritorna null per stringa vuota', () => {
        expect(safeParseJSON('')).toBeNull();
    });

    test('ritorna null per null', () => {
        expect(safeParseJSON(null)).toBeNull();
    });

    test('ritorna null per undefined', () => {
        expect(safeParseJSON(undefined)).toBeNull();
    });

    test('parsa un array JSON', () => {
        expect(safeParseJSON('[1,2,3]')).toEqual([1, 2, 3]);
    });
});

describe('getLineBadgeDetails', () => {
    test('linea urbana rossa (default)', () => {
        const result = getLineBadgeDetails('7_UM');
        expect(result.name).toBe('7');
        expect(result.class).toBe('badge-red');
    });

    test('linea extraurbana blu (US)', () => {
        const result = getLineBadgeDetails('5E_US');
        expect(result.name).toBe('5E');
        expect(result.class).toBe('badge-blue');
    });

    test('linea extraurbana blu (UN)', () => {
        const result = getLineBadgeDetails('8_UN');
        expect(result.name).toBe('8');
        expect(result.class).toBe('badge-blue');
    });

    test('linea extraurbana blu (EN)', () => {
        const result = getLineBadgeDetails('8E_EN');
        expect(result.name).toBe('8E');
        expect(result.class).toBe('badge-blue');
    });

    test('linea notturna', () => {
        const result = getLineBadgeDetails('N1_UM');
        expect(result.name).toBe('N1');
        expect(result.class).toBe('badge-night');
    });

    test('linea Cammina ritorna icona pedone', () => {
        const result = getLineBadgeDetails('Cammina');
        expect(result.name).toBe('🚶');
        expect(result.class).toBe('badge-walking');
    });

    test('input null ritorna fallback', () => {
        const result = getLineBadgeDetails(null);
        expect(result.name).toBe('?');
        expect(result.class).toBe('badge-red');
    });

    test('input undefined ritorna fallback', () => {
        const result = getLineBadgeDetails(undefined);
        expect(result.name).toBe('?');
        expect(result.class).toBe('badge-red');
    });
});

describe('formatItalianDate', () => {
    test('formatta una data valida', () => {
        const result = formatItalianDate('2025-12-25');
        expect(result).toContain('25');
    });

    test('ritorna "Invalid Date" per data non parsabile', () => {
        const result = formatItalianDate('not-a-date');
        expect(result).toBe('Invalid Date');
    });
});

describe('formatShortTime', () => {
    test('tronca orario a HH:MM', () => {
        expect(formatShortTime('14:30:00')).toBe('14:30');
    });

    test('ritorna --:-- per null', () => {
        expect(formatShortTime(null)).toBe('--:--');
    });

    test('ritorna --:-- per undefined', () => {
        expect(formatShortTime(undefined)).toBe('--:--');
    });

    test('ritorna --:-- per stringa vuota', () => {
        expect(formatShortTime('')).toBe('--:--');
    });

    test('gestisce orario già corto', () => {
        expect(formatShortTime('14:30')).toBe('14:30');
    });
});

describe('getTransferCount', () => {
    test('percorso diretto senza cambi', () => {
        const route = { legs: [{ type: 'transit', route_short_name: '2_US' }] };
        expect(getTransferCount(route)).toBe(0);
    });

    test('percorso con un cambio', () => {
        const route = { legs: [
            { type: 'transit', route_short_name: '2_US' },
            { type: 'transit', route_short_name: '5_UN' }
        ]};
        expect(getTransferCount(route)).toBe(1);
    });

    test('percorso con camminata e bus (no cambio)', () => {
        const route = { legs: [
            { type: 'walking', duration: 5 },
            { type: 'transit', route_short_name: '2_US' }
        ]};
        expect(getTransferCount(route)).toBe(0);
    });

    test('percorso senza legs', () => {
        expect(getTransferCount({})).toBe(0);
    });
});

describe('getWalkingMinutes', () => {
    test('percorso con camminata', () => {
        const route = { legs: [
            { type: 'walking', duration: 5 },
            { type: 'transit', duration: 20 },
            { type: 'walking', duration: 3 }
        ]};
        expect(getWalkingMinutes(route)).toBe(8);
    });

    test('percorso senza camminata', () => {
        const route = { legs: [{ type: 'transit', duration: 20 }] };
        expect(getWalkingMinutes(route)).toBe(0);
    });

    test('percorso senza legs', () => {
        expect(getWalkingMinutes({})).toBe(0);
    });
});

describe('findBestValues', () => {
    test('trova i valori migliori tra percorsi', () => {
        const routes = [
            { duration: 30, stops_count: 8, legs: [{ type: 'transit', stops_count: 8 }] },
            { duration: 25, stops_count: 5, legs: [{ type: 'walking', duration: 3 }, { type: 'transit', stops_count: 5 }] },
            { duration: 35, stops_count: 10, legs: [{ type: 'transit', stops_count: 4 }, { type: 'transit', stops_count: 6 }] }
        ];
        const best = findBestValues(routes);
        expect(best.duration).toBe(25);
        expect(best.stops).toBe(5);
        expect(best.transfers).toBe(0);
        expect(best.walking).toBe(0);
    });
});
