const listener = jest.spyOn(document, 'addEventListener').mockImplementation(() => {});
const { timeToSec, interpolateWithShape, vehicleKey, validVehiclePosition, matchesVehicleFilter } = require('../../public/js/liveBusMap');
listener.mockRestore();

const stops = (end = '24:10:00') => [
    { stop_id: '1', arrival_time: '23:50:00', stop_lat: 45, stop_lon: 12, stop_name: 'A', data_url: null },
    { stop_id: '2', arrival_time: end, stop_lat: 46, stop_lon: 13, stop_name: 'B', data_url: '2-3-web-aut' }
];

describe('posizioni stimate a mezzanotte', () => {
    test.each(['24:10:00', '00:10:00'])('mantiene il progresso prima e dopo mezzanotte (%s)', end => {
        expect(timeToSec('24:10:00')).toBe(87000);
        expect(interpolateWithShape(stops(end), [], timeToSec('23:55:00')).lat).toBeCloseTo(45.25);
        const after = interpolateWithShape(stops(end), [], timeToSec('00:05:00'));
        expect(after.lat).toBeCloseTo(45.75);
        expect(after.nextTime).toBe(end);
        expect(after.nextStopId).toBe('2-3');
        expect(interpolateWithShape(stops(end), [], timeToSec('00:11:00'))).toBeNull();
    });
    test('applica il ritardo sul giorno di servizio e gestisce data_url assente', () => {
        expect(interpolateWithShape(stops(), [], timeToSec('00:05:00'), 600).lat).toBeCloseTo(45.25);
        expect(interpolateWithShape(stops(), [], timeToSec('23:49:00')).nextStopId).toBe('1');
    });
    test('interpola lungo la shape conservando il prossimo orario', () => {
        const shape = [{ lat: 45, lng: 12 }, { lat: 45.5, lng: 12.5 }, { lat: 46, lng: 13 }];
        const position = interpolateWithShape(stops(), shape, timeToSec('00:05:00'));
        expect(position.lat).toBeGreaterThan(45.5);
        expect(position.lat).toBeLessThan(46);
        expect(position.nextTime).toBe('24:10:00');
    });
});

describe('identità, freschezza e filtri dei veicoli', () => {
    test('mantiene distinti due mezzi senza trip sulla stessa linea', () => {
        expect(vehicleKey({ vehicle_id: 'A', route_id: '6' })).not.toBe(vehicleKey({ vehicle_id: 'B', route_id: '6' }));
        expect(vehicleKey({ entity_id: 'A', route_id: '6' })).not.toBe(vehicleKey({ entity_id: 'B', route_id: '6' }));
        expect(vehicleKey({ route_id: '6' })).toBeNull();
    });
    test('rifiuta posizioni vecchie, future e coordinate assenti', () => {
        const vehicle = { vehicle_position: { lat: 45, lon: 12 }, position_timestamp: 1000 };
        expect(validVehiclePosition(vehicle, 1100)).toBe(true);
        expect(validVehiclePosition(vehicle, 1121)).toBe(false);
        expect(validVehiclePosition(vehicle, 900)).toBe(false);
        expect(validVehiclePosition({ vehicle_position: { lat: null, lon: 12 } })).toBe(false);
        expect(validVehiclePosition({ vehicle_position: { lat: 95, lon: 12 } })).toBe(false);
    });
    test('applica i filtri URL esatti anche al feed realtime', () => {
        const vehicle = { trip_id: 't1', route_short_name: '6', trip_headsign: 'Venezia' };
        expect(matchesVehicleFilter(vehicle, '', new URLSearchParams('tripId=t1&line=6&destination=venezia'))).toBe(true);
        expect(matchesVehicleFilter(vehicle, '', new URLSearchParams('tripId=t'))).toBe(false);
        expect(matchesVehicleFilter(vehicle, '', new URLSearchParams('line=6L'))).toBe(false);
        expect(matchesVehicleFilter(vehicle, '', new URLSearchParams('destination=Mestre'))).toBe(false);
    });
});

describe('ricerca multipla e campi selezionabili', () => {
    const fields = { line: true, destination: false, tripId: false };
    test('cerca più linee esatte senza includere linee con numeri simili', () => {
        for (const line of ['5E', '21', '5E_UN']) {
            expect(matchesVehicleFilter({route_short_name: line}, '5e, 21', undefined, fields)).toBe(true);
        }
        expect(matchesVehicleFilter({route_short_name: '121'}, '5E, 21', undefined, fields)).toBe(false);
    });
    test('interroga soltanto i campi abilitati, con OR tra termini e campi', () => {
        const bus = { route_short_name: '21', trip_headsign: 'Venezia Piazzale Roma', trip_id: 'corsa-123' };
        expect(matchesVehicleFilter(bus, 'Roma', undefined, fields)).toBe(false);
        expect(matchesVehicleFilter(bus, 'Mestre, roma', undefined, {destination: true})).toBe(true);
        expect(matchesVehicleFilter(bus, '123', undefined, {tripId: true})).toBe(true);
        expect(matchesVehicleFilter(bus, '21', undefined, {})).toBe(false);
        expect(matchesVehicleFilter(bus, '', undefined, {})).toBe(true);
        expect(matchesVehicleFilter({}, '21')).toBe(false);
    });
    test('mantiene i vincoli dei link con tutti i campi disattivati', () => {
        expect(matchesVehicleFilter({trip_id:'t1'}, 't1', new URLSearchParams('tripId=t1'), {})).toBe(true);
        expect(matchesVehicleFilter({trip_id:'t2'}, 't1', new URLSearchParams('tripId=t1'), {})).toBe(false);
    });
});
