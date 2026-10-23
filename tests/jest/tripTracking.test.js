const fs = require('fs');
const vm = require('vm');
const source = fs.readFileSync(require.resolve('../../public/js/tripDetails'), 'utf8');
let context;

beforeEach(() => {
    document.body.innerHTML = '<div id="trip-map-status"></div><div class="trip-map-stops-legend"></div><div id="trip-map-stops-list"></div><span id="trip-map-stops-count"></span><div id="stops-container"></div>';
    const layer = () => ({ addTo() { return this; }, bindPopup() { return this; }, setLatLng: jest.fn(), setLatLngs: jest.fn() });
    context = vm.createContext({
        document: { addEventListener() {}, getElementById: document.getElementById.bind(document), createElement: document.createElement.bind(document), querySelector: document.querySelector.bind(document) },
        Date, URLSearchParams, console, setTimeout: jest.fn(), fetch: jest.fn(),
        L: { polyline: layer, marker: layer, divIcon: () => ({}) }
    });
    vm.runInContext(source, context);
    vm.runInContext(`
        state.tripId = 'requested'; state.line = '6';
        state.currentStopId = '2';
        state.stopsGTFS = [
            {stop_id:'1', stop_sequence:1, stop_name:'A', stop_lat:45, stop_lon:12, arrival_time:'01:00'},
            {stop_id:'2', stop_sequence:2, stop_name:'B', stop_lat:45, stop_lon:12.01, arrival_time:'01:05'},
            {stop_id:'3', stop_sequence:3, stop_name:'C', stop_lat:45, stop_lon:12.02, arrival_time:'01:10'}
        ];
        state.mergedStops = mergeStops();
        tripMap = {removeLayer() {}};
        tripMapGeometry = [[45,12],[45,12.01],[45,12.02]];
        tripMapStops = state.stopsGTFS;
        tripMapStopProgress = projectOrderedMapStops(tripMapStops, tripMapGeometry);
        tripMapRemainingRoute = L.polyline();
    `, context);
});

const vehicle = (extra = {}) => ({ trip_id: 'requested', route_id: '6', vehicle_position: { lat: 45, lon: 12.015 }, position_timestamp: Date.now() / 1000, ...extra });
const respond = vehicles => context.fetch.mockResolvedValue({ ok: true, json: async () => ({ vehicles }) });
const refresh = () => vm.runInContext('refreshTripVehicle()', context);

test('ignora un altro mezzo della stessa linea e richiede solo la corsa esatta', async () => {
    respond([vehicle({ trip_id: 'other' })]);
    await refresh();
    expect(context.fetch.mock.calls[0][0]).not.toContain('routeId=');
    expect(document.getElementById('trip-map-stops-count').textContent).toBe('–/3');
    expect(document.querySelectorAll('.trip-map-stop.visited')).toHaveLength(0);
    expect(document.querySelector('.trip-map-stops-legend').textContent).toContain('non verificato');
});

test('mappa e timeline condividono il progresso e lo azzerano alla perdita del GPS', async () => {
    respond([vehicle()]);
    await refresh();
    expect(document.getElementById('trip-map-stops-count').textContent).toBe('2/3');
    expect(document.querySelectorAll('.trip-map-stop.visited')).toHaveLength(2);
    expect(document.querySelectorAll('.stop-item.passed')).toHaveLength(2);
    respond([]);
    await refresh();
    expect(document.getElementById('trip-map-stops-count').textContent).toBe('–/3');
    expect(document.querySelectorAll('.stop-item.passed')).toHaveLength(0);
    expect(vm.runInContext('tripMapVehiclePosition', context)).toBeNull();
});

test('rimuove il progresso anche in caso di errore di rete', async () => {
    respond([vehicle()]); await refresh();
    context.fetch.mockRejectedValue(new Error('offline'));
    await refresh();
    expect(document.querySelectorAll('.stop-item.passed')).toHaveLength(0);
    expect(vm.runInContext('tripMapProgress', context)).toBeNull();
});

test('rifiuta associazioni ambigue, stime di linea, timestamp vecchi e coordinate nulle', () => {
    context.vehicles = [vehicle(), vehicle()];
    expect(vm.runInContext('selectTripVehicle(vehicles, state.tripId)', context)).toBeNull();
    for (const extra of [{ route_guess: true }, { position_timestamp: Date.now()/1000 - 121 }, { vehicle_position: {lat:null,lon:12} }]) {
        context.vehicles = [vehicle(extra)];
        expect(vm.runInContext('selectTripVehicle(vehicles, state.tripId)', context)).toBeNull();
    }
});

test('un orario trascorso non equivale a fermata superata e il rendering non modifica i countdown', () => {
    vm.runInContext(`
        state.mergedStops[0].arrival_time = new Date(Date.now()-10*60000).toTimeString().slice(0,5);
        state.mergedStops[1].arrival_time = "6'";
        state.mergedStops[1].hasRealTime = true;
        renderTimeline(); renderMapStops(tripMapStops);
    `, context);
    expect(document.querySelectorAll('.stop-item.passed')).toHaveLength(0);
    expect(document.querySelector('.stop-time').textContent).toContain('Orario trascorso');
    expect(vm.runInContext('state.mergedStops[1].arrival_time', context)).toBe("6'");
    expect(document.querySelectorAll('.stop-time')[1].textContent).toBe('6 min');
    expect(document.querySelectorAll('.trip-map-stop-eta strong')[1].textContent).toBe('6 min');
});

test('conserva la qualità programmata dei timing point del feed ACTV', async () => {
    context.fetch.mockImplementation(async url => ({ ok:true,
        json:async()=>[{ line:'6_UM', real:false, stop:'1', destination:'C', timingPoints:[{stop:'A',time:'01:00'}] }],
        text:async()=>JSON.stringify({trip_id:'requested'})
    }));
    await vm.runInContext(`fetchRealTimeInfo('1','6','monday').then(points => {state.stopsJSON=points;state.mergedStops=mergeStops();})`, context);
    expect(vm.runInContext('state.stopsJSON[0].isRealtime', context)).toBe(false);
    expect(vm.runInContext('state.mergedStops[0].hasRealTime', context)).toBe(false);
});

test('proietta le occorrenze ripetute in ordine lungo un anello', () => {
    const positions = vm.runInContext(`projectOrderedMapStops([
        {lat:45,lng:12},{lat:45,lng:12.01},{lat:45,lng:12}
    ], [[45,12],[45,12.01],[45.01,12.01],[45,12]])`, context);
    expect(positions.map(point => point.index + point.ratio)).toEqual([0,1,3]);
});

test('su un anello non inventa il progresso quando il GPS è ambiguo e usa la sequenza se disponibile', async () => {
    vm.runInContext(`
        tripMapGeometry = [[45,12],[45,12.01],[45.01,12.01],[45,12]];
        state.stopsGTFS = [
            {stop_id:'1',stop_sequence:1,stop_name:'A',lat:45,lng:12,arrival_time:'01:00'},
            {stop_id:'2',stop_sequence:2,stop_name:'B',lat:45,lng:12.01,arrival_time:'01:05'},
            {stop_id:'1',stop_sequence:3,stop_name:'A',lat:45,lng:12,arrival_time:'01:10'}
        ];
        state.mergedStops = mergeStops();
        tripMapStops = state.stopsGTFS;
        tripMapStopProgress = projectOrderedMapStops(tripMapStops,tripMapGeometry);
    `, context);
    respond([vehicle({vehicle_position:{lat:45,lon:12}})]);
    await refresh();
    expect(document.getElementById('trip-map-stops-count').textContent).toBe('–/3');
    respond([vehicle({vehicle_position:{lat:45,lon:12},current_stop_sequence:3})]);
    await refresh();
    expect(document.getElementById('trip-map-stops-count').textContent).toBe('2/3');
});
