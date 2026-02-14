/**
 * liveBusMap.js
 * Mappa in tempo reale dei bus ACTV in servizio.
 * Carica i bus in modo asincrono — ogni marker appare sulla mappa appena possibile.
 * Supporta filtri client-side per route_short_name, tripId, routeId.
 */

document.addEventListener('DOMContentLoaded', () => {

    // ── Costanti ──────────────────────────────────────────────
    const MAX_CONCURRENT = 6;        // Fetch paralleli massimi
    const REFRESH_INTERVAL = 60_000; // Auto-refresh ogni 60s
    const BUS_COLORS = [
        '#009E61', '#0152BB', '#E30613', '#FF9800',
        '#800080', '#00838F', '#C62828', '#1565C0',
        '#2E7D32', '#EF6C00', '#6A1B9A', '#00695C',
        '#AD1457', '#283593'
    ];

    // ── DOM refs ──────────────────────────────────────────────
    const filterInput  = document.getElementById('filter-input');
    const filterClear  = document.getElementById('filter-clear');
    const btnRefresh   = document.getElementById('btn-refresh');
    const counterText  = document.getElementById('counter-text');
    const lastUpdateEl = document.getElementById('last-update');
    const spinnerEl    = document.querySelector('#bus-counter .spinner-small');

    // ── Mappa Leaflet ─────────────────────────────────────────
    const map = L.map('map', {
        attributionControl: false,
        zoomControl: true
    }).setView([45.4384, 12.3359], 12);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap'
    }).addTo(map);

    // ── Stato ─────────────────────────────────────────────────
    let busMarkers = [];   // Array di L.marker
    let abortCtrl = null;  // Per annullare fetch in corso
    let refreshTimer = null;
    let stopCache = new Map(); // Cache per i passaggi alle fermate

    // ── Helpers ───────────────────────────────────────────────

    /**
     * Colore deterministico basato sul nome della linea
     */
    function getColor(name) {
        let h = 0;
        for (let i = 0; i < name.length; i++) {
            h = name.charCodeAt(i) + ((h << 5) - h);
        }
        return BUS_COLORS[Math.abs(h) % BUS_COLORS.length];
    }

    /**
     * Converte "HH:MM:SS" o "HH:MM" in secondi dal mezzanotte
     */
    function timeToSec(t) {
        if (!t) return 0;
        const p = t.split(':');
        return (+p[0]) * 3600 + (+p[1]) * 60 + (p[2] ? +p[2] : 0);
    }

    /**
     * Fetch dei dati real-time per una fermata (con caching)
     */
    async function getRealTimeDelay(stopId, lineName, tripHeadsign, signal) {
        // Se la fermata è già in cache da meno di 30s, usa quella
        const now = Date.now();
        if (stopCache.has(stopId)) {
            const entry = stopCache.get(stopId);
            if (now - entry.ts < 30000) {
                return findMatchingTrip(entry.data, lineName, tripHeadsign);
            }
        }

        try {
            const res = await fetch(`https://oraritemporeale.actv.it/aut/backend/passages/${stopId}-web-aut`, { signal });
            if (!res.ok) return null;
            const data = await res.json();
            stopCache.set(stopId, { ts: now, data });
            return findMatchingTrip(data, lineName, tripHeadsign);
        } catch (e) {
            return null;
        }
    }

    function findMatchingTrip(passages, lineName, tripHeadsign) {
        if (!Array.isArray(passages)) return null;
        
        // Cerca il trip che corrisponde a linea e destinazione
        const match = passages.find(p => {
            const pLine = p.line?.split('_')[0];
            return pLine === lineName && tripHeadsign.toLowerCase().includes(p.destination.toLowerCase());
        });

        if (!match) return null;

        // Il delay è la differenza tra l'orario real-time (p.time) e quello programmato 
        // per l'ultima fermata passata (se presente) o quella corrente.
        // ACTV usa p.time come orario previsto (real-time).
        // timingPoints[last] ha l'orario teorico per questa fermata? No, timingPoints è la lista fermate passate.
        // In realtà non abbiamo l'orario teorico del trip ACTV nel JSON passages facilmente comparabile al GTFS
        // MA possiamo calcolare il delay come: (Minuti Real-time) - (Minuti GTFS alla prossima fermata).
        // In realtà passiamo delaySec a interpolatePosition che lo usa come offset.
        
        return {
            isReal: match.real,
            rtTime: match.time,
            // Se real=true, 'match.time' è il tempo reale. 
            // Se real=false, 'match.time' è quello programmato.
            // Per semplicità, consideriamo il delay nullo se non abbiamo un modo certo di calcolarlo qui.
            // NOTA: interpolatePosition riceve delaySec.
        };
    }

    /**
     * Interpolazione lineare tra due fermate con correzione delay
     * @returns {{ lat, lng, nextStop, prevStop, progress, nextStopId }}
     */
    function interpolatePosition(stops, nowSec, delaySec = 0) {
        if (!stops || stops.length === 0) return null;

        const virtualNowSec = nowSec - delaySec;

        // Se il bus non è ancora partito → prima fermata
        const firstSec = timeToSec(stops[0].arrival_time);
        if (virtualNowSec <= firstSec) {
            return {
                lat: parseFloat(stops[0].stop_lat),
                lng: parseFloat(stops[0].stop_lon),
                nextStop: stops[0].stop_name,
                nextStopId: stops[0].stop_id,
                prevStop: null,
                progress: 0,
                nextTime: stops[0].arrival_time
            };
        }

        // Se il bus ha già finito → ultima fermata
        const lastSec = timeToSec(stops[stops.length - 1].arrival_time);
        if (virtualNowSec >= lastSec) {
            const last = stops[stops.length - 1];
            return {
                lat: parseFloat(last.stop_lat),
                lng: parseFloat(last.stop_lon),
                nextStop: last.stop_name + ' (capolinea)',
                nextStopId: last.stop_id,
                prevStop: stops.length > 1 ? stops[stops.length - 2].stop_name : null,
                progress: 1,
                nextTime: last.arrival_time
            };
        }

        // Trova il segmento corrente
        for (let i = 0; i < stops.length - 1; i++) {
            const aSec = timeToSec(stops[i].arrival_time);
            const bSec = timeToSec(stops[i + 1].arrival_time);

            if (virtualNowSec >= aSec && virtualNowSec < bSec) {
                const progress = (bSec - aSec) > 0
                    ? (virtualNowSec - aSec) / (bSec - aSec)
                    : 0;

                const latA = parseFloat(stops[i].stop_lat);
                const lngA = parseFloat(stops[i].stop_lon);
                const latB = parseFloat(stops[i + 1].stop_lat);
                const lngB = parseFloat(stops[i + 1].stop_lon);

                return {
                    lat: latA + (latB - latA) * progress,
                    lng: lngA + (lngB - lngA) * progress,
                    nextStop: stops[i + 1].stop_name,
                    nextStopId: stops[i + 1].stop_id,
                    prevStop: stops[i].stop_name,
                    progress,
                    nextTime: stops[i + 1].arrival_time
                };
            }
        }

        return null;
    }

    /**
     * Crea un'icona Leaflet per il bus (div colorato con nome linea)
     */
    function makeBusIcon(lineName, color, isReal = true) {
        const grayClass = isReal ? '' : ' gray';
        return L.divIcon({
            className: '',
            html: `<div class="bus-icon${grayClass}" style="background:${color}">${lineName}</div>`,
            iconSize: [32, 32],
            iconAnchor: [16, 16],
            popupAnchor: [0, -18]
        });
    }

    /**
     * Crea il contenuto popup per un bus
     */
    function makePopup(bus, pos, delayInfo) {
        const nextTime = pos.nextTime ? pos.nextTime.substring(0, 5) : '';
        const rtStatus = delayInfo?.isReal 
            ? '<span style="color:var(--color-primary-green)">● Real-time</span>' 
            : '<span style="color:#999">○ Programmato</span>';
        
        let delayHtml = '';
        if (delayInfo?.delaySec) {
            const mins = Math.round(delayInfo.delaySec / 60);
            delayHtml = `<div class="bus-popup-time">${mins > 0 ? '+' : ''}${mins} min di ritardo</div>`;
        }

        let nextStop_noCapolinea = pos.nextStop.replace(' (capolinea)', '');
        
        let params = new URLSearchParams(
            {
                id: pos.nextStopId,
                name: nextStop_noCapolinea
            }
        );
        return `
            <div class="bus-popup">
                <div class="bus-popup-line">Linea ${bus.route_short_name}</div>
                <div class="bus-popup-direction">→ ${bus.trip_headsign}</div>
                <div style="font-size:10px;margin-bottom:8px">${rtStatus}</div>
                ${pos.nextStop
                    ? `<a href="https://actv-live.test/aut/stops/stop?${params}" class="bus-popup-next">Prossima: ${pos.nextStop}</a>`
                    : ''
                }
                ${nextTime
                    ? `<div class="bus-popup-time">Orario previsto: ${nextTime}</div>`
                    : ''
                }
                ${delayHtml}
                <div class="bus-popup-time" style="margin-top:4px;opacity:0.6">Trip: ${bus.trip_id}</div>
            </div>
        `;
    }

    // ── Filtro ─────────────────────────────────────────────────

    /**
     * Applica il filtro testuale ai bus prima di fetchare le posizioni.
     * Cerca in: route_short_name, trip_id, route_id, trip_headsign.
     */
    function filterBuses(buses, query) {
        if (!query) return buses;
        const q = query.toLowerCase().trim();
        return buses.filter(b =>
            b.route_short_name.toLowerCase().includes(q) ||
            b.trip_id.toLowerCase().includes(q) ||
            (b.route_id + '').toLowerCase().includes(q) ||
            b.trip_headsign.toLowerCase().includes(q)
        );
    }

    // ── Pool di fetch concorrenti ─────────────────────────────

    /**
     * Esegue un array di task async con concorrenza limitata.
     */
    async function parallelPool(tasks, concurrency, signal) {
        let idx = 0;

        async function worker() {
            while (idx < tasks.length) {
                if (signal && signal.aborted) return;
                const i = idx++;
                try {
                    await tasks[i]();
                } catch (e) {
                    if (e.name !== 'AbortError') {
                        console.warn(`Task ${i} failed:`, e.message);
                    }
                }
            }
        }

        const workers = [];
        for (let w = 0; w < Math.min(concurrency, tasks.length); w++) {
            workers.push(worker());
        }
        await Promise.all(workers);
    }

    // ── Caricamento principale ─────────────────────────────────

    async function loadBuses() {
        if (abortCtrl) abortCtrl.abort();
        abortCtrl = new AbortController();
        const signal = abortCtrl.signal;

        busMarkers.forEach(m => map.removeLayer(m));
        busMarkers = [];

        spinnerEl.classList.remove('hidden');
        counterText.textContent = 'Caricamento...';

        try {
            const res = await fetch('/api/gtfs-bnr', { signal });
            if (!res.ok) throw new Error(`BNR status ${res.status}`);
            const data = await res.json();

            if (data.error) {
                counterText.textContent = `Errore: ${data.error}`;
                spinnerEl.classList.add('hidden');
                return;
            }

            const allBuses = data.buses || [];
            const query = filterInput.value;
            const buses = filterBuses(allBuses, query);

            if (buses.length === 0) {
                counterText.textContent = query
                    ? `Nessun bus per "${query}" (${allBuses.length} totali)`
                    : 'Nessun bus in servizio';
                spinnerEl.classList.add('hidden');
                return;
            }

            const nowSec = timeToSec(data.time);
            let loaded = 0;
            const total = buses.length;
            counterText.textContent = `0 / ${total}`;

            const tasks = buses.map(bus => async () => {
                if (signal.aborted) return;

                try {
                    // 1. Fetch GTFS stops
                    const posRes = await fetch(
                        `/api/bus-position?tripId=${encodeURIComponent(bus.trip_id)}`,
                        { signal }
                    );
                    if (!posRes.ok) return;
                    const stops = await posRes.json();
                    if (signal.aborted) return;

                    // 2. Initial estimation to find next stop
                    let pos = interpolatePosition(stops, nowSec);
                    if (!pos) return;

                    // 3. Real-time check
                    let delayInfo = null;
                    if (pos.nextStopId) {
                        delayInfo = await getRealTimeDelay(pos.nextStopId, bus.route_short_name, bus.trip_headsign, signal);
                    }

                    // 4. Adjust position if real-time delay found
                    if (delayInfo && delayInfo.rtTime) {
                        // Calculate delay: RT Time - GTFS Time at next stop
                        const gtfsTime = timeToSec(pos.nextTime);
                        const rtTime = timeToSec(delayInfo.rtTime);
                        delayInfo.delaySec = rtTime - gtfsTime;
                        
                        // Re-interpolate with delay
                        pos = interpolatePosition(stops, nowSec, delayInfo.delaySec);
                    }

                    const isReal = delayInfo ? delayInfo.isReal : true;
                    const color = getColor(bus.route_short_name);
                    const icon = makeBusIcon(bus.route_short_name, color, isReal);

                    const marker = L.marker([pos.lat, pos.lng], { icon })
                        .bindPopup(makePopup(bus, pos, delayInfo))
                        .addTo(map);

                    busMarkers.push(marker);
                } catch (e) {
                    if (e.name !== 'AbortError') {
                        console.warn(`Fetch fallito per trip ${bus.trip_id}:`, e.message);
                    }
                }

                loaded++;
                counterText.textContent = `${loaded} / ${total}`;
            });

            await parallelPool(tasks, MAX_CONCURRENT, signal);

            spinnerEl.classList.add('hidden');
            counterText.textContent = `${busMarkers.length} bus sulla mappa`;
            lastUpdateEl.textContent = `Agg. ${new Date().toLocaleTimeString('it-IT', { hour: '2-digit', minute: '2-digit' })}`;

        } catch (e) {
            if (e.name !== 'AbortError') {
                console.error('Errore caricamento bus:', e);
                counterText.textContent = 'Errore di caricamento';
                spinnerEl.classList.add('hidden');
            }
        }
    }

    // ── Event listeners ───────────────────────────────────────
    let filterTimeout = null;
    filterInput.addEventListener('input', () => {
        clearTimeout(filterTimeout);
        filterTimeout = setTimeout(() => loadBuses(), 400);
    });

    filterClear.addEventListener('click', () => {
        filterInput.value = '';
        loadBuses();
    });

    btnRefresh.addEventListener('click', () => {
        stopCache.clear();
        loadBuses();
    });

    function startAutoRefresh() {
        if (refreshTimer) clearInterval(refreshTimer);
        refreshTimer = setInterval(() => loadBuses(), REFRESH_INTERVAL);
    }

    loadBuses();
    startAutoRefresh();
});
