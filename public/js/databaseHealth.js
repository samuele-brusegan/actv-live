(function () {
    'use strict';

    const page = document.getElementById('database-health');
    if (!page) return;

    const banner = document.getElementById('db-health-banner');
    const stats = document.getElementById('db-health-stats');
    const tableBody = document.getElementById('db-health-table-body');
    const stagingPanel = document.getElementById('db-health-staging-panel');
    const stagingBody = document.getElementById('db-health-staging-body');
    const errorPanel = document.getElementById('db-health-error-panel');
    const errorText = document.getElementById('db-health-error');
    const checked = document.getElementById('db-health-checked');
    const refresh = document.getElementById('db-health-refresh');
    let loading = false;

    const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, char => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    }[char]));

    function formatDate(value) {
        if (!value) return '--';
        const date = new Date(value.includes('T') ? value : value.replace(' ', 'T') + 'Z');
        return Number.isNaN(date.getTime()) ? value : date.toLocaleString('it-IT');
    }

    function formatSize(bytes) {
        const size = Number(bytes);
        if (!Number.isFinite(size)) return '--';
        if (size < 1024) return `${size} B`;
        if (size < 1048576) return `${(size / 1024).toFixed(1)} KB`;
        if (size < 1073741824) return `${(size / 1048576).toFixed(1)} MB`;
        return `${(size / 1073741824).toFixed(2)} GB`;
    }

    function render(data) {
        const labels = { ok: 'Database raggiungibile', warning: 'Database raggiungibile con elementi da verificare', error: 'Problema di connessione o verifica SQL' };
        banner.className = `db-health-banner ${data.status || 'error'}`;
        banner.textContent = labels[data.status] || 'Stato non disponibile';

        const messages = Array.isArray(data.messages) ? data.messages : [];
        if (messages.length) {
            banner.insertAdjacentHTML('afterend', `<ul class="db-health-messages">${messages.map(message => `<li>${escapeHtml(message)}</li>`).join('')}</ul>`);
        }

        stats.innerHTML = [
            ['Database', data.database || '--'],
            ['Server', data.server_version || '--'],
            ['Test SELECT 1', data.select_1 ? 'Riuscito' : 'Fallito'],
            ['Tempo risposta', data.response_ms == null ? '--' : `${data.response_ms} ms`],
            ['Tabelle mancanti', data.missing_tables?.length ?? '--'],
            ['Ultimo log applicativo', formatDate(data.latest_log_at)]
        ].map(([label, value]) => `<div class="stat-card"><div class="stat-title">${escapeHtml(label)}</div><div class="db-health-stat-value">${escapeHtml(value)}</div></div>`).join('');

        const missing = new Set(data.missing_tables || []);
        tableBody.innerHTML = (data.tables || []).map(table => {
            const isStaging = /_gtfs_\d+_\d+$/.test(table.name);
            const status = isStaging ? 'Temporanea' : (missing.has(table.name) ? 'Mancante' : 'Presente');
            return `<tr><td>${escapeHtml(table.name)}</td><td>${escapeHtml(status)}</td><td>${table.rows_estimate == null ? '--' : Number(table.rows_estimate).toLocaleString('it-IT')}</td><td>${formatSize(table.size_bytes)}</td><td>${formatDate(table.updated_at)}</td></tr>`;
        }).join('') || '<tr><td colspan="5">Nessuna tabella leggibile.</td></tr>';

        const staging = data.staging_tables || [];
        stagingPanel.hidden = staging.length === 0;
        stagingBody.innerHTML = staging.map(table => `<tr><td>${escapeHtml(table.name)}</td><td>${table.rows_estimate == null ? '--' : Number(table.rows_estimate).toLocaleString('it-IT')}</td><td>${formatDate(table.created_at)}</td><td>${formatDate(table.updated_at)}</td></tr>`).join('');

        errorPanel.hidden = !data.error;
        errorText.textContent = data.error || '';
        checked.textContent = `Ultimo controllo: ${formatDate(data.checked_at_utc)}`;
    }

    async function load() {
        if (loading) return;
        loading = true;
        refresh.disabled = true;
        banner.className = 'db-health-banner pending';
        banner.textContent = 'Verifica in corso…';
        document.querySelector('.db-health-messages')?.remove();
        try {
            const response = await fetch('/api/admin/database-health', {
                credentials: 'same-origin',
                cache: 'no-store',
                headers: { Accept: 'application/json' }
            });
            const result = await response.json();
            if (response.status === 401) {
                window.location.href = '/admin/login';
                return;
            }
            if (!response.ok || !result.success) throw new Error(result.error || `HTTP ${response.status}`);
            render(result.data);
        } catch (error) {
            banner.className = 'db-health-banner error';
            banner.textContent = 'Impossibile leggere lo stato del database';
            errorPanel.hidden = false;
            errorText.textContent = error.message;
        } finally {
            loading = false;
            refresh.disabled = false;
        }
    }

    refresh.addEventListener('click', load);
    load();
}());
