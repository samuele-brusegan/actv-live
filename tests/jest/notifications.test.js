/**
 * Test per notifications.js
 * Funzioni testate: gestione preferenze notifiche, fermate monitorate, soglia ritardo
 */

const {
    areNotificationsEnabled, setNotificationsEnabled,
    getDelayThreshold, setDelayThreshold,
    getMonitoredStops, addMonitoredStop, removeMonitoredStop, isStopMonitored,
    detectDepartures, passageMinutes
} = require('../../public/js/notifications');

describe('passageMinutes', () => {
    test('"departure" vale 0', () => expect(passageMinutes('departure')).toBe(0));
    test('"5\'" vale 5', () => expect(passageMinutes("5'")).toBe(5));
    test('"12 min" vale 12', () => expect(passageMinutes('12 min')).toBe(12));
    test('valore non parsabile -> null', () => expect(passageMinutes('--')).toBeNull());
});

describe('detectDepartures (bus passato)', () => {
    test('nessuna partenza al primo confronto senza stato precedente', () => {
        const passages = [{ real: true, line: '2_US', destination: 'Mestre', time: '1\'' }];
        const { departed, state } = detectDepartures(undefined, passages);
        expect(departed).toHaveLength(0);
        expect(state['2|Mestre']).toBe(1);
    });

    test('rileva il bus partito quando l\'orario salta alla corsa successiva', () => {
        const prev = { '2|Mestre': 1 };
        const passages = [{ real: true, line: '2_US', destination: 'Mestre', time: '11\'' }];
        const { departed } = detectDepartures(prev, passages);
        expect(departed).toEqual([{ line: '2', dest: 'Mestre' }]);
    });

    test('rileva il bus partito quando sparisce dalla lista', () => {
        const prev = { '5|Marghera': 0 };
        const { departed } = detectDepartures(prev, []);
        expect(departed).toEqual([{ line: '5', dest: 'Marghera' }]);
    });

    test('nessuna partenza se il bus non era imminente', () => {
        const prev = { '2|Mestre': 8 };
        const passages = [{ real: true, line: '2_US', destination: 'Mestre', time: '15\'' }];
        const { departed } = detectDepartures(prev, passages);
        expect(departed).toHaveLength(0);
    });

    test('ignora i passaggi non real-time', () => {
        const { state } = detectDepartures({}, [{ real: false, line: '2', destination: 'Mestre', time: '10:05' }]);
        expect(Object.keys(state)).toHaveLength(0);
    });
});

beforeEach(() => {
    localStorage.clear();
});

describe('areNotificationsEnabled / setNotificationsEnabled', () => {
    test('default disabilitato', () => {
        expect(areNotificationsEnabled()).toBe(false);
    });

    test('abilita notifiche', () => {
        setNotificationsEnabled(true);
        expect(areNotificationsEnabled()).toBe(true);
    });

    test('disabilita notifiche', () => {
        setNotificationsEnabled(true);
        setNotificationsEnabled(false);
        expect(areNotificationsEnabled()).toBe(false);
    });
});

describe('getDelayThreshold / setDelayThreshold', () => {
    test('default 5 minuti', () => {
        expect(getDelayThreshold()).toBe(5);
    });

    test('imposta soglia personalizzata', () => {
        setDelayThreshold(10);
        expect(getDelayThreshold()).toBe(10);
    });

    test('imposta soglia 3 minuti', () => {
        setDelayThreshold(3);
        expect(getDelayThreshold()).toBe(3);
    });
});

describe('getMonitoredStops', () => {
    test('default lista vuota', () => {
        expect(getMonitoredStops()).toEqual([]);
    });

    test('ritorna fermate monitorate', () => {
        const stops = [{ id: '4825', name: 'Piazzale Roma' }];
        localStorage.setItem('monitored_stops', JSON.stringify(stops));
        expect(getMonitoredStops()).toEqual(stops);
    });

    test('gestisce JSON corrotto', () => {
        localStorage.setItem('monitored_stops', 'broken{{{');
        expect(getMonitoredStops()).toEqual([]);
    });
});

describe('addMonitoredStop / removeMonitoredStop', () => {
    test('aggiunge fermata', () => {
        addMonitoredStop('4825', 'Piazzale Roma');
        expect(getMonitoredStops()).toHaveLength(1);
        expect(getMonitoredStops()[0].id).toBe('4825');
    });

    test('non duplica fermata', () => {
        addMonitoredStop('4825', 'Piazzale Roma');
        addMonitoredStop('4825', 'Piazzale Roma');
        expect(getMonitoredStops()).toHaveLength(1);
    });

    test('aggiunge fermate multiple', () => {
        addMonitoredStop('4825', 'Piazzale Roma');
        addMonitoredStop('611', 'Giovannacci');
        expect(getMonitoredStops()).toHaveLength(2);
    });

    test('rimuove fermata', () => {
        addMonitoredStop('4825', 'Piazzale Roma');
        addMonitoredStop('611', 'Giovannacci');
        removeMonitoredStop('4825');
        expect(getMonitoredStops()).toHaveLength(1);
        expect(getMonitoredStops()[0].id).toBe('611');
    });
});

describe('isStopMonitored', () => {
    test('ritorna false se non monitorata', () => {
        expect(isStopMonitored('4825')).toBe(false);
    });

    test('ritorna true se monitorata', () => {
        addMonitoredStop('4825', 'Piazzale Roma');
        expect(isStopMonitored('4825')).toBe(true);
    });

    test('ritorna false dopo rimozione', () => {
        addMonitoredStop('4825', 'Piazzale Roma');
        removeMonitoredStop('4825');
        expect(isStopMonitored('4825')).toBe(false);
    });
});
