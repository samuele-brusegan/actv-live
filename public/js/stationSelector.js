/**
 * Logica per la selezione di una fermata o di un indirizzo.
 * Gestisce la ricerca locale, i preferiti, la cronologia e i suggerimenti geografici.
 */

let selectedStop = null;
const urlParams = new URLSearchParams(window.location.search);
const selectionType = urlParams.get('type') ?? 'origin'; // 'origin' o 'destination'

let allStops = [];
let addressResults = [];
let debounceTimer;

/**
 * Caricamento e Dati
 */

/** Carica l'elenco completo delle fermate dall'API */
async function loadStops() {
    // Le liste locali non devono dipendere dalla rete: mostrale subito.
    renderFavorites();
    renderRecent();
    try {
        const response = await fetch('/api/stops', { cache: 'no-store' });
        if (!response.ok) throw new Error('Catalogo locale non disponibile');
        const responseText = await response.text();
        let data;
        try {
            data = JSON.parse(responseText);
        } catch (parseError) {
            throw new Error('Catalogo locale non valido');
        }
        const localStops = Array.isArray(data) ? data : Object.values(data || {});
        const validLocalStops = localStops.filter(stop => stop && typeof stop.stop_name === 'string');
        let sourceStops = validLocalStops;
        // Endpoint separato: evita di perdere le fermate acquee se un
        // reverse-proxy o una vecchia cache restituisce solo gli autobus.
        try {
            const waterResponse = await fetch('/api/navigation/stops', { cache: 'no-store' });
            if (waterResponse.ok) {
                const waterStops = await waterResponse.json();
                if (Array.isArray(waterStops)) sourceStops = sourceStops.concat(waterStops);
            }
        } catch (waterError) {
            console.warn('Catalogo Navigazione non disponibile:', waterError);
        }
        if (!sourceStops.length) sourceStops = await fetchExternalStops();

        // Mappa per raggruppare fermate con lo stesso nome
        const stopsMap = new Map();

        sourceStops.forEach(stop => {
            const normalizedName = String(stop.stop_name).trim().toLowerCase().replace(/\s+/g, ' ');
            const cleanName = stop.stop_name.trim();

            const stopId = stop.stop_id || stop.id;
            if (!stopId) return;

            if (stopsMap.has(normalizedName)) {
                stopsMap.get(normalizedName).ids.push(stopId);
                const service = stop.service || 'automobilistico';
                if (!stopsMap.get(normalizedName).services.includes(service)) stopsMap.get(normalizedName).services.push(service);
            } else {
                stopsMap.set(normalizedName, {
                    ids: [stopId],
                    name: cleanName,
                    lat: stop.stop_lat,
                    lng: stop.stop_lon,
                    services: [stop.service || 'automobilistico']
                });
            }
        });

        // Converte la mappa in array
        allStops = Array.from(stopsMap.values()).map(stop => ({
            id: stop.ids[0],
            ids: stop.ids,
            name: stop.name,
            lat: stop.lat,
            lng: stop.lng,
            type: 'stop'
        }));

        const allStopsSection = document.getElementById('all-stops-section');
        if (allStopsSection) allStopsSection.style.display = allStops.length ? 'block' : 'none';
        renderAllResults(allStops, [], [], true);
    } catch (error) {
        console.error('Errore nel caricamento delle fermate:', error);
        try {
            const fallbackStops = await fetchExternalStops();
            if (fallbackStops.length) {
                allStops = fallbackStops;
                const allStopsSection = document.getElementById('all-stops-section');
                if (allStopsSection) allStopsSection.style.display = 'block';
                renderAllResults(allStops, [], [], true);
            }
        } catch (fallbackError) {
            console.error('Fallback fermate non disponibile:', fallbackError);
        }
    }
}

async function fetchExternalStops() {
    const response = await fetch('https://oraritemporeale.actv.it/aut/backend/page/stops', { cache: 'no-store' });
    if (!response.ok) throw new Error('Catalogo ACTV non disponibile');
    const data = await response.json();
    return (Array.isArray(data) ? data : []).map(stop => {
        const rawDescription = String(stop.description || stop.name || '');
        const ids = [...rawDescription.matchAll(/\[(\d+)\]/g)].map(match => match[1]);
        const fallbackId = String(stop.name || '').replace(/-web-aut|-web/g, '').split('-')[0];
        const name = rawDescription.replace(/\[\d+\]/g, '').trim();
        return {
            id: ids[0] || fallbackId,
            ids: ids.length ? ids : [fallbackId],
            name: name || `Fermata ${fallbackId}`,
            stop_name: name || `Fermata ${fallbackId}`,
            stop_lat: Number(stop.latitude),
            stop_lon: Number(stop.longitude),
            type: 'stop',
            services: stop.services || ['automobilistico']
        };
    }).filter(stop => stop.name && Number.isFinite(stop.stop_lat) && Number.isFinite(stop.stop_lon));
}

/**
 * Rendering Liste
 */

function renderFavorites() {
    const favorites = readStoredArray('favorite_stops');
    const container = document.getElementById('favorites-list');
    if (!container) return;

    container.innerHTML = favorites.map(stop => createStopCardHTML(stop, true)).join('');
    const section = document.getElementById('favorites-section');
    if (section) section.style.display = favorites.length ? 'block' : 'none';
}

function renderRecent() {
    const recent = readStoredArray('recent_stops').filter(Boolean);
    const container = document.getElementById('recent-list');
    if (!container) return;

    container.innerHTML = recent.slice(0, 5).map(stop => createStopCardHTML(stop, false)).join('');
    const section = document.getElementById('recent-section');
    if (section) section.style.display = recent.length ? 'block' : 'none';
}

/** Genera l'HTML per una card fermata o indirizzo */
function createStopCardHTML(stop, isFavorite) {
    if (!stop) return '';

    // Caso Indirizzo (da Nominatim)
    if (stop.type === 'address') {
        const displayName = stop.parsedName || stop.name;
        // Escaping per l'attributo onclick
        const stopJson = JSON.stringify(stop).replace(/'/g, "&#39;");

        return `
            <div class="stop-card" onclick='handleAddressSelection(${stopJson}, this)'>
                <div class="stop-icon address-icon">📍</div>
                <div class="stop-content">
                    <div class="stop-name">${displayName}</div>
                    <div class="stop-meta">Indirizzo</div>
                </div>
            </div>`;
    }

    // Caso Fermata (da GTFS)
    let displayName = stop.name;
    if (displayName.includes("web")) {
        displayName = displayName.replace("web-aut", "").replace("web", "").trim();
    }

    // Utilizza il componente globale StopCard se disponibile
    if (typeof StopCard !== 'undefined') {
        return StopCard.create(stop, {
            isFavorite: isFavorite,
            onClick: handleStopSelection,
            showIds: true
        });
    }

    return `<div class="stop-card" onclick='handleStopSelection(${JSON.stringify(stop).replace(/'/g, "&#39;")}, this)'>
                <div class="stop-icon">🚏</div>
                <div class="stop-content">
                    <div class="stop-name">${displayName}</div>
                    <div class="stop-meta">${(stop.services || []).includes('navigation') ? 'Bus + Navigazione' : 'Bus'}</div>
                </div>
            </div>`;
}

/**
 * Gestione Selezione
 */

function handleStopSelection(stop, element) {
    selectedStop = { ...stop, type: 'stop' };
    updateActiveUI(element);
    addToRecent(selectedStop);
}

function handleAddressSelection(address, element) {
    selectedStop = { ...address, type: 'address' };
    updateActiveUI(element);
    addToRecent(selectedStop);
}

/** Evidenzia visivamente l'elemento selezionato */
function updateActiveUI(element) {
    document.querySelectorAll('.stop-card').forEach(card => {
        card.classList.remove('selected');
        card.style.background = ''; // Reset inline styles if any
    });

    if (element) {
        element.classList.add('selected');
    }
}

/** Aggiunge una fermata alla cronologia locale */
function addToRecent(stop) {
    if (!stop) return;
    let recent = JSON.parse(localStorage.getItem('recent_stops') || '[]').filter(Boolean);

    // Rimuove eventuali duplicati
    recent = recent.filter(s => {
        if (stop.type === 'address') return s.name !== stop.name;
        return s.id !== stop.id;
    });

    recent.unshift(stop);
    recent = recent.slice(0, 10); // Tiene solo le ultime 10
    localStorage.setItem('recent_stops', JSON.stringify(recent));
}

/** Conferma la scelta e torna al cercapercorsi */
function confirmSelection() {
    if (!selectedStop) {
        if (window.actvAlert) actvAlert('Seleziona una fermata o un indirizzo prima di continuare.', 'Fermata non selezionata');
        return;
    }

    localStorage.setItem(`route_${selectionType}`, JSON.stringify(selectedStop));
    if (window.parent !== window) {
        window.parent.postMessage({ type: 'actv-station-selected', selectionType, stop: selectedStop }, window.location.origin);
        return;
    }
    window.location.href = '/route-finder';
}

function readStoredArray(key) {
    try {
        const value = JSON.parse(localStorage.getItem(key) || '[]');
        return Array.isArray(value) ? value.filter(Boolean) : [];
    } catch (error) {
        return [];
    }
}

function cancelSelection() {
    if (window.parent !== window) {
        window.parent.postMessage({ type: 'actv-station-picker-cancelled' }, window.location.origin);
        return;
    }
    window.location.href = '/route-finder';
}

function normalizeStopSearch(value) {
    return String(value || '')
        .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
        .toLowerCase().replace(/[’'`.-]/g, ' ').replace(/[^a-z0-9\s]/g, ' ')
        .split(/\s+/).filter(Boolean)
        .map(token => ({ santa: 's', san: 's' }[token] || token));
}

function stopSearchScore(name, query) {
    const queryTokens = normalizeStopSearch(query);
    const nameTokens = normalizeStopSearch(name);
    if (!queryTokens.length) return 0;
    let score = 0;
    for (const queryToken of queryTokens) {
        let best = Infinity;
        nameTokens.forEach(nameToken => {
            if (nameToken === queryToken) best = 0;
            else if (nameToken.startsWith(queryToken) || queryToken.startsWith(nameToken)) best = Math.min(best, 1);
            else if (queryToken.length >= 4 && nameToken.length >= 4) {
                const distance = levenshteinDistance(queryToken, nameToken);
                if (distance <= Math.max(1, Math.floor(queryToken.length / 4))) best = Math.min(best, 2 + distance);
            }
        });
        if (best === Infinity) return Infinity;
        score += best;
    }
    const normalizedQuery = normalizeStopSearch(query).join(' ');
    const normalizedName = normalizeStopSearch(name).join(' ');
    if (normalizedName === normalizedQuery) score -= 3;
    else if (normalizedName.includes(normalizedQuery)) score -= 1;
    return score;
}

function levenshteinDistance(a, b) {
    const row = Array.from({ length: b.length + 1 }, (_, index) => index);
    for (let i = 1; i <= a.length; i++) {
        let previous = row[0];
        row[0] = i;
        for (let j = 1; j <= b.length; j++) {
            const current = row[j];
            row[j] = Math.min(row[j] + 1, row[j - 1] + 1, previous + (a[i - 1] === b[j - 1] ? 0 : 1));
            previous = current;
        }
    }
    return row[b.length];
}

/**
 * Logica di Ricerca
 */

/** Filtra le fermate e cerca indirizzi in base all'input utente */
function filterStops() {
    const query = document.getElementById('search-input').value.toLowerCase().trim();

    const favoritesSection = document.getElementById('favorites-section');
    const recentSection = document.getElementById('recent-section');
    const allStopsSection = document.getElementById('all-stops-section');

    if (query.length === 0) {
        renderFavorites();
        renderRecent();
        if (allStopsSection) allStopsSection.style.display = allStops.length ? 'block' : 'none';
        renderAllResults(allStops, [], [], true);
        return;
    }

    if (favoritesSection) favoritesSection.style.display = 'none';
    if (recentSection) recentSection.style.display = 'none';
    if (allStopsSection) allStopsSection.style.display = 'block';

    // 1. Filtro fermate locali
    const filteredLocalStops = allStops
        .map(stop => ({ stop, score: stopSearchScore(stop.name, query) }))
        .filter(item => item.score !== Infinity)
        .sort((a, b) => a.score - b.score)
        .map(item => item.stop);

    // 2. Filtro suggerimenti dai preferiti/recenti
    const favorites = readStoredArray('favorite_stops');
    const recent = readStoredArray('recent_stops');

    const combined = [...favorites, ...recent];
    const seenKeys = new Set();
    const suggestions = combined.filter(item => {
        if (!item || stopSearchScore(item.name, query) === Infinity) return false;
        const key = item.type === 'address' ? item.name : item.id;
        if (seenKeys.has(key)) return false;
        seenKeys.add(key);
        return true;
    });

    // 3. Ricerca indirizzi esterna (debounce)
    if (query.length > 2) {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => fetchAddresses(query), 500);
    } else {
        addressResults = [];
        renderAllResults(filteredLocalStops, [], suggestions);
    }

    // Rendering iniziale immediato
    renderAllResults(filteredLocalStops, addressResults, suggestions);
}

/** Effettua la ricerca su Nominatim (OpenStreetMap) */
async function fetchAddresses(query) {
    try {
        // Area geografica di Venezia (lon1,lat1,lon2,lat2)
        const viewbox = '12.1,45.3,12.6,45.5';
        const url = `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&viewbox=${viewbox}&bounded=1&limit=5`;

        const response = await fetch(url);
        const data = await response.json();

        addressResults = data.map(item => {
            const parts = item.display_name.split(',');
            return {
                type: 'address',
                id: `addr_${item.place_id}`,
                name: parts[0],
                fullName: item.display_name,
                parsedName: parts.slice(0, -3).join(','),
                lat: item.lat,
                lng: item.lon,
                ids: []
            };
        });

        // Riesegue il filtro locale per consistenza nel re-render
        const currentQuery = document.getElementById('search-input').value.toLowerCase();
        const filtered = allStops
            .map(stop => ({ stop, score: stopSearchScore(stop.name, currentQuery) }))
            .filter(item => item.score !== Infinity)
            .sort((a, b) => a.score - b.score)
            .map(item => item.stop);

        // Nota: Qui si potrebbero ricalcolare anche i suggestions se necessario
        renderAllResults(filtered, addressResults);

    } catch (e) {
        console.error("Ricerca indirizzi fallita:", e);
    }
}

/** Visualizza tutti i risultati raggruppati per categoria */
function renderAllResults(stops, addresses = [], suggestions = [], showFullList = false) {
    const listEl = document.getElementById('all-stops-list');
    if (!listEl) return;

    let html = '';

    if (suggestions.length > 0) {
        html += '<div class="subsection-title">CRONOLOGIA E PREFERITI</div>';
        html += suggestions.map(item => createStopCardHTML(item, false)).join('');
    }

    if (addresses.length > 0) {
        html += '<div class="subsection-title">INDIRIZZI</div>';
        html += addresses.map(addr => createStopCardHTML(addr, false)).join('');
    }

    if (stops.length > 0) {
        html += '<div class="subsection-title">FERMATE</div>';
        const visibleStops = showFullList ? stops : stops.slice(0, 25);
        html += visibleStops.map(stop => createStopCardHTML(stop, false)).join('');
    }

    if (stops.length === 0 && addresses.length === 0 && suggestions.length === 0) {
        html = '<div class="no-results">Nessun risultato trovato</div>';
    }

    listEl.innerHTML = html;
}

// Inizializzazione
window.addEventListener('DOMContentLoaded', loadStops);

// Export per Jest
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { addToRecent, createStopCardHTML, renderAllResults };
}
