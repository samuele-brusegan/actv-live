/**
 * Test per routeResults.js
 * Funzioni testate: safeParseJSON, getLineBadgeDetails, formatItalianDate, formatShortTime
 */
const { safeParseJSON, getLineBadgeDetails, formatItalianDate, formatShortTime } = require('../public/js/routeResults');

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
