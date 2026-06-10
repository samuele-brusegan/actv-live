<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aggiornamento GTFS - ACTV Live</title>
    <?php require COMMON_HTML_HEAD; ?>
    <link rel="stylesheet" href="/css/admin.css">
    <link rel="stylesheet" href="/css/gtfsUpdate.css">
    <script defer src="/js/gtfsUpdate.js"></script>
</head>
<body>
    <div class="admin-container" id="gtfs-update-app" data-csrf="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
        <div class="admin-header">
            <div>
                <h1>Aggiornamento GTFS</h1>
                <div class="gtfs-muted">Import atomico di feed, cache, database, shape e data_url.</div>
            </div>
            <div class="gtfs-actions">
                <a href="/admin/dashboard" class="gtfs-button secondary">Dashboard</a>
                <a href="/admin/logout" class="gtfs-button secondary">Logout</a>
            </div>
        </div>

        <div id="gtfs-message" class="gtfs-message" hidden></div>

        <div class="stats-grid" id="gtfs-stats"></div>

        <section class="panel-card">
            <div class="panel-header">
                <div>
                    <h2>Pianificazione settimanale</h2>
                    <div class="gtfs-muted">
                        Il salvataggio installa o aggiorna automaticamente il cron settimanale.
                        <strong>Attenzione:</strong> giorno e ora sono espressi in UTC+00.
                    </div>
                </div>
                <label class="gtfs-switch">
                    <input type="checkbox" id="schedule-enabled">
                    <span></span>
                    Aggiornamento automatico
                </label>
            </div>
            <div class="gtfs-form-row">
                <label>Giorno
                    <select id="schedule-weekday">
                        <option value="1">Lunedì</option>
                        <option value="2">Martedì</option>
                        <option value="3">Mercoledì</option>
                        <option value="4">Giovedì</option>
                        <option value="5">Venerdì</option>
                        <option value="6">Sabato</option>
                        <option value="7">Domenica</option>
                    </select>
                </label>
                <label>Ora (UTC+00)
                    <input type="time" id="schedule-time" value="03:00:00" step="1">
                </label>
                <button type="button" class="gtfs-button" id="save-schedule">Salva pianificazione</button>
            </div>
        </section>

        <section class="panel-card">
            <div class="panel-header">
                <div>
                    <h2>Stato aggiornamento</h2>
                    <div class="gtfs-muted" id="update-summary">Caricamento stato...</div>
                </div>
                <button type="button" class="gtfs-button danger" id="start-update">Avvia aggiornamento</button>
            </div>
            <div id="task-list" class="gtfs-task-list"></div>
        </section>

        <section class="panel-card">
            <h2>Log recente</h2>
            <pre id="gtfs-log" class="gtfs-log">Nessun log disponibile.</pre>
        </section>
    </div>
</body>
</html>
