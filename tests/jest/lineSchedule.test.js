const { getDistinctiveStops, groupVariantsByRoute } = require('../../public/js/lineSchedule');

describe('getDistinctiveStops', () => {
    const variantA = {
        stops: [
            { id: 'origin', name: 'MESTRE CENTRO B3' },
            { id: 'common', name: 'Olmo' },
            { id: 'a1', name: 'Zelarino Parolari' },
            { id: 'end', name: 'Noale' }
        ]
    };
    const variantB = {
        stops: [
            { id: 'origin', name: 'MESTRE CENTRO B3' },
            { id: 'common', name: 'Olmo' },
            { id: 'b1', name: 'Tito Castellana' },
            { id: 'end', name: 'Noale' }
        ]
    };

    test('mostra solo le fermate non condivise tra varianti comparabili', () => {
        expect(getDistinctiveStops(variantA, [variantA, variantB])).toEqual(['Zelarino Parolari']);
        expect(getDistinctiveStops(variantB, [variantA, variantB])).toEqual(['Tito Castellana']);
    });

    test('non mostra differenze senza almeno due varianti comparabili', () => {
        expect(getDistinctiveStops(variantA, [variantA])).toEqual([]);
    });
});

describe('groupVariantsByRoute', () => {
    const stops = names => names.map(name => ({ name }));
    const common = ['San Rocco', 'Castellana', 'Maerne', 'Noale'];

    test('unisce deviazioni ed estensioni collegate dello stesso percorso', () => {
        const variants = [
            { trip_id: 'a-long', origin: 'VENEZIA', terminus: 'Noale', stops: stops(['VENEZIA', 'MESTRE B4', 'Zelarino', ...common]) },
            { trip_id: 'a-short', origin: 'MESTRE B3', terminus: 'Noale', stops: stops(['MESTRE B3', 'Zelarino', ...common]) },
            { trip_id: 'b-long', origin: 'VENEZIA', terminus: 'Noale', stops: stops(['VENEZIA', 'MESTRE B4', 'Tito', ...common]) },
            { trip_id: 'b-short', origin: 'MESTRE B3', terminus: 'Noale', stops: stops(['MESTRE B3', 'Tito', ...common]) }
        ];

        const groups = groupVariantsByRoute(variants).map(group => group.map(v => v.trip_id).sort());
        expect(groups).toEqual([
            ['a-long', 'a-short', 'b-long', 'b-short']
        ]);
    });

    test('unisce deviazioni intermedie con gli stessi capolinea', () => {
        const variants = [
            { trip_id: 'direct', origin: 'Noale', terminus: 'VENEZIA', stops: stops(['Noale', 'Maerne', 'Mestre', 'VENEZIA']) },
            { trip_id: 'short-detour', origin: 'Noale', terminus: 'VENEZIA', stops: stops(['Noale', 'Robegano', 'Maerne', 'Mestre', 'VENEZIA']) },
            { trip_id: 'long-detour', origin: 'Noale', terminus: 'VENEZIA', stops: stops(['Noale', 'Martellago', 'Olmo', 'Mestre', 'VENEZIA']) }
        ];

        expect(groupVariantsByRoute(variants).map(group => group.map(v => v.trip_id))).toEqual([
            ['direct', 'short-detour', 'long-detour']
        ]);
    });

    test('aggrega transitivamente una tratta accorciata alla famiglia', () => {
        const variants = [
            { trip_id: 'noale-a', origin: 'Noale', terminus: 'Mestre', stops: stops(['Noale', 'Robegano', 'Maerne', 'Olmo', 'Mestre']) },
            { trip_id: 'noale-b', origin: 'Noale', terminus: 'Mestre', stops: stops(['Noale', 'Robegano', 'Maerne', 'Tito', 'Mestre']) },
            { trip_id: 'maerne', origin: 'Maerne FS', terminus: 'Mestre', stops: stops(['Maerne FS', 'Maerne', 'Olmo', 'Mestre']) }
        ];

        expect(groupVariantsByRoute(variants).map(group => group.map(v => v.trip_id).sort())).toEqual([
            ['maerne', 'noale-a', 'noale-b']
        ]);
    });
});

test('complete timetable loads automatically, searches locally and shows every passage at a stop', async () => {
    const fs = require('fs');
    const view = fs.readFileSync(require('path').join(__dirname, '../../app/views/lineSchedule.php'), 'utf8');
    document.body.innerHTML = view.match(/<body[^>]*>([\s\S]*)<\/body>/)[1];
    localStorage.clear();
    const stops = [{ id: 'a', name: 'Partenza' }, { id: 'b', name: 'Arrivo' }];
    global.fetch = jest.fn(async url => ({ ok: true, json: async () => {
        if (url.includes('line-catalog')) return {success:true, lines:[{line:'1', name:'Centro'}]};
        if (url.includes('line-variants')) return {success:true, variants:[{trip_id:'t1', headsign:'Arrivo', origin:'Partenza', terminus:'Arrivo', stops, trips_count:2}]};
        return {success:true, headsign:'Arrivo', stops, runs:[
            {trip_id:'t1', start:'06:00', times:['06:00','06:30'], calendar:'Lun · Ven'},
            {trip_id:'t2', start:'25:10', times:['25:10','25:40'], calendar:'Dom'},
        ]};
    }}));
    document.dispatchEvent(new Event('DOMContentLoaded'));
    const settle = async () => { for (let i=0; i<12; i++) await Promise.resolve(); };
    await settle();
    const search = document.getElementById('line-search');
    search.value = 'Centro'; search.dispatchEvent(new Event('input'));
    expect(fetch).toHaveBeenCalledTimes(1);
    const line = document.getElementById('line-input');
    line.value = '1'; line.dispatchEvent(new Event('change'));
    await settle();
    expect(document.querySelectorAll('.timetable tbody tr')).toHaveLength(2);
    expect(document.querySelectorAll('.run-map-link')).toHaveLength(2);
    expect(document.querySelectorAll('.run-calendar')[1].title).toBe('Dom');
    const stop = document.getElementById('stop-picker');
    stop.value = '1'; stop.dispatchEvent(new Event('change'));
    expect(document.getElementById('stop-panel').textContent).toContain('25:40');
    expect(document.querySelectorAll('.stop-times a')).toHaveLength(2);
    expect(document.querySelector('[type="date"], [type="time"]')).toBeNull();
    for (const [url] of fetch.mock.calls) {
        const params = new URL(url, 'http://localhost').searchParams;
        expect(params.get('all')).toBe('1');
        expect(params.has('date')).toBe(false);
        expect(params.has('day')).toBe(false);
        expect(params.has('time')).toBe(false);
    }
});

test('identical departures share a column while retaining every service calendar', () => {
    const { groupIdenticalRuns } = require('../../public/js/lineSchedule');
    const runs = groupIdenticalRuns([
        { trip_id:'weekday', start:'08:00', times:['08:00', '08:30'], calendar:'Lun · Ven' },
        { trip_id:'sunday', start:'08:00', times:['08:00', '08:30'], calendar:'Dom' },
        { trip_id:'short', start:'08:00', times:[null, '08:30'], calendar:'Dom' },
    ]);
    expect(runs).toHaveLength(2);
    expect(runs[0].calendar).toBe('Lun · Ven / Dom');
    expect(runs[1].times).toEqual([null, '08:30']);
});
