/**
 * Test per notifications.js
 * Funzioni testate: gestione preferenze notifiche, fermate monitorate, soglia ritardo
 */

const {
    areNotificationsEnabled, setNotificationsEnabled,
    getDelayThreshold, setDelayThreshold,
    getMonitoredStops, addMonitoredStop, removeMonitoredStop, isStopMonitored
} = require('../../public/js/notifications');

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
