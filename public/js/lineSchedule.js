/*
 * Pagina "Orari Linea": dato un route_short_name mostra le varianti di
 * percorso, e per la variante scelta gli orari del giorno per ogni fermata
 * piu i bus previsti nella prossima ora.
 */
(function (root, factory) {
    const api = factory();
    if (typeof module === 'object' && module.exports) module.exports = api;
    if (root && root.document) api.init(root);
})(typeof window !== 'undefined' ? window : null, function () {
    'use strict';

    function stopKey(stop) {
        return String((stop && (stop.name || stop.id)) || '').trim().toLocaleLowerCase('it');
    }

    /**
     * Restituisce, in ordine di percorso, solo le fermate che distinguono la
     * variante dalle altre con la stessa origine e lo stesso capolinea.
     */
    function getDistinctiveStops(variant, comparableVariants) {
        if (!variant || !Array.isArray(comparableVariants) || comparableVariants.length < 2) return [];

        const counts = new Map();
        comparableVariants.forEach(item => {
            const keys = new Set((item.stops || []).map(stopKey).filter(Boolean));
            keys.forEach(key => counts.set(key, (counts.get(key) || 0) + 1));
        });

        const distinct = [];
        const seen = new Set();
        (variant.stops || []).forEach(stop => {
            const key = stopKey(stop);
            if (!key || seen.has(key) || counts.get(key) === comparableVariants.length) return;
            seen.add(key);
            distinct.push(stop.name);
        });
        return distinct;
    }

    function longestCommonSubsequenceLength(a, b) {
        const row = new Array(b.length + 1).fill(0);
        for (let i = 1; i <= a.length; i++) {
            let diagonal = 0;
            for (let j = 1; j <= b.length; j++) {
                const previous = row[j];
                row[j] = a[i - 1] === b[j - 1]
                    ? diagonal + 1
                    : Math.max(row[j], row[j - 1]);
                diagonal = previous;
            }
        }
        return row[b.length];
    }

    function sameRouteFamily(a, b) {
        const aStops = (a.stops || []).slice(1).map(stopKey).filter(Boolean);
        const bStops = (b.stops || []).slice(1).map(stopKey).filter(Boolean);
        const shorter = Math.min(aStops.length, bStops.length);
        if (!shorter) return false;
        const sameEndpoints =
            stopKey({ name: a.origin }) === stopKey({ name: b.origin }) &&
            stopKey({ name: a.terminus }) === stopKey({ name: b.terminus });
        return sameEndpoints || longestCommonSubsequenceLength(aStops, bStops) / shorter >= 0.85;
    }

    /** Crea componenti connesse di varianti equivalenti o accorciate. */
    function groupVariantsByRoute(variants) {
        const groups = [];
        variants.forEach(variant => {
            const matchingIndexes = [];
            groups.forEach((items, index) => {
                if (items.some(item => sameRouteFamily(item, variant))) matchingIndexes.push(index);
            });

            if (!matchingIndexes.length) {
                groups.push([variant]);
                return;
            }

            const target = groups[matchingIndexes[0]];
            target.push(variant);
            for (let i = matchingIndexes.length - 1; i >= 1; i--) {
                target.push(...groups[matchingIndexes[i]]);
                groups.splice(matchingIndexes[i], 1);
            }
        });
        return groups;
    }

    function init(window) {
(function () {
    'use strict';

    const WEEKDAYS = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
    const WEEKDAYS_IT = ['domenica', 'lunedì', 'martedì', 'mercoledì', 'giovedì', 'venerdì', 'sabato'];

    let state = {
        line: '',
        selectedTrips: [], // trip_id rappresentativi del giro selezionato
    };

    let pendingMinimaps = [];   // minimappe da inizializzare (lazy)
    let minimapObserver = null; // IntersectionObserver condiviso

    function $(id) { return document.getElementById(id); }

    function getDay() {
        const d = $('date-input').value; // YYYY-MM-DD
        if (!d) return WEEKDAYS[new Date().getDay()];
        const date = new Date(d + 'T00:00:00');
        return WEEKDAYS[date.getDay()];
    }

    function getDate() {
        return $('date-input').value || '';
    }

    function getTime() {
        return $('time-input').value || '00:00';
    }

    function updateWeekdayHint() {
        const d = $('date-input').value;
        if (!d) { $('weekday-hint').textContent = ''; return; }
        const date = new Date(d + 'T00:00:00');
        $('weekday-hint').textContent = 'Giorno selezionato: ' + WEEKDAYS_IT[date.getDay()];
    }

    function setStatus(msg, type) {
        const el = $('status');
        if (!msg) { el.classList.add('d-none'); return; }
        el.className = 'alert alert-' + (type || 'info');
        el.textContent = msg;
    }

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"]/g, c => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;'
        }[c]));
    }

    async function fetchJson(url) {
        const res = await fetch(url);
        if (!res.ok) throw new Error('HTTP ' + res.status);
        return res.json();
    }

    /** Carica e mostra le varianti di percorso della linea. */
    async function loadVariants() {
        const line = $('line-input').value.trim();
        if (!line) { setStatus('Inserisci una linea (es. 5E).', 'warning'); return; }

        state.line = line;
        state.selectedTrips = [];
        $('variant-detail').innerHTML = '';
        $('variants').innerHTML = '';
        setStatus('Caricamento varianti...', 'info');

        try {
            const data = await fetchJson('/api/line-variants?line=' + encodeURIComponent(line) +
                '&day=' + encodeURIComponent(getDay()) +
                '&date=' + encodeURIComponent(getDate()));
            if (!data.success) { setStatus(data.error || 'Errore', 'danger'); return; }
            if (!data.variants.length) { setStatus('Nessuna variante trovata.', 'warning'); return; }
            setStatus('', null);
            renderVariants(data.variants);
        } catch (e) {
            setStatus('Errore nel caricamento delle varianti.', 'danger');
        }
    }

    function renderVariants(variants) {
        const wrap = $('variants');
        wrap.innerHTML = '';

        // Reset minimappe in sospeso (e disconnetti l'observer precedente).
        if (minimapObserver) { minimapObserver.disconnect(); minimapObserver = null; }
        pendingMinimaps = [];

        // 1. Raggruppa per capolinea (trip_headsign).
        const groups = {};
        variants.forEach(v => {
            const key = v.headsign || v.terminus || '(senza capolinea)';
            (groups[key] = groups[key] || []).push(v);
        });

        // 2. Ordina i capolinea in ordine alfabetico.
        const headsigns = Object.keys(groups).sort((a, b) => a.localeCompare(b, 'it'));

        headsigns.forEach(headsign => {
            const groupVariants = groups[headsign];
            const routeGroups = groupVariantsByRoute(groupVariants);
            const tripGroups = routeGroups.map((items, index) =>
                (index + 1) + ':' + items.map(v => v.trip_id).join(',')
            ).join('|');
            const groupMapUrl = '/lines-map?tripGroups=' + encodeURIComponent(tripGroups);

            // Intestazione del gruppo (capolinea).
            const groupHeader = document.createElement('div');
            groupHeader.className = 'variant-group-header mt-3 mb-2';
            groupHeader.innerHTML = '<a class="variant-group-map-link fw-bold" href="' + esc(groupMapUrl) +
                '" title="Mostra tutti i percorsi verso ' + esc(headsign) + ' sulla mappa">→ ' + esc(headsign) + '</a>' +
                '<span class="text-muted small ms-2">' + routeGroups.length +
                (routeGroups.length === 1 ? ' giro' : ' giri') + '</span>';
            wrap.appendChild(groupHeader);

            routeGroups.forEach((routeVariants, i) => {
                const v = routeVariants.reduce((longest, item) =>
                    (item.stops || []).length > (longest.stops || []).length ? item : longest
                );
                const tripIds = routeVariants.map(item => item.trip_id);
                const origins = [...new Set([...routeVariants]
                    .sort((a, b) => (b.stops || []).length - (a.stops || []).length)
                    .map(item => item.origin)
                    .filter(Boolean))];
                const tripsCount = routeVariants.reduce((total, item) => total + item.trips_count, 0);
                const comparableRoutes = routeGroups.map(items => items.reduce((longest, item) =>
                    (item.stops || []).length > (longest.stops || []).length ? item : longest
                ));
                const card = document.createElement('div');
                card.className = 'card variant-card mb-2';
                const mapUrl = '/lines-map?tripGroups=' + encodeURIComponent((i + 1) + ':' + tripIds.join(','));

                // "Passa per": solo le fermate che distinguono questo giro dagli altri.
                let diffHtml = '';
                if (comparableRoutes.length > 1) {
                    const distinct = getDistinctiveStops(v, comparableRoutes);
                    diffHtml = '<div class="text-muted small mt-1">Passa per: ' +
                        (distinct.length ? esc(distinct.join(', ')) : '<em>percorso base</em>') + '</div>';
                }

                const clockSvg = '<svg viewBox="0 -960 960 960" width="16" height="16" fill="currentColor" aria-hidden="true">' +
                    '<path d="M520-496v-144q0-17-11.5-28.5T480-680q-17 0-28.5 11.5T440-640v160q0 8 3 15.5t9 13.5l128 128q11 11 28 11t28-11q11-11 11-28t-11-28L520-496Zm-40 376q-75 0-140.5-28.5t-114-77q-48.5-48.5-77-114T120-480q0-75 28.5-140.5t77-114q48.5-48.5 114-77T480-840q75 0 140.5 28.5t114 77q48.5 48.5 77 114T840-480q0 75-28.5 140.5t-77 114q-48.5 48.5-114 77T480-120Z"/>' +
                    '</svg>';

                card.innerHTML =
                    '<div class="card-body py-2">' +
                        '<div class="d-flex align-items-center gap-2 flex-nowrap">' +
                            '<div class="flex-grow-1" style="min-width:0">' +
                                '<div class="fw-semibold text-truncate">' +
                                    esc(origins.join(' / ') || '?') + ' → ' + esc(v.terminus || '?') +
                                    (routeVariants.length > 1 ? ' <span class="badge text-bg-secondary ms-2">merged</span>' : '') +
                                '</div>' +
                                '<div class="text-muted small">' + v.stops_count + ' fermate max · ' + tripsCount + ' corse/giorno</div>' +
                                diffHtml +
                            '</div>' +
                            '<div class="variant-minimap flex-shrink-0" role="button" tabindex="0" title="Apri sulla mappa"></div>' +
                            '<button class="btn btn-sm btn-primary variant-orari-btn text-nowrap flex-shrink-0 d-inline-flex align-items-center gap-1">' +
                                clockSvg +
                            '</button>' +
                        '</div>' +
                    '</div>';

                card.querySelector('.variant-orari-btn').addEventListener('click', () => {
                    state.selectedTrips = tripIds;
                    document.querySelectorAll('.variant-card').forEach(c => c.classList.remove('border-primary'));
                    card.classList.add('border-primary');
                    loadSchedule();
                });

                // Minimappa del percorso (frozen, cliccabile -> mappa grande).
                const coords = (v.stops || [])
                    .map(s => [parseFloat(s.lat), parseFloat(s.lng)])
                    .filter(c => !isNaN(c[0]) && !isNaN(c[1]));
                pendingMinimaps.push({ el: card.querySelector('.variant-minimap'), coords, url: mapUrl, tripId: v.trip_id });

                wrap.appendChild(card);
            });
        });

        initMinimaps();
    }

    /**
     * Inizializza le minimappe in modo lazy (solo quando entrano nel viewport)
     * per non creare decine di mappe Leaflet tutte insieme.
     */
    function initMinimaps() {
        if (typeof L === 'undefined') return; // Leaflet non disponibile
        if (!('IntersectionObserver' in window)) {
            pendingMinimaps.forEach(createMinimap);
            return;
        }
        const byEl = new Map(pendingMinimaps.map(m => [m.el, m]));
        minimapObserver = new IntersectionObserver((entries, obs) => {
            entries.forEach(e => {
                if (!e.isIntersecting) return;
                const spec = byEl.get(e.target);
                if (spec) createMinimap(spec);
                obs.unobserve(e.target);
            });
        }, { rootMargin: '100px' });
        pendingMinimaps.forEach(m => minimapObserver.observe(m.el));
    }

    /** Crea una minimappa frozen centrata sul percorso (shape snappata su strada). */
    async function createMinimap(spec) {
        const { el, coords, url, tripId } = spec;
        if (!el || el.dataset.init === '1') return;
        el.dataset.init = '1';

        // Usa la shape snappata sulle strade; fallback ai segmenti tra fermate.
        let path = coords;
        try {
            const res = await fetch('/api/lines-shapes?tripId=' + encodeURIComponent(tripId));
            if (res.ok) {
                const shapes = await res.json();
                const sh = shapes && shapes[0] && shapes[0].shape;
                if (Array.isArray(sh) && sh.length > 1) {
                    const pts = sh.map(p => [parseFloat(p.lat), parseFloat(p.lng)])
                        .filter(c => !isNaN(c[0]) && !isNaN(c[1]));
                    if (pts.length > 1) path = pts;
                }
            }
        } catch (e) { /* fallback alle fermate */ }

        if (!path.length) { el.classList.add('minimap-empty'); return; }

        const mini = L.map(el, {
            zoomControl: false,
            attributionControl: false,
            dragging: false,
            scrollWheelZoom: false,
            doubleClickZoom: false,
            boxZoom: false,
            keyboard: false,
            touchZoom: false,
            tap: false,
            zoomSnap: 0
        });

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(mini);

        const line = L.polyline(path, { color: '#0d6efd', weight: 3, opacity: 0.9 }).addTo(mini);
        // Marker leggeri ai capolinea.
        L.circleMarker(path[0], { radius: 3, color: '#198754', fillOpacity: 1 }).addTo(mini);
        L.circleMarker(path[path.length - 1], { radius: 3, color: '#dc3545', fillOpacity: 1 }).addTo(mini);

        // Centra sul percorso e blocca lo zoom/pan ai bounds correnti.
        mini.fitBounds(line.getBounds(), { padding: [6, 6] });
        setTimeout(() => mini.invalidateSize(), 0);

        // Clic sulla minimappa -> mappa grande con questo percorso.
        const go = () => { window.location.href = url; };
        mini.on('click', go);
        el.addEventListener('keydown', ev => { if (ev.key === 'Enter' || ev.key === ' ') go(); });
    }

    /** Carica gli orari del giorno per la variante selezionata. */
    async function loadSchedule() {
        if (!state.selectedTrips.length) return;
        const day = getDay();
        const detail = $('variant-detail');
        detail.innerHTML = '<div class="text-muted">Caricamento orari...</div>';

        try {
            const url = '/api/line-schedule?line=' + encodeURIComponent(state.line) +
                '&trips=' + encodeURIComponent(state.selectedTrips.join(',')) +
                '&day=' + encodeURIComponent(day) +
                '&date=' + encodeURIComponent(getDate());
            const data = await fetchJson(url);
            if (!data.success) { detail.innerHTML = '<div class="alert alert-danger">' + esc(data.error) + '</div>'; return; }
            renderSchedule(data);
        } catch (e) {
            detail.innerHTML = '<div class="alert alert-danger">Errore nel caricamento degli orari.</div>';
        }
    }

    /**
     * Disegna la tabella orari: righe = fermate, colonne = corse del giorno.
     * Ogni cella contiene l'orario di passaggio della corsa a quella fermata.
     */
    function renderSchedule(data) {
        const detail = $('variant-detail');
        const stops = data.stops || [];
        const runs = data.runs || [];

        detail.innerHTML =
            '<h5 class="mb-1">Orari linea ' + esc(state.line) + ' → ' + esc(data.headsign || '') + '</h5>' +
            '<div class="text-muted small mb-2">' + stops.length + ' fermate · ' + runs.length + ' corse · clicca una fermata per i bus della prossima ora</div>';

        if (!runs.length) {
            detail.innerHTML += '<div class="alert alert-warning">Nessuna corsa in questo giorno.</div>';
            return;
        }

        // Raggruppa le fermate consecutive che condividono la "zona".
        // La zona è il prefisso di parole comune al gruppo: così
        // "Forte Marghera A" / "Forte Marghera B" → zona "Forte Marghera",
        // mentre "Castellana Bellotto" / "Castellana Cipressina" → "Castellana".
        const words = n => String(n || '').trim().split(/\s+/).filter(Boolean);
        const firstWord = n => (words(n)[0] || '');

        // Lunghezza (in parole) del prefisso comune tra due liste di parole.
        const commonPrefixLen = (a, b) => {
            let i = 0;
            while (i < a.length && i < b.length && a[i].toLowerCase() === b[i].toLowerCase()) i++;
            return i;
        };

        const rowMeta = new Array(stops.length);
        let gi = 0;
        while (gi < stops.length) {
            const zw = firstWord(stops[gi].name);
            let gj = gi + 1;
            while (gj < stops.length && firstWord(stops[gj].name).toLowerCase() === zw.toLowerCase()) gj++;
            const size = gj - gi;

            // Prefisso di parole comune a tutte le fermate del gruppo.
            let prefixWords = words(stops[gi].name);
            for (let k = gi + 1; k < gj; k++) {
                const len = commonPrefixLen(prefixWords, words(stops[k].name));
                prefixWords = prefixWords.slice(0, len);
            }
            // Le fermate il cui nome coincide col prefisso mostreranno comunque
            // il nome completo (fallback più sotto), quindi non serve ridurre il
            // prefisso: così anche gruppi con una fermata "secca" (es. "Noale")
            // restano raggruppati.
            const zoneLabel = prefixWords.join(' ');
            const isZone = size >= 2 && zoneLabel.length > 0;

            for (let k = gi; k < gj; k++) {
                const name = stops[k].name;
                let label = name;
                if (isZone) {
                    label = name.slice(zoneLabel.length).trim() || name;
                }
                rowMeta[k] = {
                    label: label,
                    // La cella zona si emette solo sulla prima riga del gruppo.
                    zoneTh: (k === gi) ? { word: isZone ? zoneLabel : '', span: size, empty: !isZone } : null
                };
            }
            gi = gj;
        }

        // Header: colonna zona + colonna "Fermata" + una colonna per ogni corsa.
        let head = '<tr><th class="zone-col"></th><th class="stop-col">Fermata</th>';
        runs.forEach((r, i) => {
            const mapUrl = '/lines-map?tripId=' + encodeURIComponent(r.trip_id) + '&showStops=1';
            head += '<th class="run-col" title="Apri la corsa sulla mappa">' +
                '<a class="run-map-link" href="' + esc(mapUrl) + '">' + esc(r.start) + '</a></th>';
        });
        head += '</tr>';

        // Corpo: una riga per fermata.
        let body = '';
        stops.forEach((s, ri) => {
            const m = rowMeta[ri];
            body += '<tr data-stop="' + esc(s.id) + '" data-row="' + ri + '">';
            if (m.zoneTh) {
                body += '<th class="zone-col' + (m.zoneTh.empty ? ' zone-empty' : '') + '" rowspan="' + m.zoneTh.span + '">' +
                    (m.zoneTh.empty ? '' : '<span class="zone-label">' + esc(m.zoneTh.word) + '</span>') +
                    '</th>';
            }
            body += '<th class="stop-col">' +
                    '<button type="button" class="stop-name-btn" data-stop="' + esc(s.id) + '" data-name="' + esc(s.name) + '" title="' + esc(s.name) + '">' +
                        esc(m.label) +
                    '</button>' +
                '</th>';
            runs.forEach(r => {
                const t = r.times[ri];
                body += '<td class="run-col ' + (t ? '' : 'empty') + '">' + (t ? esc(t) : '·') + '</td>';
            });
            body += '</tr>';
        });

        detail.innerHTML +=
            '<div class="table-responsive timetable-wrap">' +
                '<table class="table table-sm table-bordered timetable mb-0">' +
                    '<thead>' + head + '</thead>' +
                    '<tbody>' + body + '</tbody>' +
                '</table>' +
            '</div>' +
            '<div id="upcoming-panel" class="mt-2"></div>';

        // Click sul nome fermata -> carica i bus della prossima ora nel pannello.
        const panel = detail.querySelector('#upcoming-panel');
        detail.querySelectorAll('.stop-name-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                detail.querySelectorAll('tr.row-active').forEach(tr => tr.classList.remove('row-active'));
                btn.closest('tr').classList.add('row-active');
                loadUpcoming(btn.dataset.stop, btn.dataset.name, panel);
            });
        });

        // Porta la tabella in vista dopo il click su "Orari".
        detail.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    /** Carica (lazy) i bus previsti nella prossima ora a una fermata. */
    async function loadUpcoming(stopId, stopName, box) {
        const header = '<div class="fw-semibold mb-1">' + esc(stopName) + ' — prossima ora dalle ' + esc(getTime()) + '</div>';
        box.innerHTML = header + '<div class="text-muted small">Caricamento...</div>';

        try {
            const url = '/api/stop-upcoming?stop=' + encodeURIComponent(stopId) +
                '&day=' + encodeURIComponent(getDay()) +
                '&time=' + encodeURIComponent(getTime());
            const data = await fetchJson(url);
            if (!data.success) { box.innerHTML = header + '<div class="text-danger small">' + esc(data.error) + '</div>'; return; }

            if (!data.buses.length) {
                box.innerHTML = header + '<div class="text-muted small">Nessun bus previsto nella prossima ora.</div>';
            } else {
                box.innerHTML = header +
                    data.buses.map(b =>
                        '<div class="upcoming-row d-flex justify-content-between border-bottom py-1">' +
                            '<span><span class="badge bg-success me-2">' + esc(b.route_short_name) + '</span>' + esc(b.trip_headsign || '') + '</span>' +
                            '<span class="text-nowrap">' + esc(b.time) + ' <span class="text-muted">(+' + b.in_min + ' min)</span></span>' +
                        '</div>'
                    ).join('');
            }
        } catch (e) {
            box.innerHTML = header + '<div class="text-danger small">Errore nel caricamento.</div>';
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        updateWeekdayHint();
        $('load-btn').addEventListener('click', loadVariants);
        $('line-input').addEventListener('keydown', e => { if (e.key === 'Enter') loadVariants(); });
        $('date-input').addEventListener('change', () => {
            updateWeekdayHint();
            loadVariants();
        });
        $('time-input').addEventListener('change', () => {
            // Svuota il pannello "prossima ora": va ricaricato con il nuovo orario.
            const panel = document.getElementById('upcoming-panel');
            if (panel) panel.innerHTML = '';
            document.querySelectorAll('tr.row-active').forEach(tr => tr.classList.remove('row-active'));
        });

        // Pre-compila dalla querystring (?line=5E) se presente.
        const params = new URLSearchParams(window.location.search);
        if (params.get('line')) {
            $('line-input').value = params.get('line');
            loadVariants();
        }
    });
})();
    }

    return { init, getDistinctiveStops, groupVariantsByRoute };
});
