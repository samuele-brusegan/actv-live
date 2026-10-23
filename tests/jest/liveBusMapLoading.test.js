const fs = require('fs');
const vm = require('vm');
const source = fs.readFileSync(require.resolve('../../public/js/liveBusMap'), 'utf8');
const view = fs.readFileSync(require.resolve('../../app/views/liveBusMap.php'), 'utf8');
let context, active, initialize;

beforeEach(() => {
    document.documentElement.innerHTML = view.replace(/<\?php[\s\S]*?\?>/g, '').replace(/<script[\s\S]*?<\/script>/g, '');
    localStorage.clear();
    localStorage.setItem('actv-live-map-service', 'automobilistico');
    active = new Set();
    const map = { setView() { return this; }, on() {}, removeLayer(layer) { active.delete(layer); }, getBounds: () => ({ contains: () => false }) };
    context = vm.createContext({
        document: new Proxy(document, { get(target, key) {
            if (key === 'addEventListener') return (event, callback) => { if (event === 'DOMContentLoaded') initialize = callback; };
            const value = target[key]; return typeof value === 'function' ? value.bind(target) : value;
        } }),
        window, localStorage, URLSearchParams, Date, AbortController, console,
        setInterval: jest.fn(), clearInterval: jest.fn(), setTimeout, clearTimeout,
        fetch: jest.fn(),
        L: {
            map: () => map, tileLayer: () => ({ addTo() {} }), divIcon: options => options,
            marker: (point, options) => ({ point, options, popup: '',
                addTo() { active.add(this); return this; },
                bindPopup(html) { this.popup = html; return this; }, on() {}, getElement: () => null,
                getLatLng() { return { lat: this.point[0], lng: this.point[1] }; }
            })
        }
    });
    window.history.replaceState({}, '', '/live-map');
    vm.runInContext(source, context);
});

const settle = async () => { for (let i=0; i<40; i++) await Promise.resolve(); };
const response = data => ({ ok: true, json: async () => data });
const gps = (id, extra={}) => ({ vehicle_id:id, route_id:'6', route_short_name:'6', trip_id:'t1', vehicle_position:{lat:45,lon:12}, position_timestamp:Date.now()/1000, ...extra });

test('carica due mezzi della stessa linea senza trip e rimuove quelli scomparsi al refresh', async () => {
    context.fetch.mockResolvedValue(response({ vehicles: [gps('A',{trip_id:''}), gps('B',{trip_id:''})] }));
    initialize(); await settle();
    expect(active.size).toBe(2);
    expect(document.getElementById('counter-text').textContent).toContain('2 bus realtime');
    context.fetch.mockResolvedValue(response({ vehicles: [gps('B',{trip_id:''})] }));
    document.getElementById('btn-refresh').click(); await settle();
    expect(active.size).toBe(1);
});

test.each(['empty', 'offline'])('usa posizioni esplicitamente stimate quando il feed è %s', async mode => {
    context.fetch.mockImplementation(async url => {
        if (url.includes('/api/realtime/vehicles')) {
            if (mode === 'offline') throw new Error('offline');
            return response({ vehicles: [] });
        }
        if (url === '/api/gtfs-bnr') return response({ time:'23:55:00', buses:[{trip_id:'t1',route_short_name:'6',route_id:'r6',trip_headsign:'B'}] });
        if (url.includes('/api/bus-position')) return response({ shape:[],stops:[
            { stop_id:'1',stop_name:'A',arrival_time:'23:50:00',stop_lat:45,stop_lon:12,data_url:null },
            { stop_id:'2',stop_name:'B',arrival_time:'24:10:00',stop_lat:46,stop_lon:13,data_url:null }
        ] });
        return response([]);
    });
    initialize(); await settle();
    expect(active.size).toBe(1);
    expect([...active][0].point[0]).toBeCloseTo(45.25);
    expect(document.getElementById('counter-text').textContent).toContain('posizione stimata');
    expect([...active][0].options.icon.html).toContain('scheduled');
});

test('azzera i marker se falliscono sia il feed sia il fallback', async () => {
    context.fetch.mockResolvedValue(response({ vehicles:[gps('A')] }));
    initialize(); await settle();
    expect(active.size).toBe(1);
    context.fetch.mockRejectedValue(new Error('offline'));
    document.getElementById('btn-refresh').click(); await settle();
    expect(active.size).toBe(0);
    expect(document.getElementById('counter-text').textContent).toBe('Errore di caricamento');
});
