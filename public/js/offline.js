/**
 * Gestione modalita' offline per ACTV Live.
 * Registra il Service Worker, gestisce lo stato online/offline e
 * fornisce utility per il caching dei dati GTFS.
 */

const OFFLINE_INDICATOR_ID = 'offline-indicator';

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

/** Verifica se il browser e' online */
function isOnline() {
    return navigator.onLine;
}

/** Mostra/nascondi indicatore offline */
function updateOfflineIndicator() {
    let indicator = document.getElementById(OFFLINE_INDICATOR_ID);

    if (!isOnline()) {
        if (!indicator) {
            indicator = document.createElement('div');
            indicator.id = OFFLINE_INDICATOR_ID;
            indicator.className = 'offline-indicator';
            indicator.innerHTML = '<span class="offline-dot"></span> Offline - dati dalla cache';
            document.body.prepend(indicator);
        }
        indicator.style.display = 'flex';
    } else {
        if (indicator) {
            indicator.style.display = 'none';
        }
    }
}

/** Richiede al SW di pre-cacheare i dati GTFS */
async function precacheGtfsData() {
    if (!('serviceWorker' in navigator)) return false;

    const registration = await navigator.serviceWorker.ready;
    if (registration.active) {
        registration.active.postMessage({ type: 'CACHE_GTFS' });
        return true;
    }
    return false;
}

/** Svuota tutte le cache */
async function clearAllCaches() {
    if (!('serviceWorker' in navigator)) return false;

    return new Promise((resolve) => {
        const channel = new MessageChannel();
        channel.port1.onmessage = (event) => {
            resolve(event.data.cleared || false);
        };

        navigator.serviceWorker.ready.then((registration) => {
            if (registration.active) {
                registration.active.postMessage({ type: 'CLEAR_CACHE' }, [channel.port2]);
            } else {
                resolve(false);
            }
        });
    });
}

/** Ottiene la dimensione stimata della cache */
async function getCacheSize() {
    if (!('storage' in navigator && 'estimate' in navigator.storage)) {
        return null;
    }

    try {
        const estimate = await navigator.storage.estimate();
        return {
            usage: estimate.usage || 0,
            quota: estimate.quota || 0,
            usageMB: ((estimate.usage || 0) / (1024 * 1024)).toFixed(2),
            quotaMB: ((estimate.quota || 0) / (1024 * 1024)).toFixed(0)
        };
    } catch (e) {
        return null;
    }
}

/** Inizializza il supporto offline */
function initOffline() {
    registerServiceWorker();

    // Monitora stato connessione
    window.addEventListener('online', updateOfflineIndicator);
    window.addEventListener('offline', updateOfflineIndicator);

    // Check iniziale
    updateOfflineIndicator();

    // Pre-cache GTFS data al primo caricamento online
    if (isOnline() && !sessionStorage.getItem('gtfs_cached')) {
        setTimeout(() => {
            precacheGtfsData();
            sessionStorage.setItem('gtfs_cached', 'true');
        }, 5000);
    }
}

// Auto-init
if (typeof document !== 'undefined') {
    document.addEventListener('DOMContentLoaded', initOffline);
}

// Export per Jest
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { isOnline, updateOfflineIndicator, getCacheSize, precacheGtfsData, clearAllCaches };
}
