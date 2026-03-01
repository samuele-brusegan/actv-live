/**
 * Utility condivise tra liveBusMap.js, adminDashboard.js, linesMap.js.
 * Estratte per testabilità e riuso.
 */

/**
 * Converte "HH:MM:SS" o "HH:MM" in secondi dal mezzanotte
 */
function timeToSec(t) {
    if (!t) return 0;
    const p = t.split(':');
    const sec = (+p[0]) * 3600 + (+p[1]) * 60 + (p[2] ? +p[2] : 0);
    return sec % 86400; // Normalizza 24:xx a 00:xx
}

/**
 * Converte secondi dal mezzanotte in "HH:MM:SS"
 */
function secToTime(t) {
    if (!t) return 0;
    const hours = Math.floor(t / 3600);
    const minutes = Math.floor((t % 3600) / 60);
    const seconds = t % 60;
    return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
}

/**
 * Calcola la distanza tra due coordinate (m) usando Haversine
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
 * Parsing avanzato dell'orario real-time
 * Supporta: "HH:MM", "MM min", "MM'"
 */
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

/**
 * Genera un colore deterministico per una linea basato sul suo nome
 */
function getDeterministicColor(name, isColored, LINE_COLORS) {
    if (!isColored) return '#AAA';
    let hash = 0;
    for (let i = 0; i < name.length; i++) {
        hash = name.charCodeAt(i) + ((hash << 5) - hash);
    }
    return LINE_COLORS[Math.abs(hash) % LINE_COLORS.length];
}

/**
 * Filtra bus per query di ricerca
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

/**
 * Trova la prossima fermata considerando il ritardo
 */
function findNextStopWithDelay(stops, nowSec, delaySec = 0) {
    if (!stops || stops.length === 0) return null;
    const virtualNowSec = nowSec - delaySec;

    for (let i = 0; i < stops.length - 1; i++) {
        let aSec = timeToSec(stops[i].arrival_time);
        let bSec = timeToSec(stops[i + 1].arrival_time);
        if (bSec < aSec) bSec += 86400;

        if (virtualNowSec <= aSec) {
            return {
                nextStop: stops[i].stop_name,
                nextStopId: stops[i].data_url ? stops[i].data_url.split("-").slice(0, -2).join("-") : stops[i].stop_id,
                nextTime: stops[i].arrival_time
            };
        }

        if (virtualNowSec >= aSec && virtualNowSec <= bSec) {
            return {
                nextStop: stops[i + 1].stop_name,
                nextStopId: stops[i + 1].data_url ? stops[i + 1].data_url.split("-").slice(0, -2).join("-") : stops[i + 1].stop_id,
                nextTime: stops[i + 1].arrival_time
            };
        }
    }

    const last = stops[stops.length - 1];
    if (virtualNowSec < timeToSec(last.arrival_time)) {
        return {
            nextStop: last.stop_name,
            nextStopId: last.data_url ? last.data_url.split("-").slice(0, -2).join("-") : last.stop_id,
            nextTime: last.arrival_time
        };
    }

    return null;
}

if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        timeToSec,
        secToTime,
        getDist,
        parseRealTime,
        getDeterministicColor,
        filterBuses,
        findNextStopWithDelay
    };
}
