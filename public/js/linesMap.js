function linesMapValues(value) {
    return [...new Set(String(value || '').split(/[,;\n]+/).map(value => value.trim()).filter(Boolean))];
}

function linesMapTripIds(params) {
    const groups = (params.get('tripGroups') || '').split('|').flatMap(group => linesMapValues(group.replace(/^\d+:/, '')));
    return [...new Set([...linesMapValues(params.get('tripId') || params.get('trip_id')), ...linesMapValues(params.get('tripIds')), ...groups])];
}

function linesMapRequests(params, service) {
    const trips = linesMapTripIds(params);
    const lines = linesMapValues(params.get('line'));
    const services = service === 'all' ? ['automobilistico', 'navigation'] : [service];
    return services.flatMap(serviceName => (trips.length || !lines.length ? [null] : lines).map(line => {
        const request = new URLSearchParams(params);
        request.set('service', serviceName);
        ['line', 'tripId', 'trip_id', 'tripIds', 'cache'].forEach(key => request.delete(key));
        if (trips.length) request.set('tripIds', trips.join(','));
        else if (line) request.set('line', line);
        if (!trips.length && !line && params.get('showStops') !== '1') request.set('cache', '1');
        return request;
    }));
}

/**
 * Logica per la visualizzazione geografica delle linee ACTV su mappa Leaflet.
 * Supporta la visualizzazione di tutte le linee o il filtraggio per singola linea/corsa.
 */

document.addEventListener('DOMContentLoaded', () => {
    // Inizializzazione della mappa centrata su Venezia
    const map = L.map('map', {
        attributionControl: true,
        zoomControl: false
    }).setView([45.4384, 12.3359], 12);

    // Layer mappa (OpenStreetMap)
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);

    // Palette desaturata: leggibile sia sulle tile chiare sia: leggibile sia sulle tile chiare sia
    // su quelle scurite dal tema scuro (invert/hue-rotate in style.css).
    const LINE_COLORS = [
        '#D95D50', '#5B8DC9', '#5FA871', '#D99A45',
        '#9B72CF', '#4FA8A4', '#C4659B', '#83A05C',
        '#D9A63C', '#6878B8', '#4E9E97', '#D98862',
        '#8577B8', '#A8B45C'
    ];

    const currentParams = () => new URLSearchParams(window.location.search);
    const initialService = currentParams().get('service') || localStorage.getItem('actv-lines-map-service');
    let service = ['navigation', 'automobilistico'].includes(initialService) ? initialService : 'all';
    const serviceButtons = document.querySelectorAll('.service-switch-btn');
    const filterButton = document.getElementById('btn-lines-filters');
    const filterPanel = document.getElementById('lines-filters-panel');
    const filterMode = document.getElementById('lines-filter-mode');
    const filterQuery = document.getElementById('lines-filter-query');
    const showStopsInput = document.getElementById('lines-show-stops');
    const mapStatus = document.getElementById('lines-map-status');
    const layers = L.layerGroup().addTo(map);
    let drawLanesListener = null;
    let loadController = null;

    function closeFilters(restoreFocus = false) {
        filterPanel.hidden = true;
        filterButton.setAttribute('aria-expanded', 'false');
        if (restoreFocus) filterButton.focus();
    }
    function updateQueryLabel() {
        const trips = filterMode.value === 'trips';
        document.getElementById('lines-query-label').textContent = trips ? 'ID corse' : 'Linee';
        filterQuery.placeholder = trips ? 'ID corsa, ID corsa…' : '5E, 21';
        document.getElementById('lines-query-help').textContent = trips
            ? 'Separa più ID corsa con una virgola. Lascia vuoto per mostrare tutte le linee.'
            : 'Separa più linee con una virgola. Lascia vuoto per mostrarle tutte.';
    }
    function populateFilters() {
        const params = currentParams();
        const trips = linesMapTripIds(params);
        filterMode.value = trips.length ? 'trips' : 'line';
        filterQuery.value = trips.length ? trips.join(', ') : (params.get('line') || '');
        showStopsInput.checked = params.get('showStops') === '1';
        updateQueryLabel();
    }
    function updateToolbar() {
        serviceButtons.forEach(button => {
            const enabled = service === 'all' || service === button.dataset.service;
            button.classList.toggle('active', enabled);
            button.setAttribute('aria-pressed', String(enabled));
        });
        const params = currentParams();
        const trips = linesMapTripIds(params);
        const lines = linesMapValues(params.get('line'));
        const stops = params.get('showStops') === '1';
        const description = trips.length ? `${trips.length} ${trips.length === 1 ? 'corsa' : 'corse'}`
            : lines.length ? `${lines.length === 1 ? 'Linea' : 'Linee'} ${lines.join(', ')}` : 'Tutte le linee';
        const summary = document.getElementById('lines-filter-summary');
        summary.textContent = description + (stops ? ' · Fermate' : '');
        summary.title = summary.textContent;
        const count = Number(Boolean(trips.length || lines.length)) + Number(stops);
        const badge = document.getElementById('lines-filter-count');
        badge.hidden = !count;
        badge.textContent = count;
    }
    function applyParams(params) {
        params.set('service', service);
        // Cache mode belongs to individual API requests, never to a persisted filter.
        params.delete('cache');
        window.history.replaceState({}, '', window.location.pathname + '?' + params.toString());
        updateToolbar();
        closeFilters();
        loadLinesOnMap();
    }
    serviceButtons.forEach(button => button.addEventListener('click', () => {
        service = service === 'all'
            ? (button.dataset.service === 'navigation' ? 'automobilistico' : 'navigation')
            : service === button.dataset.service ? service : 'all';
        localStorage.setItem('actv-lines-map-service', service);
        applyParams(currentParams());
    }));
    filterButton.addEventListener('click', () => {
        const open = filterPanel.hidden;
        if (open) populateFilters();
        filterPanel.hidden = !open;
        filterButton.setAttribute('aria-expanded', String(open));
        if (open) filterQuery.focus();
    });
    document.getElementById('btn-close-lines-filters').addEventListener('click', () => closeFilters(true));
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && !filterPanel.hidden) closeFilters(true);
    });
    document.addEventListener('click', event => {
        if (!event.target.closest('#lines-toolbar')) closeFilters();
    });
    filterMode.addEventListener('change', () => { filterQuery.value = ''; updateQueryLabel(); filterQuery.focus(); });
    document.getElementById('lines-filter-form').addEventListener('submit', event => {
        event.preventDefault();
        const params = currentParams();
        const originalTrips = linesMapTripIds(params).join(',');
        const values = linesMapValues(filterQuery.value);
        ['line', 'tripId', 'trip_id', 'tripIds'].forEach(key => params.delete(key));
        if (filterMode.value === 'trips' && values.length) {
            params.set('tripIds', values.join(','));
            // Preserve comparisons opened from timetables when their trips are unchanged.
            if (values.join(',') !== originalTrips) params.delete('tripGroups');
        } else {
            params.delete('tripGroups');
            if (values.length) params.set('line', values.join(','));
        }
        if (showStopsInput.checked) params.set('showStops', '1');
        else params.delete('showStops');
        applyParams(params);
        filterButton.focus();
    });
    document.getElementById('btn-reset-lines-filters').addEventListener('click', () => {
        const params = currentParams();
        ['line', 'tripId', 'trip_id', 'tripIds', 'tripGroups', 'showStops'].forEach(key => params.delete(key));
        applyParams(params);
        filterButton.focus();
    });
    updateToolbar();

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

    function normalizeColor(value) {
        const color = String(value || '').trim();
        if (/^#[0-9a-f]{6}$/i.test(color)) return color;
        if (/^[0-9a-f]{6}$/i.test(color)) return `#${color}`;
        return '';
    }

    /** Carica i dati delle linee dall'API e li disegna sulla mappa */
    async function loadLinesOnMap() {
        const urlParams = new URLSearchParams(window.location.search);
        const hasFilter = linesMapValues(urlParams.get('line')).length > 0 || linesMapTripIds(urlParams).length > 0;
        const showStops = urlParams.get('showStops') === '1';

        const loadingEl = document.getElementById('loading');
        if (loadController) loadController.abort();
        const controller = new AbortController();
        loadController = controller;
        loadingEl.style.display = 'flex';
        loadingEl.replaceChildren();
        const spinner = document.createElement('div');
        spinner.className = 'spinner';
        const label = document.createElement('div');
        label.textContent = 'Caricamento linee…';
        loadingEl.append(spinner, label);
        mapStatus.textContent = 'Caricamento percorsi…';
        document.getElementById('lines-map-stage').setAttribute('aria-busy', 'true');

        try {
            const requestParams = linesMapRequests(urlParams, service);
            const requests = requestParams.map(async params => {
                const response = await fetch(`/api/lines-shapes?${params.toString()}`, { cache: 'no-store', signal: controller.signal });
                const responseText = await response.text();
                if (!response.ok) {
                    let serverMessage = '';
                    try { serverMessage = JSON.parse(responseText).error || ''; } catch (parseError) { /* risposta non JSON */ }
                    throw new Error(`HTTP ${response.status}${serverMessage ? `: ${serverMessage}` : ''}`);
                }
                if (!responseText.trim()) throw new Error('Il server ha restituito una risposta vuota');

                let shapes;
                try {
                    shapes = JSON.parse(responseText);
                } catch (parseError) {
                    throw new Error('Il server ha restituito dati non validi');
                }
                if (!Array.isArray(shapes)) throw new Error(shapes?.error || 'Formato dati linee non valido');
                return shapes;
            });
            const shapeSets = await Promise.all(requests);
            if (controller.signal.aborted) return;
            const shapes = shapeSets.flat();
            layers.clearLayers();
            if (drawLanesListener) map.off('zoomend', drawLanesListener);
            document.querySelector('.route-map-legend')?.remove();
            let bounds = null;
            const routeGroups = new Map();
            const drawnRoutes = [];
            const drawnStops = [];
            const referenceZoom = 18;
            let laneLayouts = [];
            let visibleRoutes = [];

            function drawRouteLanes() {
                // Keep the separation constant in screen pixels at every zoom level.
                const scale = map.getZoomScale(map.getZoom(), referenceZoom);
                const gap = (hasFilter ? 10 : 6) / scale;
                const maxOffset = (hasFilter ? 24 : Math.min(24, 8 + Math.max(0, map.getZoom() - 12) * 4)) / scale;
                visibleRoutes.forEach((route, index) => {
                    const points = ACTVRouteLanes.offsetPath(laneLayouts[index], gap, { maxOffset });
                    const latlngs = points.map(point => map.unproject(L.point(point.x, point.y), referenceZoom));
                    route.casing?.setLatLngs(latlngs);
                    route.layer.setLatLngs(latlngs);
                });
            }
            function updateRouteLanes() {
                visibleRoutes = drawnRoutes.filter(route => map.hasLayer(route.layer));
                laneLayouts = ACTVRouteLanes.layout(visibleRoutes);
                drawRouteLanes();
            }
            drawLanesListener = drawRouteLanes;
            map.on('zoomend', drawLanesListener);

            shapes.forEach((shape, index) => {
                // Preferisci la "shape" snappata sulle strade (come la live-map);
                // se assente (es. vista "tutte le linee") usa i segmenti tra fermate.
                const hasRoadShape = Array.isArray(shape.shape) && shape.shape.length > 1;
                const latlngs = hasRoadShape
                    ? shape.shape.map(p => [p.lat, p.lng])
                    : (shape.path || []).map(p => [p.lat, p.lng]);
                const validPoints = latlngs.map(([lat, lng]) => [Number(lat), Number(lng)])
                    .filter(([lat, lng]) => Number.isFinite(lat) && Number.isFinite(lng) && Math.abs(lat) <= 90 && Math.abs(lng) <= 180);

                if (validPoints.length < 2) return;
                const isTarget = hasFilter; // Se filtrato, tutto ciò che arriva è considerato target

                const groupNumber = Number(shape.group_number) || (hasFilter ? index + 1 : null);
                // Per i bus route_color descrive la categoria (urbano,
                // extraurbano...), non identifica la singola linea. La mappa
                // usa quindi il colore deterministico della linea; per la
                // navigazione conserva invece il colore pubblicato nel feed.
                const isBus = shape.service === 'automobilistico' || shape.mode === 'bus';
                const color = isBus
                    ? getDeterministicColor(shape.route_short_name || shape.route_id, true)
                    : (normalizeColor(shape.route_color) || (groupNumber
                    ? LINE_COLORS[(groupNumber - 1) % LINE_COLORS.length]
                    : getDeterministicColor(shape.route_short_name, true)));
                const style = {
                    color: color,
                    weight: isTarget ? 8 : 4,
                    opacity: isTarget ? 1 : 0.7,
                    zIndex: isTarget ? 1000 : 1
                };

                // Casing: bordo di contrasto sotto la linea, scuro in tema
                // chiaro e chiaro in tema scuro, per staccarla dalle tile.
                const isDark = document.documentElement.dataset.theme === 'dark';
                const casing = L.polyline(validPoints, {
                    color: isDark ? '#FFFFFF' : '#10231F',
                    weight: style.weight + 3,
                    opacity: isDark ? 0.55 : 0.35,
                    interactive: false
                }).addTo(layers);
                const polyline = L.polyline(validPoints, style).addTo(layers);
                const routeKey = shape.group_number
                    ? `group:${shape.group_number}`
                    : `${shape.service || shape.mode || service}:${shape.route_short_name || shape.route_id || index}`;
                drawnRoutes.push({ key: routeKey, layer: polyline, casing, points: validPoints.map(point => map.project(point, referenceZoom)) });

                const stopLayers = [];
                if (showStops) {
                    (shape.path || []).forEach((stop, stopIndex) => {
                        const lat = parseFloat(stop.lat);
                        const lng = parseFloat(stop.lng);
                        if (Number.isNaN(lat) || Number.isNaN(lng)) return;
                        const stopLayer = L.circleMarker([lat, lng], {
                            radius: stopIndex === 0 || stopIndex === shape.path.length - 1 ? 6 : 4,
                            color,
                            weight: 2,
                            fillColor: '#fff',
                            fillOpacity: 1
                        }).addTo(layers).bindPopup(
                            `<strong>${stopIndex + 1}. ${stop.name || ''}</strong>`
                        );
                        stopLayers.push(stopLayer);
                        drawnStops.push(stopLayer);
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
                            labels: new Set(),
                            stops: new Set()
                        });
                    }
                    const group = routeGroups.get(groupNumber);
                    group.layers.push(casing, polyline, ...stopLayers);
                    group.labels.add(`${isBus ? 'Bus' : 'Vaporetto'} ${shape.route_short_name || shape.route_id || ''}`.trim());
                    (shape.path || []).forEach(stop => {
                        if (stop.name) group.stops.add(stop.name);
                    });
                }

                // Effetti Hover
                polyline.on('mouseover', (e) => {
                    e.target.setStyle({ weight: style.weight + 1, opacity: 1 });
                    e.target.bringToFront();
                    drawnStops.forEach(stop => stop.bringToFront());
                });

                polyline.on('mouseout', (e) => {
                    e.target.setStyle(style);
                });
            });

            updateRouteLanes();
            drawnStops.forEach(stop => stop.bringToFront());

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
                        const label = [...group.labels].join(' / ') + (distinctStops.length ? ' · via ' + distinctStops.join(', ') : '');
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
                            updateRouteLanes();
                            button.setAttribute('aria-pressed', visible ? 'false' : 'true');
                            button.classList.toggle('active', !visible);
                        });
                        legend.appendChild(button);
                    });
                document.getElementById('lines-map-stage').appendChild(legend);
            }

            loadingEl.style.display = 'none';
            mapStatus.textContent = drawnRoutes.length
                ? `${drawnRoutes.length} ${drawnRoutes.length === 1 ? 'percorso sulla mappa' : 'percorsi sulla mappa'}`
                : 'Nessun percorso trovato. Modifica i filtri o il servizio selezionato.';

        } catch (error) {
            if (controller.signal.aborted) return;
            console.error('Errore caricamento linee:', error);
            layers.clearLayers();
            if (drawLanesListener) map.off('zoomend', drawLanesListener);
            document.querySelector('.route-map-legend')?.remove();
            loadingEl.replaceChildren();
            const message = document.createElement('p');
            message.textContent = 'Impossibile caricare i percorsi. Riprova o modifica i filtri.';
            const retry = document.createElement('button');
            retry.type = 'button';
            retry.className = 'lines-control';
            retry.textContent = 'Riprova';
            retry.addEventListener('click', () => loadLinesOnMap());
            loadingEl.append(message, retry);
            mapStatus.textContent = 'Caricamento non riuscito';
        } finally {
            if (!controller.signal.aborted) document.getElementById('lines-map-stage').setAttribute('aria-busy', 'false');
        }
    }

    // Avvio caricamento
    loadLinesOnMap();
});

if (typeof module !== 'undefined' && module.exports) module.exports = { linesMapValues, linesMapTripIds, linesMapRequests };
