/**
 * Gestione notifiche push per ritardi ACTV.
 * Monitora le fermate preferite e invia notifiche locali quando
 * viene rilevato un ritardo superiore alla soglia configurata.
 */

const DELAY_THRESHOLD_KEY = 'notification_delay_threshold';
const NOTIFICATIONS_ENABLED_KEY = 'notifications_enabled';
const MONITORED_STOPS_KEY = 'monitored_stops';
const CHECK_INTERVAL = 60000; // 1 minuto

let checkIntervalId = null;

/** Registra il Service Worker */
async function registerServiceWorker() {
    if (!('serviceWorker' in navigator)) return null;

    try {
        const registration = await navigator.serviceWorker.register('/sw.js');
        return registration;
    } catch (error) {
        console.error('Errore registrazione SW:', error);
        return null;
    }
}

/** Richiede il permesso per le notifiche */
async function requestNotificationPermission() {
    if (!('Notification' in window)) return 'denied';

    if (Notification.permission === 'granted') return 'granted';
    if (Notification.permission === 'denied') return 'denied';

    return await Notification.requestPermission();
}

/** Verifica se le notifiche sono abilitate */
function areNotificationsEnabled() {
    return localStorage.getItem(NOTIFICATIONS_ENABLED_KEY) === 'true';
}

/** Imposta lo stato delle notifiche */
function setNotificationsEnabled(enabled) {
    localStorage.setItem(NOTIFICATIONS_ENABLED_KEY, String(enabled));
}

/** Ottiene la soglia di ritardo configurata (in minuti) */
function getDelayThreshold() {
    const val = localStorage.getItem(DELAY_THRESHOLD_KEY);
    return val ? parseInt(val, 10) : 5;
}

/** Imposta la soglia di ritardo (in minuti) */
function setDelayThreshold(minutes) {
    localStorage.setItem(DELAY_THRESHOLD_KEY, String(minutes));
}

/** Ottiene la lista di fermate monitorate */
function getMonitoredStops() {
    try {
        const data = localStorage.getItem(MONITORED_STOPS_KEY);
        return data ? JSON.parse(data) : [];
    } catch (e) {
        return [];
    }
}

/** Aggiunge una fermata al monitoraggio */
function addMonitoredStop(stopId, stopName) {
    const stops = getMonitoredStops();
    if (stops.some(s => s.id === stopId)) return;
    stops.push({ id: stopId, name: stopName });
    localStorage.setItem(MONITORED_STOPS_KEY, JSON.stringify(stops));
}

/** Rimuove una fermata dal monitoraggio */
function removeMonitoredStop(stopId) {
    const stops = getMonitoredStops().filter(s => s.id !== stopId);
    localStorage.setItem(MONITORED_STOPS_KEY, JSON.stringify(stops));
}

/** Verifica se una fermata è monitorata */
function isStopMonitored(stopId) {
    return getMonitoredStops().some(s => s.id === stopId);
}

/** Invia una notifica locale tramite il Service Worker */
async function sendLocalNotification(title, body, tag) {
    if (Notification.permission !== 'granted') return;

    const registration = await navigator.serviceWorker.ready;
    await registration.showNotification(title, {
        body: body,
        icon: '/pwa/web-app-manifest-192x192.png',
        badge: '/pwa/favicon-96x96.png',
        vibrate: [200, 100, 200],
        tag: tag || 'delay-alert',
        requireInteraction: false
    });
}

const IMMINENT_STATE_KEY = 'imminent_state';
const IMMINENT_THRESHOLD_MIN = 1; // <=1 min (o "departure") = bus imminente

/** Converte il tempo real-time ACTV in minuti ("departure"->0, "5'"->5). */
function passageMinutes(timeStr) {
    if (timeStr === 'departure') return 0;
    const m = String(timeStr || '').match(/(\d+)/);
    return m ? parseInt(m[1], 10) : null;
}

/**
 * Confronta lo stato precedente (mappa "linea|dest" -> minuti) con i passaggi
 * correnti e individua i bus appena partiti: erano imminenti (<=1 min) e ora
 * non ci sono più o il loro orario è "saltato" alla corsa successiva.
 * Funzione pura per testabilità.
 * @returns {{departed: Array<{line:string,dest:string}>, state: Object}}
 */
function detectDepartures(prevState, passages) {
    const current = {};
    (Array.isArray(passages) ? passages : []).forEach(p => {
        if (!p.real) return;
        const min = passageMinutes(p.time);
        if (min === null) return;
        const line = (p.line || '').split('_')[0];
        const dest = (p.destination || '').trim();
        const key = `${line}|${dest}`;
        if (current[key] === undefined || min < current[key]) current[key] = min;
    });

    const departed = [];
    const prev = prevState || {};
    Object.keys(prev).forEach(key => {
        if (prev[key] > IMMINENT_THRESHOLD_MIN) return; // non era imminente
        const now = current[key];
        if (now === undefined || now > prev[key]) {
            const [line, dest] = key.split('|');
            departed.push({ line, dest });
        }
    });

    return { departed, state: current };
}

/**
 * Controlla i ritardi per le fermate monitorate e notifica i bus appena passati.
 * Usa l'API ACTV real-time e confronta i tempi.
 */
async function checkDelays() {
    if (!areNotificationsEnabled()) return;

    const stops = getMonitoredStops();
    const threshold = getDelayThreshold();
    const notifiedKey = 'last_notified_delays';

    let lastNotified = {};
    try {
        lastNotified = JSON.parse(sessionStorage.getItem(notifiedKey) || '{}');
    } catch (e) { /* ignore */ }

    let imminentState = {};
    try {
        imminentState = JSON.parse(sessionStorage.getItem(IMMINENT_STATE_KEY) || '{}');
    } catch (e) { /* ignore */ }

    for (const stop of stops) {
        try {
            const response = await fetch(
                `https://oraritemporeale.actv.it/aut/backend/passages/${stop.id}-web-aut`,
                { cache: 'no-cache' }
            );

            if (!response.ok) continue;

            const passages = await response.json();
            if (!Array.isArray(passages)) continue;

            // ── Notifiche ritardo ──
            for (const p of passages) {
                if (!p.real || !p.time) continue;

                const delayMatch = String(p.time).match(/(\d+)/);
                if (!delayMatch) continue;

                const delayMin = parseInt(delayMatch[1], 10);

                if (delayMin >= threshold) {
                    const notifKey = `${stop.id}_${p.line}_${p.destination}`;
                    const lastTime = lastNotified[notifKey];

                    if (!lastTime || (Date.now() - lastTime) > 300000) {
                        const lineName = (p.line || '').split('_')[0];
                        await sendLocalNotification(
                            `Ritardo Linea ${lineName}`,
                            `${lineName} verso ${p.destination} - ritardo ${delayMin} min (fermata ${stop.name})`,
                            notifKey
                        );
                        lastNotified[notifKey] = Date.now();
                    }
                }
            }

            // ── Notifiche "bus passato" ──
            // Al primo giro lo stato precedente è assente: nessuna falsa notifica.
            const hadState = Object.prototype.hasOwnProperty.call(imminentState, stop.id);
            const { departed, state } = detectDepartures(imminentState[stop.id], passages);
            imminentState[stop.id] = state;

            if (hadState) {
                for (const d of departed) {
                    await sendLocalNotification(
                        `Bus passato`,
                        `Il bus ${d.line} verso ${d.dest} è appena passato a ${stop.name}`,
                        `passed_${stop.id}_${d.line}_${d.dest}`
                    );
                }
            }
        } catch (error) {
            console.warn(`Errore check ritardi per ${stop.id}:`, error);
        }
    }

    sessionStorage.setItem(notifiedKey, JSON.stringify(lastNotified));
    sessionStorage.setItem(IMMINENT_STATE_KEY, JSON.stringify(imminentState));
}

/** Avvia il monitoraggio periodico dei ritardi */
function startDelayMonitoring() {
    if (checkIntervalId) return;
    if (!areNotificationsEnabled()) return;

    checkDelays();
    checkIntervalId = setInterval(checkDelays, CHECK_INTERVAL);
}

/** Ferma il monitoraggio periodico */
function stopDelayMonitoring() {
    if (checkIntervalId) {
        clearInterval(checkIntervalId);
        checkIntervalId = null;
    }
}

/**
 * Abilita/disabilita le notifiche.
 * Se si abilita, richiede il permesso e avvia il monitoraggio.
 */
async function toggleNotifications() {
    if (areNotificationsEnabled()) {
        setNotificationsEnabled(false);
        stopDelayMonitoring();
        return false;
    }

    const permission = await requestNotificationPermission();
    if (permission !== 'granted') return false;

    setNotificationsEnabled(true);
    startDelayMonitoring();
    return true;
}

/** Inizializzazione automatica al caricamento */
function initNotifications() {
    registerServiceWorker();

    if (areNotificationsEnabled() && Notification.permission === 'granted') {
        startDelayMonitoring();
    }
}

// Auto-init quando il DOM è pronto
if (typeof document !== 'undefined') {
    document.addEventListener('DOMContentLoaded', initNotifications);
}

// Export per Jest
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        areNotificationsEnabled, setNotificationsEnabled,
        getDelayThreshold, setDelayThreshold,
        getMonitoredStops, addMonitoredStop, removeMonitoredStop, isStopMonitored,
        toggleNotifications, detectDepartures, passageMinutes
    };
}
