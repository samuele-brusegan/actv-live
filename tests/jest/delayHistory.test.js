/**
 * Test per delayHistory.js
 * Funzioni testate: gestione storico ritardi e statistiche
 */

const {
    getDelayHistory, recordDelay, getStatsByLine, getStatsByHour,
    getOverallStats, clearDelayHistory, parseDelayMinutes, recordPassageDelays
} = require('../../public/js/delayHistory');

beforeEach(() => {
    localStorage.clear();
});

describe('getDelayHistory', () => {
    test('ritorna array vuoto se non ci sono dati', () => {
        expect(getDelayHistory()).toEqual([]);
    });

    test('ritorna dati salvati', () => {
        const data = [{ line: '2_US', delay: 5, timestamp: Date.now(), hour: 10 }];
        localStorage.setItem('delay_history', JSON.stringify(data));
        expect(getDelayHistory()).toEqual(data);
    });

    test('gestisce JSON corrotto', () => {
        localStorage.setItem('delay_history', 'broken{{{');
        expect(getDelayHistory()).toEqual([]);
    });
});

describe('recordDelay', () => {
    test('aggiunge un record', () => {
        recordDelay({ line: '2_US', delay: 5, timestamp: 1000000 });
        expect(getDelayHistory()).toHaveLength(1);
        expect(getDelayHistory()[0].line).toBe('2_US');
    });

    test('ignora record senza linea', () => {
        recordDelay({ delay: 5 });
        expect(getDelayHistory()).toHaveLength(0);
    });

    test('ignora record senza ritardo', () => {
        recordDelay({ line: '2_US' });
        expect(getDelayHistory()).toHaveLength(0);
    });

    test('aggiunge record multipli', () => {
        recordDelay({ line: '2_US', delay: 5, timestamp: 1000 });
        recordDelay({ line: '5_UM', delay: 3, timestamp: 2000 });
        expect(getDelayHistory()).toHaveLength(2);
    });
});

describe('parseDelayMinutes', () => {
    test("parsa formato ACTV '4'", () => {
        expect(parseDelayMinutes("4'")).toBe(4);
    });

    test("parsa formato ACTV '12'", () => {
        expect(parseDelayMinutes("12'")).toBe(12);
    });

    test('ritorna null per departure', () => {
        expect(parseDelayMinutes('departure')).toBeNull();
    });

    test('ritorna null per stringa vuota', () => {
        expect(parseDelayMinutes('')).toBeNull();
    });

    test('ritorna null per null', () => {
        expect(parseDelayMinutes(null)).toBeNull();
    });
});

describe('getStatsByLine', () => {
    test('ritorna array vuoto senza dati', () => {
        expect(getStatsByLine()).toEqual([]);
    });

    test('calcola media per linea', () => {
        recordDelay({ line: '2_US', delay: 4, timestamp: 1000 });
        recordDelay({ line: '2_US', delay: 6, timestamp: 2000 });
        recordDelay({ line: '5_UM', delay: 2, timestamp: 3000 });

        const stats = getStatsByLine();
        expect(stats).toHaveLength(2);

        const line2 = stats.find(s => s.line === '2');
        expect(line2.avgDelay).toBe(5);
        expect(line2.count).toBe(2);
    });

    test('ordina per ritardo medio decrescente', () => {
        recordDelay({ line: '2_US', delay: 10, timestamp: 1000 });
        recordDelay({ line: '5_UM', delay: 2, timestamp: 2000 });

        const stats = getStatsByLine();
        expect(stats[0].line).toBe('2');
        expect(stats[1].line).toBe('5');
    });
});

describe('getStatsByHour', () => {
    test('ritorna 24 elementi', () => {
        expect(getStatsByHour()).toHaveLength(24);
    });

    test('calcola media per ora', () => {
        const ts = new Date();
        ts.setHours(14, 0, 0, 0);
        recordDelay({ line: '2_US', delay: 6, timestamp: ts.getTime() });
        recordDelay({ line: '2_US', delay: 4, timestamp: ts.getTime() });

        const stats = getStatsByHour();
        expect(stats[14].avgDelay).toBe(5);
        expect(stats[14].count).toBe(2);
    });
});

describe('getOverallStats', () => {
    test('ritorna default senza dati', () => {
        const stats = getOverallStats();
        expect(stats.totalRecords).toBe(0);
        expect(stats.avgDelay).toBe(0);
    });

    test('calcola statistiche generali', () => {
        recordDelay({ line: '2_US', delay: 4, timestamp: Date.now() });
        recordDelay({ line: '2_US', delay: 6, timestamp: Date.now() });

        const stats = getOverallStats();
        expect(stats.totalRecords).toBe(2);
        expect(stats.avgDelay).toBe(5);
        expect(stats.maxDelay).toBe(6);
    });
});

describe('clearDelayHistory', () => {
    test('cancella tutti i dati', () => {
        recordDelay({ line: '2_US', delay: 5, timestamp: 1000 });
        clearDelayHistory();
        expect(getDelayHistory()).toEqual([]);
    });
});

describe('recordPassageDelays', () => {
    test('registra ritardi da passaggi reali', () => {
        const passages = [
            { line: '2_US', destination: 'Lido', real: true, time: "5'" },
            { line: '5_UM', destination: 'Roma', real: true, time: "3'" },
            { line: '1_US', destination: 'Test', real: false, time: "10'" }
        ];
        recordPassageDelays(passages, '4825', 'Piazzale Roma');
        expect(getDelayHistory()).toHaveLength(2);
    });

    test('ignora passaggi non real-time', () => {
        const passages = [
            { line: '2_US', destination: 'Lido', real: false, time: "5'" }
        ];
        recordPassageDelays(passages, '4825', 'Piazzale Roma');
        expect(getDelayHistory()).toHaveLength(0);
    });

    test('ignora passaggi con departure', () => {
        const passages = [
            { line: '2_US', destination: 'Lido', real: true, time: 'departure' }
        ];
        recordPassageDelays(passages, '4825', 'Piazzale Roma');
        expect(getDelayHistory()).toHaveLength(0);
    });

    test('gestisce array vuoto', () => {
        recordPassageDelays([], '4825', 'Test');
        expect(getDelayHistory()).toHaveLength(0);
    });
});
