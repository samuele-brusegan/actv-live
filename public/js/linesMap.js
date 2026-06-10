/**
 * Logica per la visualizzazione geografica delle linee ACTV su mappa Leaflet.
 * Supporta la visualizzazione di tutte le linee o il filtraggio per singola linea/corsa.
 */

document.addEventListener('DOMContentLoaded', () => {
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
        const hasFilter = urlParams.has('line') || urlParams.has('tripId') ||
            urlParams.has('tripIds') || urlParams.has('tripGroups');
        const showStops = urlParams.get('showStops') === '1';

        const loadingEl = document.getElementById('loading');

        try {
            const response = await fetch(`/api/lines-shapes${window.location.search}`);
            if (!response.ok) throw new Error('Fallimento fetch linee');

            const shapes = await response.json();
            let bounds = null;
            const routeGroups = new Map();

            shapes.forEach((shape, index) => {
                // Preferisci la "shape" snappata sulle strade (come la live-map);
                // se assente (es. vista "tutte le linee") usa i segmenti tra fermate.
                const hasRoadShape = Array.isArray(shape.shape) && shape.shape.length > 1;
                const latlngs = hasRoadShape
                    ? shape.shape.map(p => [p.lat, p.lng])
                    : (shape.path || []).map(p => [p.lat, p.lng]);

                if (latlngs.length === 0) return;
                const isTarget = hasFilter; // Se filtrato, tutto ciò che arriva è considerato target

                const groupNumber = Number(shape.group_number) || (hasFilter ? index + 1 : null);
                const color = groupNumber
                    ? LINE_COLORS[(groupNumber - 1) % LINE_COLORS.length]
                    : getDeterministicColor(shape.route_short_name, true);
                const style = {
                    color: color,
                    weight: isTarget ? 8 : 4,
                    opacity: isTarget ? 1 : 0.7,
                    zIndex: isTarget ? 1000 : 1
                };

                const polyline = L.polyline(latlngs, style).addTo(map);

                if (showStops) {
                    (shape.path || []).forEach((stop, stopIndex) => {
                        const lat = parseFloat(stop.lat);
                        const lng = parseFloat(stop.lng);
                        if (Number.isNaN(lat) || Number.isNaN(lng)) return;
                        L.circleMarker([lat, lng], {
                            radius: stopIndex === 0 || stopIndex === shape.path.length - 1 ? 6 : 4,
                            color,
                            weight: 2,
                            fillColor: '#fff',
                            fillOpacity: 1
                        }).addTo(map).bindPopup(
                            `<strong>${stopIndex + 1}. ${stop.name || ''}</strong>`
                        );
                    });
                }

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

                if (groupNumber) {
                    if (!routeGroups.has(groupNumber)) {
                        routeGroups.set(groupNumber, {
                            color,
                            layers: [],
                            stops: new Set()
                        });
                    }
                    const group = routeGroups.get(groupNumber);
                    group.layers.push(polyline);
                    (shape.path || []).forEach(stop => {
                        if (stop.name) group.stops.add(stop.name);
                    });
                }

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

            if (routeGroups.size > 1) {
                const stopCounts = new Map();
                routeGroups.forEach(group => {
                    group.stops.forEach(name => stopCounts.set(name, (stopCounts.get(name) || 0) + 1));
                });

                const legend = document.createElement('div');
                legend.className = 'route-map-legend';
                [...routeGroups.entries()]
                    .sort((a, b) => a[0] - b[0])
                    .forEach(([groupId, group]) => {
                        const distinctStops = [...group.stops]
                            .filter(name => stopCounts.get(name) < routeGroups.size)
                            .slice(0, 3);
                        const label = distinctStops.length
                            ? 'via ' + distinctStops.join(', ')
                            : 'Percorso ' + group.color;
                        const button = document.createElement('button');
                        button.type = 'button';
                        button.className = 'route-map-toggle active';
                        button.setAttribute('aria-pressed', 'true');
                        button.innerHTML =
                            `<span class="route-map-swatch" style="background:${group.color}"></span>` +
                            `<span>${label}</span>`;
                        button.addEventListener('click', () => {
                            const visible = button.getAttribute('aria-pressed') === 'true';
                            group.layers.forEach(layer => {
                                if (visible) map.removeLayer(layer);
                                else layer.addTo(map);
                            });
                            button.setAttribute('aria-pressed', visible ? 'false' : 'true');
                            button.classList.toggle('active', !visible);
                        });
                        legend.appendChild(button);
                    });
                document.body.appendChild(legend);
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
});
