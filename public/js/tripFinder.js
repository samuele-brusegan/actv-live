(function (root, factory) {
    const api = factory();
    if (typeof module === 'object' && module.exports) module.exports = api;
    if (root && root.document) api.init(root);
})(typeof window !== 'undefined' ? window : null, function () {
    'use strict';

    const state = {
        line: '',
        variants: [],
        trips: [],
        selectedTripId: '',
        currentPage: 1
    };
    const PAGE_SIZE = 10;
    const SESSION_KEY = 'actvLive.tripFinder';
    let sessionStore = null;

    function $(id) { return document.getElementById(id); }

    function getSessionStore() {
        if (sessionStore) return sessionStore;
        try {
            return typeof sessionStorage !== 'undefined' ? sessionStorage : null;
        } catch (error) {
            return null;
        }
    }

    function readSessionState() {
        const storage = getSessionStore();
        if (!storage) return null;
        try {
            const value = JSON.parse(storage.getItem(SESSION_KEY) || 'null');
            return value && typeof value === 'object' ? value : null;
        } catch (error) {
            return null;
        }
    }

    function saveSessionState() {
        const storage = getSessionStore();
        if (!storage) return;
        try {
            storage.setItem(SESSION_KEY, JSON.stringify({
                date: $('date-input')?.value || '',
                line: $('line-input')?.value || state.line || '',
                origin: $('origin-input')?.value || '',
                destination: $('destination-input')?.value || '',
                tripId: state.selectedTripId || '',
                page: state.currentPage
            }));
        } catch (error) {
            // La sessione può essere disabilitata o non disponibile in modalità privata.
        }
    }

    function escapeHtml(value) {
        return String(value == null ? '' : value).replace(/[&<>"']/g, character => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
        }[character]));
    }

    function dateValue() {
        const input = $('date-input');
        if (input && input.value) return input.value;
        const now = new Date();
        return now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0') + '-' + String(now.getDate()).padStart(2, '0');
    }

    function dayValue(date) {
        return ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'][new Date(date + 'T00:00:00').getDay()];
    }

    function scheduleSeconds(value) {
        const match = String(value || '').match(/^(\d+):(\d{2})/);
        if (!match) return null;
        return (Number(match[1]) * 60 * 60) + (Number(match[2]) * 60);
    }

    function isTodaySelected() {
        const now = new Date();
        const today = now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0') + '-' + String(now.getDate()).padStart(2, '0');
        return dateValue() === today;
    }

    function isTripInMotion(trip) {
        if (!isTodaySelected()) return false;
        const departure = scheduleSeconds(trip.departure_time);
        const arrival = scheduleSeconds(trip.arrival_time) ?? departure;
        if (departure === null || arrival === null) return false;
        const end = arrival < departure ? arrival + 86400 : arrival;
        const now = new Date();
        const nowSeconds = now.getHours() * 3600 + now.getMinutes() * 60 + now.getSeconds();
        const tolerance = 30 * 60;
        return [nowSeconds, nowSeconds + 86400].some(current =>
            current >= departure - tolerance && current <= end + tolerance
        );
    }

    function uniqueOrigins(variants) {
        const origins = new Map();
        variants.forEach(variant => {
            const firstStop = Array.isArray(variant.stops) ? variant.stops[0] : null;
            const id = String(firstStop?.id || '').trim();
            if (!id || origins.has(id)) return;
            origins.set(id, { id, name: variant.origin || firstStop.name || id });
        });
        return [...origins.values()].sort((a, b) => String(a.name).localeCompare(String(b.name), 'it'));
    }

    function destinationsForOrigin(variants, originId) {
        const stops = new Map();
        variants.forEach(variant => {
            const routeStops = variant.stops || [];
            const originIndex = routeStops.findIndex(stop => String(stop.id) === String(originId));
            const lastStop = routeStops[routeStops.length - 1];
            if (originIndex < 0 || !lastStop || routeStops.length - 1 <= originIndex) return;
            const id = String(lastStop.id || '').trim();
            if (id && !stops.has(id)) stops.set(id, { id, name: lastStop.name || id });
        });
        return [...stops.values()].sort((a, b) => String(a.name).localeCompare(String(b.name), 'it'));
    }

    function setStatus(message, type) {
        const element = $('status');
        if (!element) return;
        element.textContent = message || '';
        element.className = 'finder-status' + (type ? ' is-' + type : '');
    }

    async function fetchJson(url) {
        const response = await fetch(url, { headers: { Accept: 'application/json' } });
        if (!response.ok) throw new Error('HTTP ' + response.status);
        return response.json();
    }

    function resetSelect(element, label, disabled) {
        element.innerHTML = '<option value="">' + escapeHtml(label) + '</option>';
        element.disabled = disabled;
    }

    function renderLines(lines) {
        const select = $('line-input');
        select.innerHTML = '<option value="">Seleziona una linea</option>' + lines.map(line =>
            '<option value="' + escapeHtml(line.line) + '">' + escapeHtml(line.line) + ' — ' + escapeHtml(line.name || 'senza descrizione') + '</option>'
        ).join('');
        select.disabled = false;
    }

    function renderStops(select, stops, placeholder) {
        select.innerHTML = '<option value="">' + escapeHtml(placeholder) + '</option>' + stops.map(stop =>
            '<option value="' + escapeHtml(stop.id) + '">' + escapeHtml(stop.name) + '</option>'
        ).join('');
        select.disabled = stops.length === 0;
    }

    function selectTrip(tripId) {
        const list = $('trips-list');
        const button = [...list.querySelectorAll('.trip-option')]
            .find(item => String(item.dataset.tripId) === String(tripId));
        if (!button) return false;
        list.querySelectorAll('.trip-option').forEach(item => {
            item.classList.remove('is-selected');
            item.setAttribute('aria-pressed', 'false');
        });
        button.classList.add('is-selected');
        button.setAttribute('aria-pressed', 'true');
        state.selectedTripId = button.dataset.tripId;
        $('open-trip-button').disabled = false;
        saveSessionState();
        return true;
    }

    function renderTrips(trips) {
        const list = $('trips-list');
        const count = $('trip-count');
        state.trips = trips;
        if (!trips.length) state.currentPage = 1;
        state.selectedTripId = '';
        $('open-trip-button').disabled = true;
        count.hidden = !trips.length;
        count.textContent = trips.length + (trips.length === 1 ? ' corsa' : ' corse');
        if (!trips.length) {
            renderPagination();
            list.innerHTML = '<p class="empty-state">Nessuna corsa trovata per questa tratta nella data selezionata.</p>';
            return;
        }
        const totalPages = Math.ceil(trips.length / PAGE_SIZE);
        state.currentPage = Math.max(1, Math.min(state.currentPage, totalPages));
        const start = (state.currentPage - 1) * PAGE_SIZE;
        const pageTrips = trips.slice(start, start + PAGE_SIZE);
        renderPagination();
        list.innerHTML = pageTrips.map(trip => {
            const inMotion = isTripInMotion(trip);
            return '<button type="button" class="trip-option' + (inMotion ? ' is-in-motion' : '') + '" data-trip-id="' + escapeHtml(trip.trip_id) + '" aria-pressed="false">' +
                '<span class="trip-time">' + escapeHtml(trip.departure_time || '--:--') + ' → ' + escapeHtml(trip.arrival_time || '--:--') + '</span>' +
                '<span><span class="trip-headsign">' + escapeHtml(trip.headsign || 'Direzione non indicata') + '</span>' +
                    (inMotion ? '<span class="trip-live-label">In movimento secondo orario</span>' : '') +
                    '<span class="trip-id">trip_id: ' + escapeHtml(trip.trip_id) + '</span></span>' +
            '</button>';
        }).join('');

        list.querySelectorAll('.trip-option').forEach(button => button.addEventListener('click', () => {
            selectTrip(button.dataset.tripId);
        }));
    }

    function renderPagination() {
        const navigation = $('trips-navigation');
        const totalPages = Math.ceil(state.trips.length / PAGE_SIZE);
        if (!navigation) return;
        navigation.hidden = state.trips.length <= PAGE_SIZE;
        if (!state.trips.length) return;
        $('page-label').textContent = 'Pagina ' + state.currentPage + ' di ' + totalPages;
        $('previous-page').disabled = state.currentPage <= 1;
        $('next-page').disabled = state.currentPage >= totalPages;
        $('page-input').max = String(totalPages);
        $('page-input').value = String(state.currentPage);
        saveSessionState();
    }

    function goToPage(page) {
        const totalPages = Math.ceil(state.trips.length / PAGE_SIZE);
        const requested = Number.parseInt(page, 10);
        if (!totalPages || !Number.isFinite(requested)) return;
        state.currentPage = Math.max(1, Math.min(requested, totalPages));
        renderTrips(state.trips);
    }

    function goToTime(time) {
        const target = scheduleSeconds(time);
        if (target === null || !state.trips.length) return;
        const index = state.trips.findIndex(trip => {
            const departure = scheduleSeconds(trip.departure_time);
            return departure !== null && departure >= target;
        });
        const selectedIndex = index >= 0 ? index : state.trips.length - 1;
        state.currentPage = Math.floor(selectedIndex / PAGE_SIZE) + 1;
        renderTrips(state.trips);
    }

    async function loadLines(restoredState = null) {
        $('line-input').disabled = true;
        setStatus('Caricamento linee...', 'loading');
        try {
            const date = dateValue();
            const data = await fetchJson('/api/line-catalog?service=automobilistico&date=' + encodeURIComponent(date));
            if (!data.success) throw new Error(data.error || 'Catalogo non disponibile');
            renderLines(data.lines || []);
            if (restoredState?.line && (data.lines || []).some(line => String(line.line) === String(restoredState.line))) {
                $('line-input').value = restoredState.line;
                await onLineChange(restoredState);
            } else {
                setStatus('Seleziona una linea per continuare.');
            }
        } catch (error) {
            resetSelect($('line-input'), 'Catalogo linee non disponibile', true);
            setStatus('Non è stato possibile caricare le linee.', 'error');
        }
    }

    async function loadVariants(line) {
        const date = dateValue();
        const data = await fetchJson('/api/line-variants?line=' + encodeURIComponent(line) +
            '&service=automobilistico&date=' + encodeURIComponent(date) +
            '&day=' + encodeURIComponent(dayValue(date)));
        if (!data.success) throw new Error(data.error || 'Varianti non disponibili');
        return data.variants || [];
    }

    async function loadTrips(restoredState = null) {
        const origin = $('origin-input').value;
        const destination = $('destination-input').value;
        if (!state.line || !origin || !destination) return;
        setStatus('Ricerca delle corse...', 'loading');
        renderTrips([]);
        saveSessionState();
        try {
            const date = dateValue();
            const data = await fetchJson('/api/line-trips?line=' + encodeURIComponent(state.line) +
                '&origin=' + encodeURIComponent(origin) +
                '&destination=' + encodeURIComponent(destination) +
                '&date=' + encodeURIComponent(date) +
                '&day=' + encodeURIComponent(dayValue(date)));
            if (!data.success) throw new Error(data.error || 'Corse non disponibili');
            state.trips = data.trips || [];
            state.currentPage = 1;
            const savedTripId = restoredState?.tripId || '';
            const savedTripIndex = state.trips.findIndex(trip => String(trip.trip_id) === String(savedTripId));
            if (savedTripIndex >= 0) {
                state.currentPage = Math.floor(savedTripIndex / PAGE_SIZE) + 1;
            } else if (Number.isFinite(Number(restoredState?.page))) {
                state.currentPage = Number(restoredState.page);
            }
            renderTrips(state.trips);
            if (savedTripIndex >= 0) selectTrip(savedTripId);
            setStatus(state.trips.length ? 'Seleziona una corsa per aprire i dettagli.' : 'Nessuna corsa trovata per questa tratta.');
            saveSessionState();
        } catch (error) {
            renderTrips([]);
            setStatus('Non è stato possibile caricare le corse.', 'error');
        }
    }

    async function onLineChange(restoredState = null) {
        state.line = $('line-input').value;
        state.variants = [];
        resetSelect($('origin-input'), state.line ? 'Caricamento partenze...' : 'Prima scegli una linea', true);
        resetSelect($('destination-input'), 'Prima scegli la partenza', true);
        renderTrips([]);
        saveSessionState();
        if (!state.line) {
            setStatus('Seleziona una linea per continuare.');
            return;
        }
        setStatus('Caricamento fermate della linea...', 'loading');
        try {
            state.variants = await loadVariants(state.line);
            const origins = uniqueOrigins(state.variants);
            renderStops($('origin-input'), origins, 'Seleziona la partenza');
            if (restoredState?.origin && origins.some(stop => String(stop.id) === String(restoredState.origin))) {
                $('origin-input').value = restoredState.origin;
                await onOriginChange(restoredState);
                return;
            }
            setStatus('Ora scegli la partenza.');
        } catch (error) {
            resetSelect($('origin-input'), 'Fermate non disponibili', true);
            setStatus('Non è stato possibile caricare le fermate della linea.', 'error');
        }
    }

    function onDateChange() {
        state.line = '';
        state.variants = [];
        state.trips = [];
        state.currentPage = 1;
        resetSelect($('line-input'), 'Caricamento linee...', true);
        resetSelect($('origin-input'), 'Prima scegli una linea', true);
        resetSelect($('destination-input'), 'Prima scegli la partenza', true);
        renderTrips([]);
        saveSessionState();
        loadLines();
    }

    async function onOriginChange(restoredState = null) {
        const origin = $('origin-input').value;
        resetSelect($('destination-input'), origin ? 'Seleziona la destinazione' : 'Prima scegli la partenza', !origin);
        renderTrips([]);
        saveSessionState();
        if (!origin) {
            setStatus('Ora scegli la partenza.');
            return;
        }
        const destinations = destinationsForOrigin(state.variants, origin);
        renderStops($('destination-input'), destinations, 'Seleziona la destinazione');
        if (restoredState?.destination && destinations.some(stop => String(stop.id) === String(restoredState.destination))) {
            $('destination-input').value = restoredState.destination;
            await loadTrips(restoredState);
            return;
        }
        setStatus('Ora scegli la destinazione.');
    }

    function openTripDetails() {
        if (!state.selectedTripId) return;
        const origin = $('origin-input');
        const destination = $('destination-input');
        const selectedTrip = state.trips.find(trip => String(trip.trip_id) === String(state.selectedTripId));
        const params = new URLSearchParams({
            tripId: state.selectedTripId,
            stopId: origin.value,
            contextLine: state.line,
            contextLineId: state.line,
            contextStop: origin.options[origin.selectedIndex]?.textContent || '',
            contextDestination: destination.options[destination.selectedIndex]?.textContent || '',
            contextTime: selectedTrip?.departure_time || ''
        });
        window.location.href = '/trip-details?' + params.toString();
    }

    function init(window) {
        if (!window || !window.document || !$('line-input')) return;
        try {
            sessionStore = window.sessionStorage;
        } catch (error) {
            sessionStore = null;
        }
        const savedState = readSessionState();
        const dateInput = $('date-input');
        if (dateInput && /^\d{4}-\d{2}-\d{2}$/.test(savedState?.date || '')) {
            dateInput.value = savedState.date;
        }
        $('line-input').addEventListener('change', () => onLineChange());
        if (dateInput) dateInput.addEventListener('change', onDateChange);
        $('origin-input').addEventListener('change', () => onOriginChange());
        $('destination-input').addEventListener('change', () => loadTrips());
        $('open-trip-button').addEventListener('click', openTripDetails);
        $('previous-page').addEventListener('click', () => goToPage(state.currentPage - 1));
        $('next-page').addEventListener('click', () => goToPage(state.currentPage + 1));
        $('page-jump-button').addEventListener('click', () => goToPage($('page-input').value));
        $('page-input').addEventListener('change', () => goToPage($('page-input').value));
        $('time-jump-button').addEventListener('click', () => goToTime($('time-input').value));
        loadLines(savedState);
    }

    return {
        init,
        uniqueOrigins,
        destinationsForOrigin,
        dayValue
    };
});
