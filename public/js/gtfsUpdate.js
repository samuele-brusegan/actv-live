(function () {
    'use strict';

    const app = document.getElementById('gtfs-update-app');
    if (!app) return;
    const csrf = app.dataset.csrf;
    let initialized = false;
    let statusLoading = false;
    let messageTimer = null;

    const $ = id => document.getElementById(id);
    const esc = value => String(value == null ? '' : value).replace(/[&<>"]/g, char => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;'
    }[char]));

    async function request(url, options) {
        const response = await fetch(url, {
            credentials: 'same-origin',
            cache: 'no-store',
            ...options,
            headers: { 'Accept': 'application/json', ...(options && options.headers) }
        });
        const text = await response.text();
        let data;
        try {
            data = JSON.parse(text);
        } catch (error) {
            const contentType = response.headers.get('content-type') || 'sconosciuto';
            const preview = text.trim().replace(/\s+/g, ' ').slice(0, 160);

            if (!isStringValidHtml(text)) {
                throw new Error(
                    `Risposta non JSON (${response.status}, ${contentType})` +
                    (preview ? `: ${preview}` : ': risposta vuota')
                );
            } else {
                let xcodeTable = document.getElementById('xcode-table');
                if (!xcodeTable) {
                    document.body.insertAdjacentHTML('afterbegin', '<div id="xcode-table"></div>');
                    xcodeTable = document.getElementById('xcode-table');
                }
                xcodeTable.innerHTML = text;

                throw new Error(
                    `Risposta HTML (${response.status}, ${contentType})` +
                    (preview ? `: ${preview}` : ': risposta vuota')
                );
            }
        }
        if (response.status === 401) {
            window.location.href = '/admin/login';
            throw new Error('Sessione amministratore scaduta.');
        }
        if (!response.ok || !data.success) throw new Error(data.error || data.message || 'Errore richiesta');
        return data;
    }

    function showMessage(message, error, autoHide = !error) {
        const box = $('gtfs-message');
        if (messageTimer) clearTimeout(messageTimer);
        box.hidden = false;
        box.className = 'gtfs-message ' + (error ? 'error' : 'success');
        box.textContent = message;
        if (autoHide) {
            messageTimer = setTimeout(() => { box.hidden = true; }, 5000);
        }
    }

    function clearError() {
        const box = $('gtfs-message');
        if (box.classList.contains('error')) box.hidden = true;
    }

    function renderOverview(overview) {
        const { config, state, database, cache_updated_at: cacheUpdatedAt, log_tail: logTail } = overview;
        if (!initialized) {
            $('schedule-enabled').checked = !!config.enabled;
            $('schedule-weekday').value = String(config.weekday);
            $('schedule-time').value = config.time;
            initialized = true;
        }

        const status = state.running ? 'In esecuzione' : ({
            completed: 'Completato',
            failed: 'Fallito',
            idle: 'Mai eseguito'
        }[state.status] || state.status);
        $('gtfs-stats').innerHTML = [
            ['Stato', status],
            ['Automatico', config.enabled ? 'Attivo' : 'Disattivato'],
            ['Ultimo avvio', state.started_at ? new Date(state.started_at).toLocaleString('it-IT') : '--'],
            ['Cache aggiornata', cacheUpdatedAt ? new Date(cacheUpdatedAt).toLocaleString('it-IT') : '--'],
            ['Fermate / Corse', `${database.stops ?? '--'} / ${database.trips ?? '--'}`]
        ].map(([title, value]) =>
            `<div class="stat-card"><div class="stat-title">${esc(title)}</div>` +
            `<div class="gtfs-stat-value">${esc(value)}</div></div>`
        ).join('');

        $('update-summary').textContent = state.error
            ? `Errore: ${state.error}`
            : `${status}${state.feed_url ? ' · ' + state.feed_url : ''}`;
        $('start-update').disabled = !!state.running;
        $('start-update').textContent = state.running ? 'Aggiornamento in corso' : 'Avvia aggiornamento';

        $('task-list').innerHTML = (state.tasks || []).map(task => {
            const total = Math.max(1, Number(task.total) || 1);
            const current = Math.min(total, Number(task.current) || 0);
            const percent = Math.round(current / total * 100);
            return `<div class="gtfs-task ${esc(task.status)}">` +
                `<div class="gtfs-task-head"><strong>${esc(task.name)}</strong>` +
                `<span>${current}/${total}</span></div>` +
                `<div class="gtfs-progress"><div style="width:${percent}%"></div></div>` +
                `</div>`;
        }).join('') || '<div class="gtfs-muted">Nessuna esecuzione registrata.</div>';

        $('gtfs-log').textContent = (logTail || []).join('\n') || 'Nessun log disponibile.';
    }

    async function loadStatus() {
        if (statusLoading) return;
        statusLoading = true;
        try {
            const response = await request('/api/admin/gtfs-update/status');
            renderOverview(response.data);
            clearError();
        } catch (error) {
            showMessage(error.message, true, false);
        } finally {
            statusLoading = false;
        }
    }

    async function saveSchedule(message) {
        try {
            await request('/api/admin/gtfs-update/config', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    csrf,
                    enabled: $('schedule-enabled').checked,
                    weekday: Number($('schedule-weekday').value),
                    time: $('schedule-time').value
                })
            });
            showMessage(message, false);
            loadStatus();
        } catch (error) {
            showMessage(error.message, true);
        }
    }

    $('schedule-enabled').addEventListener('change', () => {
        saveSchedule($('schedule-enabled').checked
            ? 'Aggiornamento automatico attivato.'
            : 'Aggiornamento automatico disattivato.');
    });

    $('save-schedule').addEventListener('click', () => {
        saveSchedule('Pianificazione salvata.');
    });

    $('start-update').addEventListener('click', async () => {
        try {
            await request('/api/admin/gtfs-update/start', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ csrf })
            });
            showMessage('Aggiornamento avviato.', false);
            loadStatus();
        } catch (error) {
            showMessage(error.message, true);
        }
    });

    loadStatus();
    setInterval(loadStatus, 3000);
})();

function isStringValidHtml(html, mimeType = 'application/xml') {
  const domParser = new DOMParser();
  const doc = domParser.parseFromString(html, mimeType);
  const parseError = doc.documentElement.querySelector('parsererror');
  const result = {
    isParseErrorAvailable: parseError !== null,
    isStringValidHtml: false,
    parsedDocument: ''
  };

  if (parseError !== null && parseError.nodeType === Node.ELEMENT_NODE) {
    result.parsedDocument = parseError.outerHTML;
  } else {
    result.isStringValidHtml = true;
    result.parsedDocument = typeof doc.documentElement.textContent === 'string' ? doc.documentElement.textContent : '';
  }

  return result;
}
