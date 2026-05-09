/**
 * Storico ritardi ACTV.
 * Registra i ritardi osservati durante la navigazione e fornisce
 * statistiche aggregate per linea, fermata e fascia oraria.
 */

const DELAY_HISTORY_KEY = 'delay_history';
const MAX_HISTORY_ENTRIES = 500;

/**
 * Ottiene lo storico ritardi salvato
 * @returns {Array} Array di record ritardo
 */
function getDelayHistory() {
    try {
        var data = localStorage.getItem(DELAY_HISTORY_KEY);
        return data ? JSON.parse(data) : [];
    } catch (e) {
        return [];
    }
}

/**
 * Salva un record ritardo
 * @param {Object} record - { line, destination, stop, stopName, delay, timestamp }
 */
function recordDelay(record) {
    if (!record || !record.line || typeof record.delay !== 'number') return;

    var history = getDelayHistory();
    history.push({
        line: record.line,
        destination: record.destination || '',
        stop: record.stop || '',
        stopName: record.stopName || '',
        delay: record.delay,
        timestamp: record.timestamp || Date.now(),
        hour: new Date(record.timestamp || Date.now()).getHours()
    });

    if (history.length > MAX_HISTORY_ENTRIES) {
        history = history.slice(-MAX_HISTORY_ENTRIES);
    }

    localStorage.setItem(DELAY_HISTORY_KEY, JSON.stringify(history));
}

/**
 * Calcola statistiche per linea
 * @returns {Array} Array ordinato per ritardo medio decrescente
 */
function getStatsByLine() {
    var history = getDelayHistory();
    var byLine = {};

    history.forEach(function(r) {
        var lineName = (r.line || '').split('_')[0];
        if (!byLine[lineName]) {
            byLine[lineName] = { line: lineName, delays: [], total: 0, count: 0 };
        }
        byLine[lineName].delays.push(r.delay);
        byLine[lineName].total += r.delay;
        byLine[lineName].count++;
    });

    return Object.values(byLine).map(function(s) {
        s.avgDelay = Math.round((s.total / s.count) * 10) / 10;
        s.maxDelay = Math.max.apply(null, s.delays);
        s.minDelay = Math.min.apply(null, s.delays);
        return s;
    }).sort(function(a, b) { return b.avgDelay - a.avgDelay; });
}

/**
 * Calcola statistiche per fascia oraria
 * @returns {Array} 24 elementi, uno per ogni ora del giorno
 */
function getStatsByHour() {
    var history = getDelayHistory();
    var byHour = [];

    for (var i = 0; i < 24; i++) {
        byHour.push({ hour: i, total: 0, count: 0, avgDelay: 0 });
    }

    history.forEach(function(r) {
        var h = r.hour !== undefined ? r.hour : new Date(r.timestamp).getHours();
        if (h >= 0 && h < 24) {
            byHour[h].total += r.delay;
            byHour[h].count++;
        }
    });

    byHour.forEach(function(s) {
        s.avgDelay = s.count > 0 ? Math.round((s.total / s.count) * 10) / 10 : 0;
    });

    return byHour;
}

/**
 * Calcola statistiche generali
 * @returns {Object} { totalRecords, avgDelay, maxDelay, worstLine, bestHour, worstHour }
 */
function getOverallStats() {
    var history = getDelayHistory();

    if (history.length === 0) {
        return { totalRecords: 0, avgDelay: 0, maxDelay: 0, worstLine: '-', bestHour: '-', worstHour: '-' };
    }

    var totalDelay = 0;
    var maxDelay = 0;

    history.forEach(function(r) {
        totalDelay += r.delay;
        if (r.delay > maxDelay) maxDelay = r.delay;
    });

    var byLine = getStatsByLine();
    var byHour = getStatsByHour().filter(function(h) { return h.count > 0; });

    var worstLine = byLine.length > 0 ? byLine[0].line : '-';
    var bestHour = '-';
    var worstHour = '-';

    if (byHour.length > 0) {
        byHour.sort(function(a, b) { return a.avgDelay - b.avgDelay; });
        bestHour = byHour[0].hour + ':00';
        worstHour = byHour[byHour.length - 1].hour + ':00';
    }

    return {
        totalRecords: history.length,
        avgDelay: Math.round((totalDelay / history.length) * 10) / 10,
        maxDelay: maxDelay,
        worstLine: worstLine,
        bestHour: bestHour,
        worstHour: worstHour
    };
}

/**
 * Svuota lo storico
 */
function clearDelayHistory() {
    localStorage.removeItem(DELAY_HISTORY_KEY);
}

/**
 * Estrae il ritardo in minuti da una stringa tempo ACTV
 * @param {string} timeStr - es. "4'" o "12'" o "departure"
 * @returns {number|null} ritardo in minuti, null se non parsabile
 */
function parseDelayMinutes(timeStr) {
    if (!timeStr || timeStr === 'departure') return null;
    var match = String(timeStr).match(/^(\d+)/);
    return match ? parseInt(match[1], 10) : null;
}

/**
 * Registra automaticamente i ritardi dai passaggi real-time
 * @param {Array} passages - Array di passaggi ACTV
 * @param {string} stopId - ID della fermata
 * @param {string} stopName - Nome della fermata
 */
function recordPassageDelays(passages, stopId, stopName) {
    if (!Array.isArray(passages)) return;

    var now = Date.now();

    passages.forEach(function(p) {
        if (!p.real) return;

        var delay = parseDelayMinutes(p.time);
        if (delay === null || delay < 1) return;

        recordDelay({
            line: p.line || '',
            destination: p.destination || '',
            stop: stopId,
            stopName: stopName,
            delay: delay,
            timestamp: now
        });
    });
}

// Export per Jest
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        getDelayHistory, recordDelay, getStatsByLine, getStatsByHour,
        getOverallStats, clearDelayHistory, parseDelayMinutes, recordPassageDelays
    };
}
