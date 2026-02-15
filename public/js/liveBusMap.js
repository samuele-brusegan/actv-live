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
    let busMarkers = new Map(); // tripId -> { marker, polyline }
    let abortCtrl = null;       // Per annullare fetch in corso
    let refreshTimer = null;
    let stopCache = new Map();  // Cache per i passaggi alle fermate

    // ── Helpers ───────────────────────────────────────────────

    /**
     * Colore deterministico basato sul nome della linea
     */
    function getColor(name) {
        let h = 0;
        for (let i = 0; i < name.length; i++) {
            h = name.charCodeAt(i) + ((h << 5) - h);
        }
        const color = BUS_COLORS[Math.abs(h) % BUS_COLORS.length];
        return color;
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
     * Calcola la distanza tra due coordinate (m)
     */
    function getDist(lat1, ln1, lat2, ln2) {
        const R = 6371e3;
        const φ1 = lat1 * Math.PI / 180;
        const φ2 = lat2 * Math.PI / 180;
        const Δφ = (lat2 - lat1) * Math.PI / 180;
        const Δλ = (ln2 - ln1) * Math.PI / 180;
        const a = Math.sin(Δφ / 2) * Math.sin(Δφ / 2) +
                  Math.cos(φ1) * Math.cos(φ2) *
                  Math.sin(Δλ / 2) * Math.sin(Δλ / 2);
        return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    }

    /**
     * Interpolazione precisa lungo la shape
     */
    function interpolateWithShape(stops, shape, nowSec, delaySec = 0) {
        if (!stops || stops.length === 0) return null;

        const virtualNowSec = nowSec - delaySec;
        const firstSec = timeToSec(stops[0].arrival_time);
        const lastSec  = timeToSec(stops[stops.length - 1].arrival_time);

        // Capolinea iniziale
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

        // Capolinea finale
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

        // Trova il segmento GTFS corrente
        let segment = null;
        for (let i = 0; i < stops.length - 1; i++) {
            const aSec = timeToSec(stops[i].arrival_time);
            const bSec = timeToSec(stops[i + 1].arrival_time);
            if (virtualNowSec >= aSec && virtualNowSec <= bSec) {
                segment = {
                    idx: i,
                    progress: (bSec - aSec) > 0 ? (virtualNowSec - aSec) / (bSec - aSec) : 0,
                    stopA: stops[i],
                    stopB: stops[i + 1]
                };
                break;
            }
        }

        if (!segment) return null;

        // Se non abbiamo una shape, fallback a lineare tra le fermate
        if (!shape || shape.length === 0) {
            const latA = parseFloat(segment.stopA.stop_lat);
            const lngA = parseFloat(segment.stopA.stop_lon);
            const latB = parseFloat(segment.stopB.stop_lat);
            const lngB = parseFloat(segment.stopB.stop_lon);
            return {
                lat: latA + (latB - latA) * segment.progress,
                lng: lngA + (lngB - lngA) * segment.progress,
                nextStop: segment.stopB.stop_name,
                nextStopId: segment.stopB.stop_id,
                prevStop: segment.stopA.stop_name
            };
        }

        // Identifica gli indici della shape corrispondenti alle fermate A e B
        // (Cerchiamo il punto della shape più vicino a ciascuna fermata)
        function findNearest(lat, lon) {
            let minD = Infinity, bestIdx = 0;
            for (let i = 0; i < shape.length; i++) {
                const d = getDist(lat, lon, shape[i].lat, shape[i].lng);
                if (d < minD) { minD = d; bestIdx = i; }
            }
            return bestIdx;
        }

        const idxA = findNearest(segment.stopA.stop_lat, segment.stopA.stop_lon);
        const idxB = findNearest(segment.stopB.stop_lat, segment.stopB.stop_lon);
        
        // Se la shape è al contrario o incoerente, fallback lineare
        if (idxA >= idxB) {
            const latA = parseFloat(segment.stopA.stop_lat);
            const lngA = parseFloat(segment.stopA.stop_lon);
            const latB = parseFloat(segment.stopB.stop_lat);
            const lngB = parseFloat(segment.stopB.stop_lon);
            return {
                lat: latA + (latB - latA) * segment.progress,
                lng: lngA + (lngB - lngA) * segment.progress,
                nextStop: segment.stopB.stop_name,
                nextStopId: segment.stopB.stop_id,
                prevStop: segment.stopA.stop_name
            };
        }

        // Calcola distanze cumulative tra idxA e idxB
        const subShape = shape.slice(idxA, idxB + 1);
        let dists = [0];
        let totalD = 0;
        for (let i = 1; i < subShape.length; i++) {
            totalD += getDist(subShape[i-1].lat, subShape[i-1].lng, subShape[i].lat, subShape[i].lng);
            dists.push(totalD);
        }

        const targetD = totalD * segment.progress;

        // Trova il punto esatto sulla shape
        for (let i = 0; i < dists.length - 1; i++) {
            if (targetD >= dists[i] && targetD <= dists[i+1]) {
                const segProg = (dists[i+1] - dists[i]) > 0 ? (targetD - dists[i]) / (dists[i+1] - dists[i]) : 0;
                const p1 = subShape[i];
                const p2 = subShape[i+1];
                return {
                    lat: parseFloat(p1.lat) + (parseFloat(p2.lat) - parseFloat(p1.lat)) * segProg,
                    lng: parseFloat(p1.lng) + (parseFloat(p2.lng) - parseFloat(p1.lng)) * segProg,
                    nextStop: segment.stopB.stop_name,
                    nextStopId: segment.stopB.stop_id,
                    prevStop: segment.stopA.stop_name,
                    nextTime: segment.stopB.arrival_time
                };
            }
        }

        // Ultimo punto della sub-shape come fallback
        return {
            lat: subShape[subShape.length-1].lat,
            lng: subShape[subShape.length-1].lng,
            nextStop: segment.stopB.stop_name,
            nextStopId: segment.stopB.stop_id,
            prevStop: segment.stopA.stop_name,
            nextTime: segment.stopB.arrival_time
        };
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
        
        return {
            isReal: match.real,
            rtTime: match.time,
            // Info extra
            vehicle: match.vehicle,
            operator: match.operator
        };
    }

    /**
     * Crea un'icona Leaflet per il bus (Badge circolare)
     */
    function makeBusIcon(lineName, color, isReal = true) {
        const grayClass = isReal ? '' : ' gray';
        return L.divIcon({
            className: '',
            html: `<div class="bus-icon${grayClass}" style="background-color:${color}">${lineName}</div>`,
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
            ? '<span style="color:#009E61">● Real-time</span>' 
            : '<span style="color:#999">○ Programmato</span>';
        
        let delayHtml = '';
        if (delayInfo?.delaySec) {
            const mins = Math.round(delayInfo.delaySec / 60);
            const color = mins > 0 ? '#E30613' : '#009E61';
            delayHtml = `<div class="bus-popup-time" style="color:${color};font-weight:700">${mins > 0 ? '+' : ''}${mins} min ritardo</div>`;
        }

        let nextStop_noCapolinea = pos.nextStop.replace(' (capolinea)', '');
        
        let params = new URLSearchParams({ id: pos.nextStopId, name: nextStop_noCapolinea });
        
        return `
            <div class="bus-popup">
                <div class="bus-popup-line">Linea ${bus.route_short_name}</div>
                <div class="bus-popup-direction">→ ${bus.trip_headsign}</div>
                <div style="font-size:10px;margin-bottom:8px">${rtStatus}</div>
                ${pos.nextStop
                    ? `<a href="https://actv-live.test/aut/stops/stop?${params}" class="bus-popup-next">Prossima: ${pos.nextStop}</a>`
                    : ''
                }
                ${nextTime ? `<div class="bus-popup-time">Orario previsto: ${nextTime}</div>` : ''}
                ${delayHtml}
                <div class="bus-popup-time" style="margin-top:8px;opacity:0.6;font-size:9px">
                    Trip: ${bus.trip_id}
                    ${delayInfo?.vehicle ? `<br>Veicolo: ${delayInfo.vehicle}` : ''}
                </div>
            </div>
        `;
    }

    // ── Filtro ─────────────────────────────────────────────────

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

    // ── Pool di fetch e Caricamento ───────────────────────────

    async function parallelPool(tasks, concurrency, signal) {
        let idx = 0;
        async function worker() {
            while (idx < tasks.length) {
                if (signal && signal.aborted) return;
                const i = idx++;
                try { await tasks[i](); } catch (e) { }
            }
        }
        const workers = [];
        for (let w = 0; w < Math.min(concurrency, tasks.length); w++) {
            workers.push(worker());
        }
        await Promise.all(workers);
    }

    async function loadBuses() {
        if (abortCtrl) abortCtrl.abort();
        abortCtrl = new AbortController();
        const signal = abortCtrl.signal;

        spinnerEl.classList.remove('hidden');
        counterText.textContent = 'Caricamento...';

        try {
            const res = await fetch('/api/gtfs-bnr', { signal });
            if (!res.ok) throw new Error(`BNR status ${res.status}`);
            const data = await res.json();

            const allBuses = data.buses || [];
            
            // Applica filtri da input e URL
            const urlParams = new URLSearchParams(window.location.search);
            const lineFilter = urlParams.get('line');
            const tripFilter = urlParams.get('tripId');
            const destFilter = urlParams.get('destination');

            let buses = filterBuses(allBuses, filterInput.value);

            if (lineFilter) buses = buses.filter(b => b.route_short_name === lineFilter);
            if (tripFilter) buses = buses.filter(b => b.trip_id === tripFilter);
            if (destFilter) buses = buses.filter(b => b.trip_headsign.toLowerCase().includes(destFilter.toLowerCase()));

            if (buses.length === 0) {
                // Pulisci tutto se nessun bus trovato
                busMarkers.forEach(b => {
                    map.removeLayer(b.marker);
                    if (b.polyline) map.removeLayer(b.polyline);
                });
                busMarkers.clear();
                counterText.textContent = 'Nessun bus trovato';
                spinnerEl.classList.add('hidden');
                return;
            }

            const nowSec = timeToSec(data.time);
            let loaded = 0;
            const total = buses.length;
            counterText.textContent = `0 / ${total}`;

            // Trip IDs che devono rimanere sulla mappa
            const validTripIds = new Set(buses.map(b => b.trip_id));

            const tasks = buses.map(bus => async () => {
                if (signal.aborted) return;

                try {
                    const posRes = await fetch(`/api/bus-position?tripId=${encodeURIComponent(bus.trip_id)}`, { signal });
                    if (!posRes.ok) return;
                    const posData = await posRes.json();
                    if (signal.aborted) return;

                    const stops = posData.stops;
                    const shape = posData.shape;

                    let pos = interpolateWithShape(stops, shape, nowSec);
                    if (!pos) return;

                    let delayInfo = null;
                    if (pos.nextStopId) {
                        delayInfo = await getRealTimeDelay(pos.nextStopId, bus.route_short_name, bus.trip_headsign, signal);
                    }

                    if (delayInfo && delayInfo.rtTime) {
                        const gtfsTime = timeToSec(pos.nextTime);
                        const rtTime = timeToSec(delayInfo.rtTime);
                        delayInfo.delaySec = rtTime - gtfsTime;
                        pos = interpolateWithShape(stops, shape, nowSec, delayInfo.delaySec);
                    }

                    const color = getColor(bus.route_short_name);
                    const isReal = delayInfo ? delayInfo.isReal : true;
                    const icon = makeBusIcon(bus.route_short_name, color, isReal);

                    // Rimuovi il vecchio marker per questo trip
                    if (busMarkers.has(bus.trip_id)) {
                        const old = busMarkers.get(bus.trip_id);
                        map.removeLayer(old.marker);
                        if (old.polyline) map.removeLayer(old.polyline);
                    }

                    // Aggiungi il nuovo
                    const marker = L.marker([pos.lat, pos.lng], { icon })
                        .bindPopup(makePopup(bus, pos, delayInfo))
                        .addTo(map);

                    let polyline = null;
                    if (shape && shape.length > 0) {
                        polyline = L.polyline(shape.map(p => [p.lat, p.lng]), {
                            color: color,
                            weight: 3,
                            opacity: 0.4,
                            dashArray: '5, 10'
                        }).addTo(map);
                    }

                    busMarkers.set(bus.trip_id, { marker, polyline });
                } catch (e) { }

                loaded++;
                counterText.textContent = `${loaded} / ${total}`;
            });

            await parallelPool(tasks, MAX_CONCURRENT, signal);

            // Rimuovi i bus non più in servizio
            for (let [tripId, b] of busMarkers.entries()) {
                if (!validTripIds.has(tripId)) {
                    map.removeLayer(b.marker);
                    if (b.polyline) map.removeLayer(b.polyline);
                    busMarkers.delete(tripId);
                }
            }

            spinnerEl.classList.add('hidden');
            counterText.textContent = `${busMarkers.size} bus sulla mappa`;
            lastUpdateEl.textContent = `Agg. ${new Date().toLocaleTimeString('it-IT', { hour: '2-digit', minute: '2-digit' })}`;

        } catch (e) {
            if (e.name !== 'AbortError') {
                spinnerEl.classList.add('hidden');
                counterText.textContent = 'Errore di caricamento';
            }
        }
    }

    // ── Event listeners e Inizializzazione ──────────────────

    // Inizializza filtro da URL se presente
    const initParams = new URLSearchParams(window.location.search);
    if (initParams.get('line')) filterInput.value = initParams.get('line');
    else if (initParams.get('tripId')) filterInput.value = initParams.get('tripId');

    let filterTimeout = null;
    filterInput.addEventListener('input', () => {
        clearTimeout(filterTimeout);
        filterTimeout = setTimeout(() => loadBuses(), 400);
    });

    filterClear.addEventListener('click', () => {
        filterInput.value = '';
        // Pulisci anche parametri URL
        window.history.replaceState({}, '', window.location.pathname);
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

