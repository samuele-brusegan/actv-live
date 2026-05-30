/**
 * Test per routeDetails.js
 * Funzioni testate: formatItalianDate, formatShortTime, getLineBadge, renderLeg
 */
const {
    formatItalianDate, formatShortTime, getLineBadge, renderLeg,
    haversineMeters, getBoardingTime, computeLeaveInfo
} = require('../../public/js/routeDetails');

describe('formatItalianDate (routeDetails)', () => {
    test('formatta una data valida', () => {
        const result = formatItalianDate('2025-06-15');
        expect(result).toContain('15');
    });

    test('ritorna stringa vuota per null', () => {
        expect(formatItalianDate(null)).toBe('');
    });

    test('ritorna stringa vuota per undefined', () => {
        expect(formatItalianDate(undefined)).toBe('');
    });

    test('ritorna "Invalid Date" per data non parsabile', () => {
        const result = formatItalianDate('not-a-date');
        expect(result).toBe('Invalid Date');
    });
});

describe('formatShortTime', () => {
    test('taglia ai primi 5 caratteri (HH:MM)', () => {
        expect(formatShortTime('08:30:00')).toBe('08:30');
    });

    test('placeholder se vuoto', () => {
        expect(formatShortTime('')).toBe('--:--');
    });
});

describe('getLineBadge (convenzioni colori)', () => {
    test('5E è blu (extraurbano)', () => {
        expect(getLineBadge('5E').class).toBe('badge-blue');
    });

    test('linea urbana numerica è rossa', () => {
        expect(getLineBadge('2').class).toBe('badge-red');
    });

    test('tag US -> blu e nome senza suffisso', () => {
        const b = getLineBadge('7E_US');
        expect(b.name).toBe('7E');
        expect(b.class).toBe('badge-blue');
    });

    test('linea notturna -> badge-night', () => {
        expect(getLineBadge('N1').class).toBe('badge-night');
    });

    test('Cammina -> badge-walking', () => {
        expect(getLineBadge('Cammina').class).toBe('badge-walking');
    });
});

describe('renderLeg', () => {
    test('tratta diretta contiene badge, fermate e marcatori', () => {
        const leg = {
            type: 'bus', route_short_name: '5E', stops_count: 20,
            departure_time: '12:41:00', arrival_time: '12:59:00',
            origin: 'Maerne Chiesa', destination: 'Mestre Centro'
        };
        const html = renderLeg(leg, 0, 1, 'Mestre Centro');
        expect(html).toContain('Maerne Chiesa');
        expect(html).toContain('badge-blue');
        expect(html).toContain('5E');
        expect(html).toContain('20 fermate');
        expect(html).toContain('12:41');
        expect(html).toContain('timeline-marker start');
        expect(html).toContain('timeline-marker end');
    });

    test('tratta a piedi mostra info camminata', () => {
        const leg = {
            type: 'walking', route_short_name: 'Cammina', duration: 8, distance: 600,
            departure_time: '12:00:00', arrival_time: '12:08:00', origin: 'A', destination: 'B'
        };
        const html = renderLeg(leg, 0, 1, 'B');
        expect(html).toContain('Cammina per 8 min');
        expect(html).toContain('badge-walking');
    });
});

describe('haversineMeters', () => {
    test('distanza nulla per lo stesso punto', () => {
        expect(haversineMeters(45.5, 12.2, 45.5, 12.2)).toBeCloseTo(0, 3);
    });

    test('distanza positiva tra due punti distinti', () => {
        expect(haversineMeters(45.50, 12.2, 45.51, 12.2)).toBeGreaterThan(1000);
    });
});

describe('getBoardingTime', () => {
    test('salta la tratta a piedi iniziale', () => {
        const route = { legs: [{ type: 'walking', departure_time: '12:00:00' }, { type: 'bus', departure_time: '12:10:00' }] };
        expect(getBoardingTime(route)).toBe('12:10:00');
    });
});

describe('computeLeaveInfo', () => {
    const route = { legs: [{ type: 'bus', departure_time: '12:30:00' }] };

    test('ritorna null se la fermata è troppo lontana', () => {
        const r = computeLeaveInfo(route, { lat: 45.50, lng: 12.20 }, { lat: 45.60, lng: 12.30 });
        expect(r).toBeNull();
    });

    test('ritorna null senza coordinate utente', () => {
        expect(computeLeaveInfo(route, { lat: 45.5, lng: 12.2 }, null)).toBeNull();
    });

    test('calcola orario di partenza consigliato se vicino', () => {
        const origin = { lat: 45.5000, lng: 12.2000 };
        const user = { lat: 45.5012, lng: 12.2000 }; // ~133 m
        const r = computeLeaveInfo(route, origin, user);
        expect(r).not.toBeNull();
        expect(r.walkMin).toBe(2);
        expect(r.boarding).toBe('12:30');
        expect(r.leaveBy).toBe('12:26');
    });
});
