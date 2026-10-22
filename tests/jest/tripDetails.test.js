/**
 * Test per tripDetails.js
 * Funzioni testate: formatMinutesRemaining, mergeStops
 */

// Usiamo history.pushState per cambiare l'URL in jsdom
window.history.pushState({}, 'Test', '?tripId=123');

const {
    formatMinutesRemaining,
    getContrastTextColor,
    getMapStopArrowRotation,
    isMapTerminalStop,
    mergeStops,
    normalizeMapColor,
    normalizeStopName,
    selectMatchingTripTimingPoints,
    selectPreviousStopTrip,
    getPreviousStopsRealTime,
    fetchRealTimeInfo,
    updateHeader,
    state
} = require('../../public/js/tripDetails');

describe('realtime delle fermate precedenti', () => {
    test('Pertini: il passaggio stop 1258 aggiorna solo il ritorno, mai lo stop 258 prima di Carrer', async () => {
        const originalFetch = global.fetch;
        const tail = [{ stop: 'Bissuola Cadorna', time: '23:45' }, { stop: 'MESTRE CENTRO B7', time: '23:48' }];
        const passage = { line: '31H_UM', real: true, stop: '1258', time: "6'",
            timingPoints: [{ stop: 'Pertini Gavagnin', time: '23:37' }, ...tail] };
        global.fetch = jest.fn(async () => ({ json: async () => [passage] }));
        state.lineFull = '31H_UM';
        state.currentStopId = '1270';
        state.stopsJSON = tail;
        const outward = { stop_id: '258', data_url: '258-1258-web-aut', arrival_time: '23:31' };
        const inward = { stop_id: '1258', data_url: '258-1258-web-aut', arrival_time: '23:38' };
        state.mergedStops = [outward, { stop_id: '3568', rtTimeline: true, arrival_time: "1'" }, inward, { stop_id: '1270' }];
        try {
            await getPreviousStopsRealTime();
            expect(outward.hasRealTime).toBeUndefined();
            expect(outward.arrival_time).toBe('23:31');
            expect(inward.hasRealTime).toBe(true);
            expect(inward.arrival_time).toBe("6'");
        } finally { global.fetch = originalFetch; }
    });

    test('senza ID rifiuta un feed unito, con ID scarta la direzione opposta prima della disambiguazione', () => {
        const stop = { stop_id: '1258', data_url: '258-1258-web-aut' };
        const valid = candidate('23:13', { stop: '1258' });
        const opposite = candidate('23:13', { stop: '258' });
        expect(selectPreviousStopTrip([candidate('23:13')], '6_UM', timeline, stop)).toBeNull();
        expect(selectPreviousStopTrip([opposite, valid], '6_UM', timeline, stop)).toBe(valid);
    });
    const timeline = [
        { stop: 'Giovannacci Ulloa', time: '23:22' },
        { stop: 'Risorgimento Miranese', time: '23:35' }
    ];
    const candidate = (time, extra = {}) => ({
        line: '6_UM', real: true, time,
        timingPoints: [{ stop: 'VENEZIA', time }, ...timeline], ...extra
    });

    test('rifiuta Venezia 11 minuti quando Giovannacci e tra 8 minuti', () => {
        expect(selectPreviousStopTrip([candidate('23:25')], '6_UM', timeline)).toBeNull();
    });

    test('accetta solo un seguito coerente con la timeline scelta', () => {
        const valid = candidate('23:13');
        const next = candidate('23:25', { timingPoints: [
            { stop: 'VENEZIA', time: '23:25' },
            { stop: 'Giovannacci Ulloa', time: '23:36' },
            { stop: 'Risorgimento Miranese', time: '23:48' }
        ] });
        expect(selectPreviousStopTrip([next, valid], '6_UM', timeline)).toBe(valid);
        expect(selectPreviousStopTrip([next], '6_UM', timeline)).toBeNull();
    });

    test('rifiuta dati programmati, direzione opposta e candidati ambigui', () => {
        expect(selectPreviousStopTrip([candidate('23:13', { real: false })], '6_UM', timeline)).toBeNull();
        expect(selectPreviousStopTrip([candidate('23:13', { timingPoints: [
            { stop: "Liberta' Santa Chiara", time: '23:13' },
            { stop: 'VENEZIA', time: '23:16' }
        ] })], '6_UM', timeline)).toBeNull();
        expect(selectPreviousStopTrip([candidate('23:13'), candidate('23:14')], '6_UM', timeline)).toBeNull();
    });

    test('verifica la monotonicita anche a mezzanotte con ore GTFS oltre 24', () => {
        const midnight = [{ stop: 'Giovannacci Ulloa', time: '24:02' }];
        const before = candidate('23:55', { timingPoints: [
            { stop: 'VENEZIA', time: '23:55' }, { stop: 'Giovannacci Ulloa', time: '00:02' }
        ] });
        expect(selectPreviousStopTrip([before], '6_UM', midnight)).toBe(before);
        expect(selectPreviousStopTrip([{ ...before, time: '00:05' }], '6_UM', midnight)).toBeNull();
    });

    test('ignora risposte arrivate dopo il refresh della timeline', async () => {
        const originalFetch = global.fetch;
        let finish;
        global.fetch = jest.fn(() => new Promise(resolve => { finish = resolve; }));
        state.currentStopId = '611';
        state.lineFull = '6_UM';
        state.stopsJSON = timeline;
        const previous = { stop_id: '508', data_url: '508-web-aut', arrival_time: '23:10' };
        state.mergedStops = [previous, { stop_id: '611' }];
        try {
            const pending = getPreviousStopsRealTime();
            state.mergedStops = [];
            finish({ json: async () => [candidate('23:13')] });
            await pending;
            expect(previous.arrival_time).toBe('23:10');
            expect(previous.hasRealTime).toBeUndefined();
        } finally {
            global.fetch = originalFetch;
        }
    });

    test('aggiorna la fermata coerente e non interroga data_url assenti o punti gia associati', async () => {
        const originalFetch = global.fetch;
        global.fetch = jest.fn(async () => ({ json: async () => [candidate('23:13')] }));
        state.currentStopId = '611';
        state.lineFull = '6_UM';
        state.stopsJSON = timeline;
        const previous = { stop_id: '508', stop_name: 'VENEZIA', data_url: '508-web-aut', arrival_time: '23:10', hasGTFS: true };
        state.mergedStops = [previous,
            { stop_id: '1392', data_url: '392-1392-web-aut', rtTimeline: true },
            { stop_id: '11380', data_url: null }, { stop_id: '611' }];
        document.body.innerHTML = '<div class="stop-item" data-stop-id="508" data-index="0"></div>';
        try {
            await getPreviousStopsRealTime();
            expect(global.fetch).toHaveBeenCalledTimes(1);
            expect(previous.arrival_time).toBe('23:13');
            expect(previous.hasRealTime).toBe(true);
            expect(document.querySelector('.stop-item').textContent).toContain('VENEZIA');
        } finally {
            global.fetch = originalFetch;
            document.body.innerHTML = '';
        }
    });
});

describe('formatMinutesRemaining', () => {
    test('ritorna la stringa originale se non contiene ":"', () => {
        expect(formatMinutesRemaining('info')).toBe('info');
    });

    test('ritorna la stringa originale per null', () => {
        expect(formatMinutesRemaining(null)).toBeNull();
    });

    test('ritorna la stringa originale per undefined', () => {
        expect(formatMinutesRemaining(undefined)).toBeUndefined();
    });

    test('ritorna "< 1 min" se l\'orario è adesso', () => {
        const now = new Date();
        const h = String(now.getHours()).padStart(2, '0');
        const m = String(now.getMinutes()).padStart(2, '0');
        expect(formatMinutesRemaining(`${h}:${m}`)).toBe('< 1 min');
    });

    test('ritorna "X min" per orario nel futuro prossimo', () => {
        const future = new Date();
        future.setMinutes(future.getMinutes() + 10);
        const h = String(future.getHours()).padStart(2, '0');
        const m = String(future.getMinutes()).padStart(2, '0');
        const result = formatMinutesRemaining(`${h}:${m}`);
        expect(result).toMatch(/\d+ min/);
    });

    test('ritorna "Passato" per orario nel passato', () => {
        const past = new Date();
        past.setMinutes(past.getMinutes() - 10);
        const h = String(past.getHours()).padStart(2, '0');
        const m = String(past.getMinutes()).padStart(2, '0');
        const result = formatMinutesRemaining(`${h}:${m}`);
        expect(result).toMatch(/Passato/);
    });

    test('gestisce formato con ore e minuti per tempi oltre 60 min', () => {
        const future = new Date();
        future.setMinutes(future.getMinutes() + 90);
        const h = String(future.getHours()).padStart(2, '0');
        const m = String(future.getMinutes()).padStart(2, '0');
        const result = formatMinutesRemaining(`${h}:${m}`);
        expect(result).toMatch(/h.*min/);
    });
});

describe('mergeStops', () => {
    beforeEach(() => {
        state.stopsGTFS = [];
        state.stopsJSON = [];
    });

    test('ritorna array vuoto senza dati', () => {
        const result = mergeStops();
        expect(result).toEqual([]);
    });

    test('usa solo dati GTFS quando non ci sono dati real-time', () => {
        state.stopsGTFS = [
            { stop_name: 'Fermata A', stop_id: '100', arrival_time: '10:00', departure_time: '10:00' },
            { stop_name: 'Fermata B', stop_id: '200', arrival_time: '10:05', departure_time: '10:05' }
        ];
        state.stopsJSON = [];
        const result = mergeStops();
        expect(result).toHaveLength(2);
        expect(result[0].hasRealTime).toBe(false);
        expect(result[0].hasGTFS).toBe(true);
    });

    test('fonde dati GTFS e real-time per la stessa fermata', () => {
        state.stopsGTFS = [
            { stop_name: 'Fermata A', stop_id: '100', arrival_time: '10:00', departure_time: '10:00' }
        ];
        state.stopsJSON = [
            { stop: 'Fermata A', time: '10:02' }
        ];
        const result = mergeStops();
        expect(result).toHaveLength(1);
        expect(result[0].hasRealTime).toBe(true);
        expect(result[0].arrival_time).toBe('10:02');
    });

    test('fonde nomi fermata con maiuscole, spazi e apostrofi diversi', () => {
        state.stopsGTFS = [
            { stop_name: "Ca' Marcello  Cappuccina", stop_id: '9', arrival_time: '10:00' }
        ];
        state.stopsJSON = [
            { stop: 'CA’ MARCELLO CAPPUCCINA', time: '10:02' }
        ];

        const result = mergeStops();

        expect(result).toHaveLength(1);
        expect(result[0].hasRealTime).toBe(true);
        expect(result[0].arrival_time).toBe('10:02');
    });

    test('mantiene le occorrenze duplicate nello stesso ordine', () => {
        state.stopsGTFS = [
            { stop_name: 'Fermata A', stop_id: '100', arrival_time: '10:00' },
            { stop_name: 'Fermata A', stop_id: '100', arrival_time: '10:10' }
        ];
        state.stopsJSON = [
            { stop: 'fermata a', time: '10:02' },
            { stop: 'FERMATA A', time: '10:12' }
        ];

        const result = mergeStops();

        expect(result).toHaveLength(2);
        expect(result.map(stop => stop.arrival_time)).toEqual(['10:02', '10:12']);
    });

    test('aggiunge fermate JSON non presenti in GTFS', () => {
        state.stopsGTFS = [
            { stop_name: 'Fermata A', stop_id: '100', arrival_time: '10:00', departure_time: '10:00' }
        ];
        state.stopsJSON = [
            { stop: 'Fermata A', time: '10:02' },
            { stop: 'Fermata Extra', time: '10:10' }
        ];
        const result = mergeStops();
        expect(result).toHaveLength(2);
        expect(result[1].hasGTFS).toBe(false);
        expect(result[1].hasRealTime).toBe(true);
    });

    test('31H: assegna il capolinea a 20 minuti all arrivo dopo Cadorna, non alla partenza', () => {
        state.stopsGTFS = [
            { stop_name: 'MESTRE CENTRO B7', arrival_time: '23:27' },
            { stop_name: 'San Marco Musatti', arrival_time: '23:29' },
            { stop_name: 'Bissuola Cadorna', arrival_time: '23:44' },
            { stop_name: 'Bissuola Colombo', arrival_time: '23:45' },
            { stop_name: 'MESTRE CENTRO B7', arrival_time: '23:46' }
        ];
        state.stopsJSON = [
            { stop: 'Bissuola Cadorna', time: '23:44' },
            { stop: 'Bissuola Colombo', time: '23:46' },
            { stop: 'MESTRE CENTRO B7', time: '23:48' }
        ];
        const result = mergeStops();
        expect(result[0].hasRealTime).toBe(false);
        expect(result[0].arrival_time).toBe('23:27');
        expect(result[4].hasRealTime).toBe(true);
        expect(result[4].arrival_time).toBe('23:48');
        expect(result.filter(stop => stop.rtTimeline).map(stop => stop.arrival_time))
            .toEqual(['23:44', '23:46', '23:48']);
    });

    test('usa gli altri punti per distinguere le occorrenze anche con forte ritardo', () => {
        state.stopsGTFS = [
            { stop_name: 'Capolinea', arrival_time: '10:00' },
            { stop_name: 'Intermedia', arrival_time: '10:05' },
            { stop_name: 'Capolinea', arrival_time: '10:10' }
        ];
        state.stopsJSON = [
            { stop: 'Capolinea', time: '10:12' },
            { stop: 'Intermedia', time: '10:17' }
        ];
        const result = mergeStops();
        expect(result.map(stop => stop.hasRealTime)).toEqual([true, true, false]);
    });

    test('un solo punto RT tra due occorrenze usa la distanza circolare oltre mezzanotte', () => {
        state.stopsGTFS = [
            { stop_name: 'Capolinea', arrival_time: '23:45' },
            { stop_name: 'Capolinea', arrival_time: '24:05' }
        ];
        state.stopsJSON = [{ stop: 'Capolinea', time: '00:06' }];
        const result = mergeStops();
        expect(result.map(stop => stop.hasRealTime)).toEqual([false, true]);
        expect(result[1].arrival_time).toBe('00:06');
    });

    test('un ID realtime esplicito prevale su nome e vicinanza oraria', () => {
        state.stopsGTFS = [
            { stop_id: '258', stop_name: 'Pertini Gavagnin', arrival_time: '23:31' },
            { stop_id: '3568', stop_name: 'Pertini Carrer', arrival_time: '23:32' },
            { stop_id: '1258', stop_name: 'Pertini Gavagnin', arrival_time: '23:38' }
        ];
        state.stopsJSON = [{ stop_id: '1258', stop: 'Pertini Gavagnin', time: '23:31' }];
        expect(mergeStops().map(stop => stop.hasRealTime)).toEqual([false, false, true]);
        state.stopsJSON = [{ stop_id: '9999', stop: 'Pertini Gavagnin', time: '23:31' }];
        expect(mergeStops().slice(0, 3).map(stop => stop.hasRealTime)).toEqual([false, false, false]);
    });
});

describe('normalizeStopName', () => {
    test('normalizza accenti e punteggiatura', () => {
        expect(normalizeStopName('  Università—Ca’ Foscari ')).toBe('universita ca foscari');
    });
});

describe('trip map route colors', () => {
    test('normalizza il colore route_color del database', () => {
        expect(normalizeMapColor('#abc')).toBe('#AABBCC');
        expect(normalizeMapColor('087f5b')).toBe('#087F5B');
        expect(normalizeMapColor('invalid')).toBeNull();
    });

    test('sceglie il testo con contrasto migliore', () => {
        expect(getContrastTextColor('#ffffff')).toBe('#111827');
        expect(getContrastTextColor('#087f5b')).toBe('#fff');
    });
});

describe('trip map stop arrows', () => {
    test('allinea il chevron con il bearing geografico', () => {
        expect(getMapStopArrowRotation(0)).toBe(-90);
        expect(getMapStopArrowRotation(90)).toBe(0);
        expect(getMapStopArrowRotation(180)).toBe(90);
    });

    test('identifica solo il primo e l ultimo capolinea', () => {
        const stops = [{ stop_id: 'a' }, { stop_id: 'b' }, { stop_id: 'c' }];
        expect(isMapTerminalStop(stops, 0)).toBe(true);
        expect(isMapTerminalStop(stops, 1)).toBe(false);
        expect(isMapTerminalStop(stops, 2)).toBe(true);
    });
});

describe('selectMatchingTripTimingPoints', () => {
    test('conserva stop 1258 sul primo punto RT e lo passa al resolver come ID specifico', async () => {
        const originalFetch = global.fetch;
        state.tripId = 'AUT_ACTV_794_26812';
        global.fetch = jest.fn()
            .mockResolvedValueOnce({ ok: true, json: async () => [{ line: '31H_UM', stop: '1258', lineId: 5274,
                timingPoints: [{ stop: 'Pertini Gavagnin', time: '23:37' }, { stop: 'Bissuola Cadorna', time: '23:45' }] }] })
            .mockResolvedValueOnce({ ok: true, text: async () => JSON.stringify({ trip_id: state.tripId }) });
        try {
            const result = await fetchRealTimeInfo('258-1258', '31H', 'thursday');
            expect(result[0].stop_id).toBe('1258');
            expect(result[1].stop_id).toBeUndefined();
            const params = new URL(global.fetch.mock.calls[1][0], 'https://actv-live.test').searchParams;
            expect(params.get('stopId')).toBe('1258');
        } finally { global.fetch = originalFetch; }
    });
    test('non attribuisce la timeline 26812 alla 26811 anche se il contesto coincide', () => {
        const warn = jest.spyOn(console, 'warn').mockImplementation(() => {});
        try {
            expect(selectMatchingTripTimingPoints([{
                calculatedTripId: 'AUT_ACTV_794_26812',
                timingPoints: [{ stop: 'MESTRE CENTRO B7', time: '23:47' }]
            }], 'AUT_ACTV_794_26811', { stop: 'MESTRE CENTRO B7', time: '23:47', realtime: true })).toEqual([]);
        } finally { warn.mockRestore(); }
    });

    test('risolve il passaggio alla fermata selezionata prima di usare il contesto', async () => {
        const originalFetch = global.fetch;
        const warn = jest.spyOn(console, 'warn').mockImplementation(() => {});
        state.tripId = 'AUT_ACTV_794_26811';
        state.stopsGTFS = [{ stop_name: 'Bissuola Cadorna' }, { stop_name: 'MESTRE CENTRO B7' }];
        global.fetch = jest.fn()
            .mockResolvedValueOnce({ ok: true, json: async () => [{ line: '31H_UM', destination: 'MESTRE CENTRO', lineId: 5274,
                timingPoints: [{ stop: 'Bissuola Cadorna', time: '23:44' }, { stop: 'MESTRE CENTRO B7', time: '23:47' }] }] })
            .mockResolvedValueOnce({ ok: true, text: async () => JSON.stringify({ trip_id: 'AUT_ACTV_794_26812' }) });
        try {
            expect(await fetchRealTimeInfo('270-1270', '31H', 'thursday', {
                stop: 'MESTRE CENTRO B7', time: '23:47', realtime: true
            })).toEqual([]);
            const params = new URL(global.fetch.mock.calls[1][0], 'https://actv-live.test').searchParams;
            expect(params.get('stop')).toBe('Bissuola Cadorna');
            expect(params.get('time')).toBe('23:44');
            expect(params.get('stopId')).toBe('270-1270');
        } finally { global.fetch = originalFetch; warn.mockRestore(); }
    });

    test('rimuove il ritardo quando non rimane una stima realtime associata', () => {
        document.body.innerHTML = '<div id="time-container"><span id="time-text"></span><span id="time-delay">+60 min</span></div>';
        state.arrivalTime = null;
        updateHeader();
        expect(document.getElementById('time-delay').style.display).toBe('none');
        expect(document.getElementById('time-container').style.display).toBe('none');
        document.body.innerHTML = '';
    });
    test('usa soltanto la corsa con trip ID esatto', () => {
        const wrongTrip = [{ stop: 'Fermata sbagliata', time: '21:15' }];
        const exactTrip = [{ stop: 'Fermata corretta', time: '21:16' }];

        expect(selectMatchingTripTimingPoints([
            { calculatedTripId: 'AUT_ACTV_99_11111', timingPoints: wrongTrip },
            { calculatedTripId: 'AUT_ACTV_99_57155', timingPoints: exactTrip }
        ], 'AUT_ACTV_99_57155')).toBe(exactTrip);
    });

    test('usa il contesto originale quando il resolver non identifica il candidato', () => {
        const contextTrip = [{ stop: 'IST.GRITTI', time: '21:29' }];
        const otherTrip = [{ stop: 'IST.GRITTI', time: '21:30' }];

        expect(selectMatchingTripTimingPoints([
            {
                calculatedTripId: null,
                destination: 'MESTRE CENTRO B3',
                timingPoints: contextTrip
            },
            {
                calculatedTripId: 'AUT_ACTV_99_11111',
                destination: 'MESTRE CENTRO B3',
                timingPoints: otherTrip
            }
        ], 'AUT_ACTV_99_57155', {
            stop: 'IST.GRITTI',
            time: '21:29',
            expectedDestination: 'MESTRE CENTRO B3'
        })).toBe(contextTrip);
    });

    test('non usa il primo candidato quando l ID non coincide', () => {
        const warn = jest.spyOn(console, 'warn').mockImplementation(() => {});
        const candidate = [{ stop: 'Fermata di un altra corsa', time: '21:15' }];

        expect(selectMatchingTripTimingPoints([
            { calculatedTripId: 'AUT_ACTV_99_11111', line: '5E_EN', timingPoints: candidate }
        ], 'AUT_ACTV_99_57155')).toEqual([]);
        expect(warn).toHaveBeenCalledWith('No matching trip found for tripId', expect.objectContaining({
            requestedTripId: 'AUT_ACTV_99_57155'
        }));

        warn.mockRestore();
    });
});
