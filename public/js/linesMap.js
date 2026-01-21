/**
 * Logica per la visualizzazione geografica delle linee ACTV su mappa Leaflet.
 * Supporta la visualizzazione di tutte le linee o il filtraggio per singola linea/corsa.
 */

// Inizializzazione della mappa centrata su Venezia
const map = L.map('map', { 
    attributionControl: false, 
    zoomControl: false 
}).setView([45.4384, 12.3359], 12);

// Layer mappa (OpenStreetMap)
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors'
}).addTo(map);

// Palette di colori vibranti per le linee
const LINE_COLORS = [
    '#FF0000', '#0000FF', '#008000', '#FFA500', 
    '#800080', '#00FFFF', '#FF00FF', '#00FF00', 
    '#FF1493', '#008080', '#FFD700', '#4B0082', 
    '#DC143C', '#1E90FF'
];

/** 
 * Genera un colore deterministico per una linea basato sul suo nome 
 * @param {string} name - Nome della linea
 * @param {boolean} isColored - Se applicare il colore o usare il grigio
 */
function getDeterministicColor(name, isColored) {
    if (!isColored) return '#AAA';
    
    let hash = 0;
    for (let i = 0; i < name.length; i++) {
        hash = name.charCodeAt(i) + ((hash << 5) - hash);
    }
    return LINE_COLORS[Math.abs(hash) % LINE_COLORS.length];
}

/** Carica i dati delle linee dall'API e li disegna sulla mappa */
async function loadLinesOnMap() {
    const urlParams = new URLSearchParams(window.location.search);
    const hasFilter = urlParams.has('line') || urlParams.has('tripId');
    
    const loadingEl = document.getElementById('loading');

    try {
        const response = await fetch(`/api/lines-shapes${window.location.search}`);
        if (!response.ok) throw new Error('Fallimento fetch linee');
        
        const shapes = await response.json();
        let bounds = null;
        
        shapes.forEach(shape => {
            if (!shape.path || shape.path.length === 0) return;

            const latlngs = shape.path.map(p => [p.lat, p.lng]);
            const isTarget = hasFilter; // Se filtrato, tutto ciò che arriva è considerato target
            
            const color = getDeterministicColor(shape.route_short_name, true);
            const style = {
                color: color,
                weight: isTarget ? 8 : 4,
                opacity: isTarget ? 1 : 0.7,
                zIndex: isTarget ? 1000 : 1
            };

            const polyline = L.polyline(latlngs, style).addTo(map);

            if (isTarget) {
                if (!bounds) bounds = polyline.getBounds();
                else bounds.extend(polyline.getBounds());
                polyline.bringToFront();
            }

            // Popup informativo
            polyline.bindPopup(`
                <div class="line-popup">
                    <div class="line-popup-title">Linea ${shape.route_short_name}</div>
                    <div class="line-popup-desc">${shape.route_long_name}</div>
                </div>
            `);

            // Effetti Hover
            polyline.on('mouseover', (e) => {
                e.target.setStyle({ weight: 9, opacity: 1 });
                e.target.bringToFront();
            });

            polyline.on('mouseout', (e) => {
                e.target.setStyle(style);
            });
        });

        // Zooma sull'area interessata
        if (bounds) {
            map.fitBounds(bounds, { padding: [50, 50] });
        }
        
        if (loadingEl) loadingEl.style.display = 'none';

    } catch (error) {
        console.error('Errore caricamento linee:', error);
        if (loadingEl) {
            loadingEl.innerHTML = '<div class="text-danger">Errore durante il caricamento della mappa.</div>';
        }
    }
}

// Avvio caricamento
loadLinesOnMap();