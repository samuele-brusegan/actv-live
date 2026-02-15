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
    
    // Side Panel Container (Dynamic creation if missing)
    let sidePanel = document.getElementById('non-rt-panel');
    if (!sidePanel) {
        sidePanel = document.createElement('div');
        sidePanel.id = 'non-rt-panel';
        sidePanel.classList.add('hidden');
        sidePanel.innerHTML = `
            <div class="nr-header" onclick="this.parentElement.classList.toggle('collapsed')">
                <span>Bus non monitorati</span>
                <img src="svg/expand_more.svg" alt="expand_more" style="width:16px">
            </div>
            <ul class="nr-list"></ul>
        `;
        document.body.appendChild(sidePanel);
    }
    const sidePanelList = sidePanel.querySelector('.nr-list');

    // ── Mappa Leaflet ─────────────────────────────────────────
    const map = L.map('map', {
        attributionControl: false,
        zoomControl: true,
        maxZoom: 19
    }).setView([45.4384, 12.3359], 12);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap'
    }).addTo(map);

    // ── Stato ─────────────────────────────────────────────────
    let busMarkers = new Map(); // tripId -> { marker, polyline }
    let abortCtrl = null;       // Per annullare fetch in corso
    let refreshTimer = null;
    let stopCache = new Map();  // Cache per i passaggi alle fermate

    map.on('zoomend moveend', updateMarkerSizes);
    // Also trigger after load
    const originalLoadBuses = loadBuses;
    loadBuses = async function() {
        await originalLoadBuses.apply(this, arguments);
        updateMarkerSizes();
    };

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



    // ── Funzioni ─────────────────────────────────────────────
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
     * Converte secondi dal mezzanotte in "HH:MM:SS"
     */
    function secToTime(t) {
        if (!t) return 0;
        const hours = Math.floor(t / 3600);
        const minutes = Math.floor((t % 3600) / 60);
        const seconds = t % 60;
        return `${hours}:${minutes}:${seconds}`;
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

        stops.forEach( (stop) => {
            let real_stop_id = stop.data_url.split("-").slice(0, -2).join("-");            
            stop.real_stop_id = real_stop_id;
        }); 
                

        // Capolinea iniziale
        if (virtualNowSec <= firstSec) {
            return {
                lat: parseFloat(stops[0].stop_lat),
                lng: parseFloat(stops[0].stop_lon),
                nextStop: stops[0].stop_name,
                nextStopId: stops[0].real_stop_id,
                prevStop: null,
                progress: 0,
                nextTime: stops[0].arrival_time
            };
        }

        // Capolinea finale
        if (virtualNowSec >= lastSec) {
            return null;
            const last = stops[stops.length - 1];
            return {
                lat: parseFloat(last.stop_lat),
                lng: parseFloat(last.stop_lon),
                nextStop: last.stop_name  + ' (capolinea)' ,
                nextStopId: last.real_stop_id,
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
        
        // console.log("Linea 198", segment);
        

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
                nextStopId: segment.stopB.real_stop_id,
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
                nextStopId: segment.stopB.real_stop_id,
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
                    nextStopId: segment.stopB.real_stop_id,
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
            nextStopId: segment.stopB.real_stop_id,
            prevStop: segment.stopA.stop_name,
            nextTime: segment.stopB.arrival_time
        };
    }

    /**
     * Fetch dei dati real-time per una fermata (con caching)
     */
    async function getRealTimeDelay(stopId, lineName, tripHeadsign, gtfsStopSec, signal) {
        // Se la fermata è già in cache da meno di 30s, usa quella
        const now = Date.now();
        if (stopCache.has(stopId)) {
            const entry = stopCache.get(stopId);
            if (now - entry.ts < 30000) {
                console.log("Using cached data for stop", stopId);
                return findBestMatch(entry.data, lineName, tripHeadsign, gtfsStopSec);
            }
        }

        try {
            let url = `https://oraritemporeale.actv.it/aut/backend/passages/${stopId}-web-aut`;
            console.log("getRealTimeDelay", url, `https://actv-live.test/aut/stops/stop?id=${stopId}`);
            
            const res = await fetch(url, { signal });
            if (!res.ok) return null;
            const data = await res.json();
            // console.log("fetched", url, "data", data);
            
            stopCache.set(stopId, { ts: now, data });
            return findBestMatch(data, lineName, tripHeadsign, gtfsStopSec);
        } catch (e) {
            return null;
        }
    }

    /**
     * Parsing avanzato dell'orario real-time
     * Supporta: "HH:MM", "MM min", "MM'"
     */
    function parseRealTime(rtStr, now) {
        if (!rtStr) return null;
        rtStr = rtStr.trim();
        
        // Formato relativo: "9'", "5 min"
        if (rtStr.includes("'") || rtStr.includes("min")) {
            const minutes = parseInt(rtStr.replace(/[^0-9]/g, ''), 10);
            if (isNaN(minutes)) return null;
            // Aggiungi minuti all'ora corrente
            const d = new Date(now);
            d.setMinutes(d.getMinutes() + minutes);
            return d.getHours() * 3600 + d.getMinutes() * 60 + d.getSeconds();
        }
        
        // Formato assoluto: "15:40"
        if (rtStr.includes(":")) {
            return timeToSec(rtStr + ":00");
        }
        
        return null;
    }

    /**
     * Trova il miglior match basato su prossimità temporale e similarità destinazione
     */
    function findBestMatch(passages, lineName, tripHeadsign, gtfsStopSec) {
        if (!Array.isArray(passages)) return null;

        passages.forEach(p => {
            p.path = null;
        });

        const candidates = passages.filter(p => {
             // Normalizza nome linea (es. "6L" vs "6L_UM")
             const pLine = p.line ? p.line.split('_')[0] : '';
             return pLine === lineName;
        });

        if (candidates.length === 0) return null;

        let bestMatch = null;
        let bestScore = -Infinity;
        const now = Date.now(); // Per parsing orari relativi

        // Pesi per lo scoring
        const MAX_TIME_DIFF = 3600; // 60 min max differenza
        const TIME_WEIGHT = 0.7;
        const DEST_WEIGHT = 0.3;

        console.log("pre_candidates", candidates, lineName);
        
        candidates.forEach(cand => {
            // 1. Punteggio Tempo
            let timeScore = 0;
            const candSec = parseRealTime(cand.time, now);
            
            if (candSec !== null) {
                // Gestione scavallamento mezzanotte (semplificata)
                let diff = Math.abs(candSec - gtfsStopSec);
                if (diff > 43200) diff = 86400 - diff; // Wrap-around 24h

                if (diff <= MAX_TIME_DIFF) {
                    // Score 1.0 se diff=0, scende linearmente
                    timeScore = 1 - (diff / MAX_TIME_DIFF);
                } else {
                    timeScore = -1; // Troppo distante
                }
            }

            // 2. Punteggio Destinazione (Jaccard/Overlap simplificato)
            let destScore = 0;
            const normCandDest = cand.destination ? cand.destination.toLowerCase() : '';
            const normGtfsDest = tripHeadsign.toLowerCase();
            
            // Check parole comuni
            const candWords = normCandDest.split(/\s+/).filter(w => w.length > 2);
            const gtfsWords = normGtfsDest.split(/\s+/).filter(w => w.length > 2);
            
            let matches = 0;
            candWords.forEach(w => {
                if (normGtfsDest.includes(w)) matches++;
            });
            
            if (candWords.length > 0) {
                destScore = matches / candWords.length;
            } else if (normCandDest === normGtfsDest) {
                destScore = 1;
            }

            // 3. Score Totale
            // Se il tempo è valido, combiniamo. Se no (es. timeScore < 0), penalizza forte.
            let totalScore = 0;
            if (timeScore >= 0) {
                totalScore = (timeScore * TIME_WEIGHT) + (destScore * DEST_WEIGHT);
            } else {
                totalScore = -1;
            }

            if (totalScore > bestScore) {
                bestScore = totalScore;
                bestMatch = { candidate: cand, score: totalScore, candSec, secToTime: secToTime(candSec) };
            }
        });

        console.log(
            {
                "lineName": lineName,
                "tripToMatch":{"lineName": lineName, "tripHeadsign": tripHeadsign, "gtfsStopSec": gtfsStopSec, "secToTime": secToTime(gtfsStopSec)},
                "bestMatch":bestMatch, 
                "candidates": candidates
            }
        );
        /* console.log('TripToMatch:', { tripHeadsign, lineName });
        console.log('Best match:', bestMatch);
        console.log('Candidates:', candidates);
        console.log('Passages:', passages);
        console.log(''); */

        // Soglia minima di accettabilità per il match
        if (bestMatch && bestMatch.score >= 0.3) {
            return {
                status: bestMatch.candidate.real ? 'REALTIME' : 'SCHEDULED_API_FLAG',
                isReal: bestMatch.candidate.real,
                rtTime: formatSecToTime(bestMatch.candSec), // Converti sec back to HH:MM:SS
                vehicle: bestMatch.candidate.vehicle,
                operator: bestMatch.candidate.operator,
                debugScore: bestMatch.score
            };
        }

        return { status: 'SCHEDULED_NO_DATA', isReal: false };
    }

    function formatSecToTime(sec) {
        const h = Math.floor(sec / 3600);
        const m = Math.floor((sec % 3600) / 60);
        const s = sec % 60;
        return `${String(h).padStart(2,'0')}:${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`;
    }

    /**
     * Crea un'icona Leaflet per il bus (Badge circolare)
     */
    function makeBusIcon(lineName, color, status) {
        let typeClass = '';
        if (status === 'SCHEDULED_NO_DATA') typeClass = ' scheduled';
        else if (status === 'SCHEDULED_API_FLAG') typeClass = ' scheduled-api';
        else typeClass = ' realtime';

        // Add specific class for easy selection
        const markerClass = `bus-marker-${lineName.replace(/\s+/g, '-')}`;
        
        // Data attribute for content in mini mode
        return L.divIcon({
            className: `bus-div-icon ${markerClass}`, 
            html: `<div class="bus-icon${typeClass}" style="background-color:${color}" data-line="${lineName}">${lineName}</div>`,
            iconSize: [30, 30],
            iconAnchor: [15, 15],
            popupAnchor: [0, -15]
        });
    }

    /**
     * Crea il contenuto popup per un bus
     */
    function makePopup(bus, pos, delayInfo) {
        const nextTime = pos.nextTime ? pos.nextTime.substring(0, 5) : '';
        
        let rtStatus = '<span style="color:#999">○ Programmato (Dati non disp.)</span>';
        if (delayInfo?.status === 'REALTIME') {
            rtStatus = '<span style="color:#009E61">● Real-time</span>';
        } else if (delayInfo?.status === 'SCHEDULED_API_FLAG') {
            // rtStatus = '<span style="color:#FF9800">⚠️ Programmato (No GPS)</span>';
             rtStatus = '<span style="color:#757575">○ Programmato (No GPS)</span>';
        }
        
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
                sidePanelList.innerHTML = '';
                sidePanel.classList.add('hidden');
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

            // Track non-RT trips to prune stale ones at the end
            const activeTripsInCycle = new Set();

            const tasks = buses.map(bus => async () => {
                if (signal.aborted) return;

                try {
                    const posRes = await fetch(`/api/bus-position?tripId=${encodeURIComponent(bus.trip_id)}`, { signal });
                    if (!posRes.ok) return;
                    const posData = await posRes.json();
                    if (signal.aborted) return;

                    const stops = posData.stops;
                    const shape = posData.shape;
                    
                    // pos = dove dovrebbe essere con 0 delay
                    let pos = interpolateWithShape(stops, shape, nowSec);
                    if (!pos) {
                        if (busMarkers.has(bus.trip_id)) {
                            const old = busMarkers.get(bus.trip_id);
                            map.removeLayer(old.marker);
                            if (old.polyline) map.removeLayer(old.polyline);
                            busMarkers.delete(bus.trip_id);
                        }
                        return;
                    }

                    let delayInfo = { status: 'SCHEDULED_NO_DATA', isReal: false };
                    
                    if (pos.nextStopId) {
                        const gtfsStopSec = timeToSec(pos.nextTime);
                        // restituisce il findBestMatch (che fallisce perché pos.nextStopId non è il paramento corretto???)
                        const fetched = await getRealTimeDelay(
                            pos.nextStopId,
                            bus.route_short_name,
                            bus.trip_headsign,
                            gtfsStopSec,
                            signal
                        );
                        if (fetched) delayInfo = fetched;
                    }

                    if (delayInfo && delayInfo.rtTime) {
                        const gtfsTime = timeToSec(pos.nextTime);
                        const rtTime = timeToSec(delayInfo.rtTime);
                        delayInfo.delaySec = rtTime - gtfsTime;
                        pos = interpolateWithShape(stops, shape, nowSec, delayInfo.delaySec);
                        if (!pos) {
                            if (busMarkers.has(bus.trip_id)) {
                                const old = busMarkers.get(bus.trip_id);
                                map.removeLayer(old.marker);
                                if (old.polyline) map.removeLayer(old.polyline);
                                busMarkers.delete(bus.trip_id);
                            }
                            return;
                        }
                    }
                    
                    const isNonRt = (delayInfo.status === 'SCHEDULED_API_FLAG' || delayInfo.status === 'SCHEDULED_NO_DATA');
                    if (isNonRt) {
                        activeTripsInCycle.add(bus.trip_id);
                        let li = sidePanelList.querySelector(`[data-trip-id="${bus.trip_id}"]`);
                        if (!li) {
                            li = document.createElement('li');
                            li.className = 'nr-item';
                            li.dataset.tripId = bus.trip_id;
                            sidePanelList.appendChild(li);
                        }
                        const label = (delayInfo.status === 'SCHEDULED_API_FLAG') ? 'No GPS' : 'No dati';
                        li.innerHTML = `
                            <span class="nr-badge">${bus.route_short_name}</span>
                            <div class="nr-content">
                                <span>${bus.trip_headsign}</span>
                                <span style="font-size:9px;color:#999">${label}</span>
                            </div>
                        `;
                        li.onclick = () => {
                            if (busMarkers.has(bus.trip_id)) {
                                const m = busMarkers.get(bus.trip_id).marker;
                                map.setView(m.getLatLng(), 16);
                                m.openPopup();
                            }
                        };
                    } else {
                        // Remove if it was non-RT but now is RT
                        const li = sidePanelList.querySelector(`[data-trip-id="${bus.trip_id}"]`);
                        if (li) li.remove();
                    }

                    const color = getColor(bus.route_short_name);
                    const status = delayInfo ? delayInfo.status : 'SCHEDULED_NO_DATA';
                    const icon = makeBusIcon(bus.route_short_name, color, status);

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

                    busMarkers.set(bus.trip_id, { marker, polyline, shape, currentPos: pos, busData: bus, status });
                } catch (e) { }

                loaded++;
                counterText.textContent = `${loaded} / ${total}`;
            });

            await parallelPool(tasks, MAX_CONCURRENT, signal);

            // Prune stale side panel items (buses no longer in service)
            Array.from(sidePanelList.children).forEach(li => {
                if (!activeTripsInCycle.has(li.dataset.tripId)) {
                    li.remove();
                }
            });

            const finalNonRtCount = sidePanelList.children.length;
            if (finalNonRtCount > 0) {
                sidePanel.classList.remove('hidden');
                sidePanel.querySelector('.nr-header span:first-child').textContent = `${finalNonRtCount} bus non monitorati`;
            } else {
                sidePanel.classList.add('hidden');
            }

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

    // ── Gestione Sovrapposizioni (Collision Detection) ────────
    function updateMarkerSizes() {
        const visibleMarkers = [];
        
        // 1. Reset all markers FIRST
        busMarkers.forEach(b => {
            const el = b.marker.getElement();
            if (el) {
                const icon = el.querySelector('.bus-icon');
                if (icon) icon.classList.remove('mini');
                b.marker.setZIndexOffset(0);
                // Reset position to original calculation (stored in currentPos)
                // We need to store original latlng to reset if displaced
                if (b.originalLatLng) {
                    b.marker.setLatLng(b.originalLatLng);
                } else {
                    b.originalLatLng = b.marker.getLatLng();
                }
            }
        });

        // 2. Filter visible markers
        busMarkers.forEach((val, key) => {
            if (map.getBounds().contains(val.marker.getLatLng())) {
                visibleMarkers.push({
                    key: key,
                    marker: val.marker,
                    shape: val.shape,
                    originalLatLng: val.originalLatLng || val.marker.getLatLng(),
                    screenPos: map.latLngToLayerPoint(val.marker.getLatLng()),
                    status: val.status
                });
            }
        });

        const COLLISION_DIST = 26; // pixels
        const COLLISION_DIST_SQ = COLLISION_DIST * COLLISION_DIST;

        // Group into clusters
        // Simple disjoint-set or just iterative clustering
        // For simplicity: iterate and check collisions. 
        // If collision: try displacement. If fail: miniaturize.
        
        // Multi-pass approach:
        
        // Pass 1: Detect heavy collisions
        for (let i = 0; i < visibleMarkers.length; i++) {
            const m1 = visibleMarkers[i];
            let cluster = [m1];
            
            for (let j = i + 1; j < visibleMarkers.length; j++) {
                const m2 = visibleMarkers[j];
                const dx = m1.screenPos.x - m2.screenPos.x;
                const dy = m1.screenPos.y - m2.screenPos.y;
                if ((dx*dx + dy*dy) < COLLISION_DIST_SQ) {
                    cluster.push(m2);
                }
            }

            if (cluster.length > 1) {
                resolveCluster(cluster);
            }
        }
    }

    function resolveCluster(cluster) {
        // Sort by priority (Realtime > Scheduled)
        // cluster.sort((a,b) => (a.status === 'REALTIME' ? -1 : 1));

        // Try displacement along shape
        let displacementPossible = true;
        const DISPLACEMENT_STEP = 0.00015; // ~15-20 meters lat/lon degrees approximation

        cluster.forEach((item, idx) => {
            if (idx === 0) return; // Keep first one anchored (usually the most accurate or just pivot)
            
            // If item has shape, try to move it back/forth
            if (item.shape && item.shape.length > 5) {
                // Find nearest point index on shape from original pos
                // Then move +/- N points
                // Simplified: Just shift LatLng slightly along the bearing of the shape? 
                // Or just naive lat/lon shift if we want to be fast.
                // Better: move along the polyline.
                
                // Let's implement a simple shift: 
                // We shift alternate items forward and backward
                const direction = (idx % 2 === 0) ? 1 : -1;
                const magnitude = Math.ceil(idx / 2); 
                
                // Find index on shape
                // We stored 'currentPos' which had 'lat'/'lng'. 
                // But finding exact index on shape is expensive every frame.
                // Let's just use a naive offset if strictly visual.
                // BUT user said "spostali un po' prima o un po' dopo, sempre sulla loro shape".
                // So we MUST stick to shape.
                
                // We need to re-find position on shape. 
                // Fortunately, we have the full shape array.
                
                // Optimized find:
                let bestIdx = -1;
                let minD = Infinity;
                const searchRadius = 0.005; // optimization
                for (let k=0; k<item.shape.length; k++) {
                    const latDiff = item.shape[k].lat - item.originalLatLng.lat;
                    const lonDiff = item.shape[k].lng - item.originalLatLng.lng;
                    if (Math.abs(latDiff) > searchRadius || Math.abs(lonDiff) > searchRadius) continue;
                    
                    const d = latDiff*latDiff + lonDiff*lonDiff;
                    if(d < minD) { minD = d; bestIdx = k; }
                }

                if (bestIdx !== -1) {
                    // Shift index
                    let newIdx = bestIdx + (direction * magnitude * 2); // Jump 2 points per step to ensure visibility
                    // Clamp
                    newIdx = Math.max(0, Math.min(newIdx, item.shape.length - 1));
                    
                    const newPt = item.shape[newIdx];
                    item.marker.setLatLng([newPt.lat, newPt.lng]);
                    
                    // Update screen pos for next checks? No, local resolution.
                } else {
                    displacementPossible = false;
                }
            } else {
                displacementPossible = false; // No shape, can't displace on shape
            }
        });

        // If displacement failed or improved visibility but still tight? 
        // User said: "se è impossibile usa il bollino piccolo"
        // Let's re-check collisions after displacement?
        // Computing screen positions again is expensive.
        // Let's check distance *after* displacement.
        
        // Re-check first vs others (simplified)
        const p0 = map.latLngToLayerPoint(cluster[0].marker.getLatLng());
        let stillColliding = false;
        
        for (let i=1; i<cluster.length; i++) {
            const pi = map.latLngToLayerPoint(cluster[i].marker.getLatLng());
            const dx = p0.x - pi.x;
            const dy = p0.y - pi.y;
            if ((dx*dx + dy*dy) < (20*20)) { // 20px threshold for mini-dots
                stillColliding = true; 
                break;
            }
        }

        if (!displacementPossible || stillColliding) {
            // Miniaturize ALL in cluster
            cluster.forEach(item => {
                const el = item.marker.getElement()?.querySelector('.bus-icon');
                //if (el) el.classList.add('mini');
                // Even mini dots must not overlap!
                // Apply Mini-Displacement: Jitter or Grid
                
                // Since we might have failed shape displacement or don't have shape,
                // we fallback to slight screen-space offset for mini-dots.
                // "anche i bollini biccoli non si devono sovrappore"
                
                // Circle layout around the center?
                
            });
            
            // Apply circle layout for minis
             const center = cluster[0].originalLatLng;
             const angleStep = (2 * Math.PI) / cluster.length;
             const radius = 0.00015; // Small geo radius
             
             cluster.forEach((item, idx) => {
                 // Reposition around center
                 if (idx === 0) return; // Keep one center
                 const angle = idx * angleStep;
                 const latOffset = radius * Math.cos(angle);
                 const lngOffset = radius * Math.sin(angle) * 1.5; // Aspect ratio correction
                 
                 item.marker.setLatLng([
                     center.lat + latOffset,
                     center.lng + lngOffset
                 ]);
             });
        }
    }
});

