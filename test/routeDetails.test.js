/**
 * Test per routeDetails.js
 * Funzioni testate: formatItalianDate, renderTimelineStep, renderStopStep
 */
const { formatItalianDate, renderTimelineStep, renderStopStep } = require('../public/js/routeDetails');

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
        // new Date('not-a-date').toLocaleDateString() restituisce "Invalid Date"
        const result = formatItalianDate('not-a-date');
        expect(result).toBe('Invalid Date');
    });
});

describe('renderTimelineStep', () => {
    test('genera HTML per step di tipo location', () => {
        const html = renderTimelineStep({ icon: 'location', title: 'Posizione attuale', connector: 'dashed' });
        expect(html).toContain('Posizione attuale');
        expect(html).toContain('↘');
        expect(html).toContain('timeline-connector');
        expect(html).toContain('dashed');
    });

    test('genera HTML per step di tipo walk', () => {
        const html = renderTimelineStep({ icon: 'walk', title: 'Cammina', subtitle: 'circa 800 metri' });
        expect(html).toContain('🚶');
        expect(html).toContain('Cammina');
        expect(html).toContain('circa 800 metri');
    });

    test('genera HTML senza connector', () => {
        const html = renderTimelineStep({ icon: 'location_end', title: 'Destinazione' });
        expect(html).toContain('Destinazione');
        expect(html).toContain('↖');
        expect(html).not.toContain('timeline-connector');
    });

    test('genera HTML per step circle', () => {
        const html = renderTimelineStep({ icon: 'circle', title: 'Fermata intermedia', connector: 'dashed' });
        expect(html).toContain('Fermata intermedia');
        expect(html).toContain('icon-circle');
    });
});

describe('renderStopStep', () => {
    test('genera HTML per fermata con info linea', () => {
        const html = renderStopStep('Piazzale Roma', '5E', 8, 15);
        expect(html).toContain('Piazzale Roma');
        expect(html).toContain('5E');
        expect(html).toContain('8');
        expect(html).toContain('15 min');
    });

    test('genera HTML per fermata senza linea', () => {
        const html = renderStopStep('Test Stop', null, null, null);
        expect(html).toContain('Test Stop');
        expect(html).not.toContain('Prendi la linea');
    });

    test('contiene struttura timeline corretta', () => {
        const html = renderStopStep('Fermata', '10', 5, 10);
        expect(html).toContain('timeline-item');
        expect(html).toContain('timeline-content');
        expect(html).toContain('timeline-title');
    });
});
