(function () {
    'use strict';

    const app = document.getElementById('gtfs-rt-inspector');
    if (!app) return;

    const service = document.getElementById('gtfs-rt-service');
    const kind = document.getElementById('gtfs-rt-kind');
    const refresh = document.getElementById('gtfs-rt-refresh');
    const auto = document.getElementById('gtfs-rt-auto');
    const status = document.getElementById('gtfs-rt-status');
    const stats = document.getElementById('gtfs-rt-stats');
    const raw = document.getElementById('gtfs-rt-raw');
    const hex = document.getElementById('gtfs-rt-hex');
    const decoded = document.getElementById('gtfs-rt-decoded');
    const copy = document.getElementById('gtfs-rt-copy');
    let timer = null;
    let loading = false;

    const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, char => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    }[char]));

    const formatBytes = value => {
        const bytes = Number(value);
        if (!Number.isFinite(bytes)) return '--';
        if (bytes < 1024) return `${bytes} B`;
        if (bytes < 1048576) return `${(bytes / 1024).toFixed(1)} KB`;
        return `${(bytes / 1048576).toFixed(2)} MB`;
    };

    function setStatus(message, error = false) {
        status.textContent = message;
        status.className = `inspector-status${error ? ' error' : ''}`;
    }

    function render(data) {
        const records = Array.isArray(data.decoded) ? data.decoded.length : 0;
        stats.innerHTML = [
            ['Servizio', data.service],
            ['Feed', data.kind === 'vehicles' ? 'Vehicle Positions' : 'Trip Updates'],
            ['Ricevuto', data.fetched_at ? new Date(data.fetched_at).toLocaleString('it-IT') : '--'],
            ['Dimensione', formatBytes(data.bytes)],
            ['Record decodificati', records],
            ['SHA-256', data.sha256]
        ].map(([title, value]) =>
            `<div class="stat-card"><div class="stat-title">${escapeHtml(title)}</div>` +
            `<div class="inspector-stat-value" title="${escapeHtml(value)}">${escapeHtml(value)}</div></div>`
        ).join('');

        raw.value = data.raw_base64 || '';
        hex.textContent = data.raw_hex_preview || '--';
        decoded.textContent = JSON.stringify(data.decoded ?? [], null, 2);
        setStatus(`Feed caricato · ${formatBytes(data.bytes)} · ${records} record`);
    }

    async function loadFeed() {
        if (loading) return;
        loading = true;
        refresh.disabled = true;
        setStatus('Richiesta feed in corso...');
        const params = new URLSearchParams({ service: service.value, kind: kind.value });
        try {
            const response = await fetch(`/api/admin/gtfs-rt-inspector?${params}`, {
                credentials: 'same-origin',
                cache: 'no-store',
                headers: { Accept: 'application/json' }
            });
            const data = await response.json();
            if (response.status === 401) {
                window.location.href = '/admin/login';
                return;
            }
            if (!response.ok || !data.success) throw new Error(data.error || `HTTP ${response.status}`);
            render(data.data);
        } catch (error) {
            setStatus(`Errore: ${error.message}`, true);
            decoded.textContent = 'Nessun dato disponibile.';
        } finally {
            loading = false;
            refresh.disabled = false;
        }
    }

    function updateTimer() {
        if (timer) clearInterval(timer);
        timer = auto.checked ? setInterval(loadFeed, 30000) : null;
    }

    refresh.addEventListener('click', loadFeed);
    service.addEventListener('change', loadFeed);
    kind.addEventListener('change', loadFeed);
    auto.addEventListener('change', updateTimer);
    copy.addEventListener('click', async () => {
        if (!raw.value) return;
        try {
            await navigator.clipboard.writeText(raw.value);
            setStatus('Payload Base64 copiato negli appunti.');
        } catch (error) {
            raw.select();
            document.execCommand('copy');
            setStatus('Payload Base64 copiato negli appunti.');
        }
    });

    loadFeed();
})();
