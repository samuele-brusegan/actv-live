/**
 * adminDashboard.js
 * Logic for the Admin Dashboard.
 * Reuses logic from liveBusMap.js to calculate delays and positions.
 */

document.addEventListener('DOMContentLoaded', () => {
    
    // ── Constants ──────────────────────────────────────────────
    const REFRESH_INTERVAL = 30000;
    const MAX_CONCURRENT = 10;
    
    // ── DOM Elements ───────────────────────────────────────────
    const els = {
        totalBuses: document.getElementById('stat-total-buses'),
        avgDelay: document.getElementById('stat-avg-delay'),
        maxDelay: document.getElementById('stat-max-delay'),
        maxDelayLine: document.getElementById('stat-max-delay-line'),
        noGps: document.getElementById('stat-no-gps'),
        linesStats: document.getElementById('lines-stats-container'),
        tableBody: document.getElementById('buses-table-body'),
        lastUpdate: document.getElementById('last-update'),
        filterInput: document.getElementById('table-filter')
    };

    let allBusesData = []; // Store current cycle data for filtering

    // ── Helpers (Copied/Adapted from liveBusMap.js) ───────────
    
    function timeToSec(t) {
        if (!t) return 0;
        const p = t.split(':');
        const sec = (+p[0]) * 3600 + (+p[1]) * 60 + (p[2] ? +p[2] : 0);
        return sec % 86400; // Normalizza 24:xx a 00:xx
    }
    
    function secToTime(t) {
        if (!t) return 0;
        const hours = Math.floor(t / 3600);
        const minutes = Math.floor((t % 3600) / 60);
        const seconds = t % 60;
        return `${String(hours).padStart(2,'0')}:${String(minutes).padStart(2,'0')}:${String(seconds).padStart(2,'0')}`;
    }

    function parseRealTime(rtStr, now) {
        if (!rtStr) return null;
        rtStr = rtStr.trim();
        if (rtStr.includes("'") || rtStr.includes("min")) {
            const minutes = parseInt(rtStr.replace(/[^0-9]/g, ''), 10);
            if (isNaN(minutes)) return null;
            const d = new Date(now);
            d.setMinutes(d.getMinutes() + minutes);
            return d.getHours() * 3600 + d.getMinutes() * 60 + d.getSeconds();
        }
        if (rtStr.includes(":")) return timeToSec(rtStr + ":00");
        return null;
    }

    // Simplified version of interpolateWithShape for just finding next stop
    function findNextStopWithDelay(stops, nowSec, delaySec = 0) {
        if (!stops || stops.length === 0) return null;
        const virtualNowSec = nowSec - delaySec;
        
        // Find current segment
        for (let i = 0; i < stops.length - 1; i++) {
            let aSec = timeToSec(stops[i].arrival_time);
            let bSec = timeToSec(stops[i + 1].arrival_time);
            
            // Gestione mezzanotte: se b < a, b è il giorno dopo
            if (bSec < aSec) bSec += 86400;

            if (virtualNowSec <= aSec) {
               // Before first stop or waiting at a stop
               return {
                   nextStop: stops[i].stop_name,
                   nextStopId: stops[i].data_url ? stops[i].data_url.split("-").slice(0, -2).join("-") : stops[i].stop_id,
                   nextTime: stops[i].arrival_time
               }; 
            }

            if (virtualNowSec >= aSec && virtualNowSec <= bSec) {
                // In transit between A and B
                return {
                    nextStop: stops[i+1].stop_name,
                    nextStopId: stops[i+1].data_url ? stops[i+1].data_url.split("-").slice(0, -2).join("-") : stops[i+1].stop_id,
                    nextTime: stops[i+1].arrival_time
                };
            }
        }
        
        // Past last stop?
        const last = stops[stops.length-1];
        if (virtualNowSec < timeToSec(last.arrival_time)) {
             return {
                   nextStop: last.stop_name,
                   nextStopId: last.data_url ? last.data_url.split("-").slice(0, -2).join("-") : last.stop_id,
                   nextTime: last.arrival_time
             };
        }
        
        return null; // Finished trip
    }

    // Cache for stop requests
    const stopCache = new Map();

    async function getRealTimeData(stopId, lineName, tripHeadsign, gtfsStopSec) {
         const now = Date.now();
         if (stopCache.has(stopId)) {
             const entry = stopCache.get(stopId);
             if (now - entry.ts < 30000) {
                 return findBestMatch(entry.data, lineName, tripHeadsign, gtfsStopSec);
             }
         }

         try {
             let url = `https://oraritemporeale.actv.it/aut/backend/passages/${stopId}-web-aut`;
             const res = await fetch(url);
             if (!res.ok) return null;
             const data = await res.json();
             stopCache.set(stopId, { ts: now, data });
             return findBestMatch(data, lineName, tripHeadsign, gtfsStopSec);
         } catch (e) {
             return null;
         }
    }

    function findBestMatch(passages, lineName, tripHeadsign, gtfsStopSec) {
        if (!Array.isArray(passages)) return null;
        
        // Candidates with same line
        const candidates = passages.filter(p => {
             const pLine = p.line ? p.line.split('_')[0] : '';
             return pLine === lineName;
        });
        if (candidates.length === 0) return null;

        let bestMatch = null;
        let bestScore = -Infinity;
        const now = Date.now();
        const MAX_TIME_DIFF = 3600;

        candidates.forEach(cand => {
            const candSec = parseRealTime(cand.time, now);
            if (candSec === null) return;

            let diff = Math.abs(candSec - gtfsStopSec);
            if (diff > 43200) diff = 86400 - diff;

            let timeScore = (diff <= MAX_TIME_DIFF) ? (1 - (diff / MAX_TIME_DIFF)) : -1;
            
            // Dest score
            let destScore = 0;
            const normCandDest = cand.destination ? cand.destination.toLowerCase() : '';
            const normGtfsDest = tripHeadsign.toLowerCase();
            if (normCandDest === normGtfsDest) destScore = 1;
            else if (normGtfsDest.includes(normCandDest) || normCandDest.includes(normGtfsDest)) destScore = 0.8;

            let totalScore = (timeScore >= 0) ? (timeScore * 0.7 + destScore * 0.3) : -1;

            if (totalScore > bestScore) {
                bestScore = totalScore;
                bestMatch = { candidate: cand, score: totalScore, candSec };
            }
        });

        if (bestMatch && bestMatch.score >= 0.3) {
            return {
                status: bestMatch.candidate.real ? 'REALTIME' : 'SCHEDULED_API_FLAG',
                rtTime: bestMatch.candSec,
                vehicle: bestMatch.candidate.vehicle
            };
        }
        return { status: 'SCHEDULED_NO_DATA' };
    }


    // ── Main Logic ─────────────────────────────────────────────

    async function loadDashboard() {
        els.lastUpdate.textContent = 'Aggiornamento in corso...';
        
        try {
            // 1. Get Active Trips
            const res = await fetch('/api/gtfs-bnr');
            const data = await res.json();
            const buses = data.buses || [];
            const nowSec = timeToSec(data.time);

            if (buses.length === 0) {
                renderEmpty();
                return;
            }

            // 2. Fetch Details Concurrently
            let processedBuses = [];
            let tasks = buses.map(bus => async () => {
                try {
                    // Fetch stops/shape
                    const posRes = await fetch(`/api/bus-position?tripId=${encodeURIComponent(bus.trip_id)}`);
                    if (!posRes.ok) return;
                    const posData = await posRes.json();
                    
                    // GTFS Position calculation
                    let pos = findNextStopWithDelay(posData.stops, nowSec);
                    if (!pos) return; // Trip ended?

                    // Realtime Check
                    let delayInfo = { status: 'SCHEDULED_NO_DATA', delaySec: 0 };
                    
                    if (pos.nextStopId) {
                        const gtfsStopSec = timeToSec(pos.nextTime);
                        const fetched = await getRealTimeData(
                            pos.nextStopId, 
                            bus.route_short_name, 
                            bus.trip_headsign, 
                            gtfsStopSec
                        );
                        
                        if (fetched && fetched.rtTime) {
                            delayInfo = fetched;
                            let diff = fetched.rtTime - gtfsStopSec;
                            if (diff > 43200) diff -= 86400;
                            else if (diff < -43200) diff += 86400;
                            delayInfo.delaySec = diff;
                            // Re-calculate pos with delay
                            // (Optional: simply update next stop time, but simpler to just keep next stop)
                        } else if (fetched) {
                            delayInfo = fetched; // captures SCHEDULED_API_FLAG
                        }
                    }

                    processedBuses.push({
                        ...bus,
                        ...pos,
                        ...delayInfo
                    });

                } catch (e) {
                    console.error("Error processing bus", bus.trip_id, e);
                }
            });

            // Run pool
            await parallelPool(tasks, MAX_CONCURRENT);

            allBusesData = processedBuses;
            renderDashboard();

        } catch (e) {
            console.error("Dashboard load error", e);
            els.lastUpdate.textContent = 'Errore aggiornamento';
        }
    }

    async function parallelPool(tasks, concurrency) {
        let idx = 0;
        async function worker() {
            while (idx < tasks.length) {
                const fn = tasks[idx++];
                try { await fn(); } catch(e){}
            }
        }
        const workers = Array(concurrency).fill(null).map(worker);
        await Promise.all(workers);
    }

    // ── Rendering ──────────────────────────────────────────────

    function renderEmpty() {
        els.totalBuses.textContent = '0';
        els.tableBody.innerHTML = '<tr><td colspan="7" style="text-align:center;">Nessun bus in servizio</td></tr>';
        els.lastUpdate.textContent = 'Aggiornato: ' + new Date().toLocaleTimeString();
    }

    function renderDashboard() {
        const filter = els.filterInput.value.toLowerCase();
        
        let filtered = allBusesData;
        if (filter) {
            filtered = allBusesData.filter(b => 
                b.route_short_name.toLowerCase().includes(filter) ||
                b.trip_headsign.toLowerCase().includes(filter) ||
                b.trip_id.toLowerCase().includes(filter) ||
                (b.vehicle && b.vehicle.includes(filter))
            );
        }

        // Stats Calculation
        const total = allBusesData.length;
        const noGps = allBusesData.filter(b => b.status !== 'REALTIME').length;
        
        let totalDelay = 0;
        let delayCount = 0;
        let maxDelay = -Infinity;
        let maxDelayBus = null;

        const lineCounts = {};

        allBusesData.forEach(b => {
             if (b.status === 'REALTIME') {
                 totalDelay += b.delaySec;
                 delayCount++;
                 if (b.delaySec > maxDelay) {
                     maxDelay = b.delaySec;
                     maxDelayBus = b;
                 }
             }

             // Line counts
             lineCounts[b.route_short_name] = (lineCounts[b.route_short_name] || 0) + 1;
        });
        
        const avgDelaySec = delayCount > 0 ? totalDelay / delayCount : 0;

        // Update DOM - Big Stats
        els.totalBuses.textContent = total;
        els.noGps.textContent = noGps;
        els.avgDelay.textContent = `${Math.round(avgDelaySec / 60)} min`;
        
        if (maxDelayBus) {
            els.maxDelay.textContent = `+${Math.round(maxDelay / 60)} m`;
            els.maxDelayLine.textContent = `Linea ${maxDelayBus.route_short_name}`;
        } else {
            els.maxDelay.textContent = '--';
            els.maxDelayLine.textContent = '-';
        }

        // Line Charts
        els.linesStats.innerHTML = '';
        Object.keys(lineCounts).sort((a,b) => parseInt(a)-parseInt(b)).forEach(line => {
             const div = document.createElement('div');
             div.className = 'line-stat-item';
             div.innerHTML = `<span class="line-badge">${line}</span> <span>${lineCounts[line]} bus</span>`;
             els.linesStats.appendChild(div);
        });

        // Table
        els.tableBody.innerHTML = '';
        
        // Sort: Realtime first, then by delay descending
        filtered.sort((a,b) => {
            if (a.status === 'REALTIME' && b.status !== 'REALTIME') return -1;
            if (a.status !== 'REALTIME' && b.status === 'REALTIME') return 1;
            return b.delaySec - a.delaySec;
        });

        filtered.forEach(b => {
            const tr = document.createElement('tr');
            
            let statusBadge = '';
            if (b.status === 'REALTIME') statusBadge = '<span class="status-badge status-realtime">Realtime</span>';
            else if (b.status === 'SCHEDULED_API_FLAG') statusBadge = '<span class="status-badge status-no-gps">No GPS</span>';
            else statusBadge = '<span class="status-badge status-scheduled">Schedulato</span>';

            let delayDisplay = '-';
            if (b.status === 'REALTIME') {
                const mins = Math.round(b.delaySec / 60);
                if (mins > 0) delayDisplay = `<span class="delay-positive">+${mins} min</span>`;
                else if (mins < 0) delayDisplay = `<span class="delay-early">${mins} min</span>`;
                else delayDisplay = `<span class="delay-on-time">In orario</span>`;
            }

            tr.innerHTML = `
                <td><span class="line-badge">${b.route_short_name}</span></td>
                <td>${b.trip_headsign}</td>
                <td class="trip-id-cell">${b.trip_id}</td>
                <td>${b.nextStop} <small style="color:#666">(${b.nextTime ? b.nextTime.substr(0,5) : ''})</small></td>
                <td>${statusBadge}</td>
                <td>${delayDisplay}</td>
                <td>${b.vehicle || '-'}</td>
            `;
            els.tableBody.appendChild(tr);
        });

        els.lastUpdate.textContent = 'Aggiornato: ' + new Date().toLocaleTimeString();
    }


    // ── Init ───────────────────────────────────────────────────
    loadDashboard();
    setInterval(loadDashboard, REFRESH_INTERVAL);

    els.filterInput.addEventListener('input', () => {
        renderDashboard(); // Re-render with local filter
    });

});
