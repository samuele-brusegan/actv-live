/*
 * Pagina "Orari Linea": dato un route_short_name mostra le varianti di
 * percorso e tutti gli orari disponibili nel feed, con giorni di validità.
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

    /** A GTFS trip may be repeated for several service calendars. Keep one
     * column per identical sequence of times, retaining every validity label. */
    function groupIdenticalRuns(runs) {
        const groups = new Map();
        runs.forEach(run => {
            const key = JSON.stringify(run.times);
            if (!groups.has(key)) groups.set(key, { ...run, calendars: new Set() });
            groups.get(key).calendars.add(run.calendar || 'Validità non disponibile');
        });
        return Array.from(groups.values(), ({ calendars, ...run }) => ({
            ...run, calendar: Array.from(calendars).join(' / ')
        }));
    }

    function init(window) {
(function () {
    'use strict';

    let state = {
        line: '',
        service: 'automobilistico',
        catalog: [],
        selectedTrips: [], // trip_id rappresentativi del giro selezionato
    };

    let catalogRequest = 0;
    let variantsRequest = 0;
    let scheduleRequest = 0;
    let variants = [];

    function $(id) { return document.getElementById(id); }

    function renderFeedMeta(updatedAt) {
        $('feed-meta').textContent = 'Orari programmati del feed · Tutti i giorni di servizio' +
            (updatedAt ? ' · Aggiornamento: ' + new Date(updatedAt).toLocaleDateString('it-IT') : ' · Aggiornamento non disponibile');
    }

    function filterCatalog() {
        const needle = $('line-search').value.trim().toLocaleLowerCase('it');
        const lines = state.catalog.filter(l => !needle || (l.line + ' ' + l.name).toLocaleLowerCase('it').includes(needle));
        $('line-input').innerHTML = '<option value="">' + (lines.length ? 'Seleziona una linea' : 'Nessuna linea trovata') + '</option>' +
            lines.map(l => '<option value="' + esc(l.line) + '">' + esc(l.line) + ' — ' + esc(l.name || 'Linea ' + l.line) + '</option>').join('');
        if (lines.some(l => l.line === state.line)) $('line-input').value = state.line;
    }

    async function loadCatalog() {
        const request = ++catalogRequest;
        ++variantsRequest; ++scheduleRequest;
        state.catalog = []; state.selectedTrips = [];
        $('variants').innerHTML = ''; $('variant-detail').innerHTML = '';
        $('line-input').disabled = true;
        $('line-input').innerHTML = '<option>Caricamento linee…</option>';
        setStatus('Caricamento linee…', 'info');
        try {
            const data = await fetchJson('/api/line-catalog?all=1&service=' + encodeURIComponent(state.service));
            if (request !== catalogRequest) return;
            if (!data.success || data.feed_available === false) throw new Error('catalog');
            state.catalog = data.lines || []; renderFeedMeta(data.updated_at);
            filterCatalog(); $('line-input').disabled = false;
            if ($('line-input').value) loadVariants();
            else {
                setStatus(state.catalog.length ? '' : 'Nessuna linea disponibile per questo servizio.', 'info');
                $('variant-detail').innerHTML = '<div class="schedule-empty"><h2>Da quale linea partiamo?</h2><p>Scegli una linea per vedere tutte le corse. Le indicazioni di validità distinguono i giorni di servizio.</p></div>';
            }
        } catch (e) {
            if (request !== catalogRequest) return;
            $('line-input').innerHTML = '<option value="">Linee non disponibili</option>';
            setStatus('Impossibile caricare le linee. Riprova oppure cambia servizio.', 'warning');
            const retry = document.createElement('button');
            retry.type = 'button'; retry.className = 'btn btn-outline-primary ms-2'; retry.textContent = 'Riprova';
            retry.addEventListener('click', loadCatalog); $('status').appendChild(retry);
            renderFeedMeta(null);
        }
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
        const line = $('line-input').value;
        const request = ++variantsRequest; ++scheduleRequest;
        state.line = line; state.selectedTrips = [];
        $('variants').innerHTML = ''; $('variant-detail').innerHTML = '';
        if (!line) { setStatus('Seleziona una linea.', 'info'); return; }
        setStatus('Caricamento percorsi…', 'info');
        try {
            const data = await fetchJson('/api/line-variants?all=1&line=' + encodeURIComponent(line) + '&service=' + encodeURIComponent(state.service));
            if (request !== variantsRequest) return;
            if (!data.success) throw new Error('variants');
            variants = data.variants || [];
            if (!variants.length) { setStatus('Nessun percorso disponibile per questa linea.', 'warning'); return; }
            setStatus(''); renderVariants();
        } catch (e) {
            if (request === variantsRequest) setStatus('Impossibile caricare i percorsi. Seleziona di nuovo la linea per riprovare.', 'danger');
        }
    }

    function renderVariants() {
        const wrap = $('variants');
        const headsigns = [...new Set(variants.map(v => v.headsign || v.terminus || 'Senza destinazione'))];
        wrap.innerHTML = '<h2 class="schedule-section-title">Direzione</h2><div class="direction-list" role="group" aria-label="Direzione"></div><div id="route-options"></div>';
        const chooseDirection = headsign => {
            wrap.querySelectorAll('.direction-btn').forEach(btn => {
                btn.classList.toggle('active', btn.textContent === headsign);
                btn.setAttribute('aria-pressed', String(btn.textContent === headsign));
            });
            const matching = variants.filter(v => (v.headsign || v.terminus || 'Senza destinazione') === headsign);
            const routeGroups = groupVariantsByRoute(matching);
            const routes = $('route-options');
            routes.innerHTML = '<h3 class="schedule-section-title">' + (routeGroups.length > 1 ? 'Scegli il percorso' : 'Percorso') + '</h3>';
            routeGroups.forEach((items, index) => {
                const longest = items.reduce((a, b) => a.stops.length >= b.stops.length ? a : b);
                const button = document.createElement('button');
                button.type = 'button'; button.className = 'route-choice';
                const origins = [...new Set(items.map(v => v.origin).filter(Boolean))];
                button.innerHTML = '<span class="route-choice-name">' + esc(origins.join(' / ') || 'Partenza') + ' → ' + esc(longest.terminus || headsign) + '</span><span class="route-choice-meta">' + longest.stops.length + ' fermate · ' + items.reduce((sum,v) => sum + v.trips_count,0) + (items.reduce((sum,v) => sum + v.trips_count,0) === 1 ? ' corsa nel feed</span>' : ' corse nel feed</span>');
                if (routeGroups.length > 1) {
                    const distinct = getDistinctiveStops(longest, routeGroups.map(g => g[0]));
                    if (distinct.length) button.innerHTML += '<span class="route-choice-via">Via ' + esc(distinct.join(', ')) + '</span>';
                }
                button.addEventListener('click', () => {
                    routes.querySelectorAll('.route-choice').forEach(b => { b.classList.remove('active'); b.setAttribute('aria-pressed', 'false'); });
                    button.classList.add('active'); button.setAttribute('aria-pressed', 'true');
                    state.selectedTrips = items.map(v => v.trip_id); loadSchedule();
                });
                routes.appendChild(button);
                if (index === 0) button.click();
            });
        };
        headsigns.forEach(headsign => {
            const button = document.createElement('button');
            button.type = 'button'; button.className = 'direction-btn'; button.textContent = headsign;
            button.addEventListener('click', () => chooseDirection(headsign));
            wrap.querySelector('.direction-list').appendChild(button);
        });
        chooseDirection(headsigns[0]);
    }

    /** Carica tutti gli orari per la variante selezionata. */
    async function loadSchedule() {
        if (!state.selectedTrips.length) return;
        const request = ++scheduleRequest;
        const detail = $('variant-detail');
        detail.innerHTML = '<div class="text-muted">Caricamento orari...</div>';

        try {
            const url = '/api/line-schedule?all=1&line=' + encodeURIComponent(state.line) +
                '&service=' + encodeURIComponent(state.service) +
                '&trips=' + encodeURIComponent(state.selectedTrips.join(','));
            const data = await fetchJson(url);
            if (request !== scheduleRequest) return;
            if (!data.success) { detail.innerHTML = '<div class="alert alert-danger">' + esc(data.error) + '</div>'; return; }
            renderSchedule(data);
        } catch (e) {
            if (request === scheduleRequest) detail.innerHTML = '<div class="alert alert-danger">Errore nel caricamento degli orari. Seleziona di nuovo il percorso per riprovare.</div>';
        }
    }

    /**
     * Disegna la tabella orari: righe = fermate, colonne = tutte le corse del feed.
     * Ogni cella contiene l'orario di passaggio della corsa a quella fermata.
     */
    function renderSchedule(data) {
        const detail = $('variant-detail');
        const stops = data.stops || [];
        const runs = groupIdenticalRuns(data.runs || []);

        const calendars = [...new Set(runs.map(r => r.calendar || 'Validità non disponibile'))];
        const mapUrl = '/lines-map?tripGroups=' + encodeURIComponent('1:' + state.selectedTrips.join(','));
        detail.innerHTML = '<div class="schedule-result-heading"><div><h2>Linea ' + esc(state.line) + ' <span>→ ' + esc(data.headsign || '') + '</span></h2><p>' + stops.length + ' fermate · ' + runs.length + (runs.length === 1 ? ' partenza' : ' partenze') + ' · Orario completo</p></div><a class="schedule-map-link" href="' + esc(mapUrl) + '">Vedi percorso sulla mappa ↗</a></div>' +
            '<details class="schedule-validity"><summary>Giorni e validità delle corse</summary><p>Le corse sono mostrate senza filtri. Il numero sotto ogni partenza rimanda alla sua validità.</p>' +
            calendars.map((label,i) => '<div><strong>' + (i+1) + '</strong> ' + esc(label) + '</div>').join('') + '</details>';
        if (!runs.length) {
            detail.innerHTML += '<div class="alert alert-warning">Nessuna corsa disponibile per questo percorso.</div>';
            return;
        }
        detail.innerHTML += '<label class="schedule-stop-label" for="stop-picker">Consulta una fermata</label><select id="stop-picker" class="form-select"><option value="">Tutte le fermate</option>' + stops.map((s,i) => '<option value="' + i + '">' + esc(s.name) + '</option>').join('') + '</select><div id="stop-panel" class="stop-panel" aria-live="polite"></div><p class="schedule-table-hint">Scorri gli orari in orizzontale. Seleziona una partenza per aprire la corsa sulla mappa.</p>';

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
                '<a class="run-map-link" href="' + esc(mapUrl) + '">' + esc(r.start) + '<small class="run-calendar" title="' + esc(r.calendar || 'Validità non disponibile') + '">' + (calendars.indexOf(r.calendar || 'Validità non disponibile') + 1) + '</small></a></th>';
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
                body += '<td class="run-col ' + (t ? 'served' : 'not-served empty') + (ri === 0 && t ? ' first-stop' : '') + (ri === stops.length - 1 && t ? ' last-stop' : '') + '">' + (t ? esc(t) : '·') + '</td>';
            });
            body += '</tr>';
        });

        detail.innerHTML +=
            '<div class="table-responsive timetable-wrap" tabindex="0" role="region" aria-label="Tabella completa degli orari">' +
                '<table class="table table-sm table-bordered timetable mb-0"><caption class="visually-hidden">Orario completo. Righe: fermate; colonne: corse. I numeri sotto le partenze indicano i giorni di validità.</caption>' +
                    '<thead>' + head + '</thead>' +
                    '<tbody>' + body + '</tbody>' +
                '</table>' +
            '</div>';

        function selectStop(index) {
            detail.querySelectorAll('tr.row-active').forEach(tr => tr.classList.remove('row-active'));
            $('stop-picker').value = String(index);
            if (index === '') { $('stop-panel').innerHTML = ''; return; }
            const stop = stops[Number(index)];
            detail.querySelector('tr[data-row="' + index + '"]').classList.add('row-active');
            $('stop-panel').innerHTML = '<h3>' + esc(stop.name) + '</h3><p>Tutti i passaggi della linea ' + esc(state.line) + ' in questa direzione</p><div class="stop-times">' +
                runs.filter(r => r.times[Number(index)]).map(r => '<a href="/lines-map?tripId=' + encodeURIComponent(r.trip_id) + '&amp;showStops=1" title="' + esc(r.calendar || '') + '">' + esc(r.times[Number(index)]) + '<small>' + (calendars.indexOf(r.calendar || 'Validità non disponibile')+1) + '</small></a>').join('') + '</div>';
        }
        $('stop-picker').addEventListener('change', e => selectStop(e.target.value));
        detail.querySelectorAll('.stop-name-btn').forEach((btn,index) => btn.addEventListener('click', () => {
            selectStop(index); $('stop-panel').scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth', block: 'nearest' });
        }));
    }

    document.addEventListener('DOMContentLoaded', () => {
        state.service = localStorage.getItem('actv-line-schedule-service') === 'navigation' ? 'navigation' : 'automobilistico';
        document.querySelectorAll('.service-switch-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.service === state.service);
            btn.setAttribute('aria-pressed', String(btn.dataset.service === state.service));
            btn.addEventListener('click', () => {
                if (state.service === btn.dataset.service) return;
                state.service = btn.dataset.service;
                localStorage.setItem('actv-line-schedule-service', state.service);
                document.querySelectorAll('.service-switch-btn').forEach(b => { b.classList.toggle('active', b.dataset.service === state.service); b.setAttribute('aria-pressed', String(b.dataset.service === state.service)); });
                state.line = ''; $('line-search').value = ''; loadCatalog();
            });
        });
        $('line-input').addEventListener('change', loadVariants);
        $('line-search').addEventListener('input', filterCatalog);

        // Pre-compila dalla querystring (?line=5E) se presente.
        const params = new URLSearchParams(window.location.search);
        if (params.get('line')) {
            state.line = params.get('line'); $('line-search').value = params.get('line');
        }
        loadCatalog();
    });
})();
    }

    return { init, getDistinctiveStops, groupVariantsByRoute, groupIdenticalRuns };
});
