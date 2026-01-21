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
    try {
        const response = await fetch('/api/stops');
        if (!response.ok) throw new Error('Errore di rete');
        const data = await response.json();
        
        // Mappa per raggruppare fermate con lo stesso nome
        const stopsMap = new Map();

        Object.values(data).forEach(stop => {
            const normalizedName = stop.stop_name.trim().toLowerCase().replace(/\s+/g, ' ');
            const cleanName = stop.stop_name.trim();

            if (stopsMap.has(normalizedName)) {
                stopsMap.get(normalizedName).ids.push(stop.stop_id);
            } else {
                stopsMap.set(normalizedName, {
                    ids: [stop.stop_id],
                    name: cleanName,
                    lat: stop.stop_lat,
                    lng: stop.stop_lon
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
        
        renderFavorites();
        renderRecent();
    } catch (error) {
        console.error('Errore nel caricamento delle fermate:', error);
    }
}

/**
 * Rendering Liste
 */

function renderFavorites() {
    const favorites = JSON.parse(localStorage.getItem('favorite_stops') || '[]');
    const container = document.getElementById('favorites-list');
    if (!container) return;
    
    if (favorites.length === 0) {
        container.innerHTML = '<div class="no-results">Nessuna fermata preferita</div>';
        return;
    }

    container.innerHTML = favorites.map(stop => createStopCardHTML(stop, true)).join('');
}

function renderRecent() {
    const recent = JSON.parse(localStorage.getItem('recent_stops') || '[]').filter(Boolean);
    const container = document.getElementById('recent-list');
    if (!container) return;
    
    if (recent.length === 0) {
        container.innerHTML = '<div class="no-results">Nessuna fermata recente</div>';
        return;
    }

    container.innerHTML = recent.slice(0, 5).map(stop => createStopCardHTML(stop, false)).join('');
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
        element.style.background = '#E8F5E9'; // Verde leggero per selezione
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
        alert('Seleziona una fermata o un indirizzo prima di continuare.');
        return;
    }

    localStorage.setItem(`route_${selectionType}`, JSON.stringify(selectedStop));
    window.location.href = '/route-finder';
}

function cancelSelection() {
    window.location.href = '/route-finder';
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
        if (favoritesSection) favoritesSection.style.display = 'block';
        if (recentSection) recentSection.style.display = 'block';
        if (allStopsSection) allStopsSection.style.display = 'none';
        return;
    }

    if (favoritesSection) favoritesSection.style.display = 'none';
    if (recentSection) recentSection.style.display = 'none';
    if (allStopsSection) allStopsSection.style.display = 'block';

    // 1. Filtro fermate locali
    const filteredLocalStops = allStops.filter(stop => 
        stop.name.toLowerCase().includes(query)
    );

    // 2. Filtro suggerimenti dai preferiti/recenti
    const favorites = JSON.parse(localStorage.getItem('favorite_stops') || '[]');
    const recent = JSON.parse(localStorage.getItem('recent_stops') || '[]').filter(Boolean);
    
    const combined = [...favorites, ...recent];
    const seenKeys = new Set();
    const suggestions = combined.filter(item => {
        if (!item || !item.name.toLowerCase().includes(query)) return false;
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
        const filtered = allStops.filter(s => s.name.toLowerCase().includes(currentQuery));
        
        // Nota: Qui si potrebbero ricalcolare anche i suggestions se necessario
        renderAllResults(filtered, addressResults);

    } catch (e) {
        console.error("Ricerca indirizzi fallita:", e);
    }
}

/** Visualizza tutti i risultati raggruppati per categoria */
function renderAllResults(stops, addresses = [], suggestions = []) {
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
        html += stops.slice(0, 25).map(stop => createStopCardHTML(stop, false)).join('');
    }

    if (stops.length === 0 && addresses.length === 0 && suggestions.length === 0) {
        html = '<div class="no-results">Nessun risultato trovato</div>';
    }

    listEl.innerHTML = html;
}

// Inizializzazione
window.addEventListener('DOMContentLoaded', loadStops);