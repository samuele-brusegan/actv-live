<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stato database - ACTV Live</title>
    <?php require COMMON_HTML_HEAD; ?>
    <link rel="stylesheet" href="/css/admin.css">
    <link rel="stylesheet" href="/css/databaseHealth.css">
    <link rel="stylesheet" href="/css/admin-shell.css">
    <script defer src="/js/databaseHealth.js"></script>
</head>
<body class="admin-page">
<main class="admin-container" id="database-health">
    <header class="admin-header">
        <div>
            <h1>Stato database</h1>
            <p class="db-health-subtitle">Verifica connessione SQL, schema e tabelle del database usato dall’app.</p>
        </div>
        <nav class="admin-actions" aria-label="Navigazione amministrazione">
            <a href="/admin/dashboard" class="admin-action">Dashboard</a>
            <a href="/admin/gtfs-update" class="admin-action">Aggiornamento GTFS</a>
            <a href="/admin/logout" class="admin-action">Logout</a>
        </nav>
    </header>

    <section class="panel-card db-health-overview" aria-live="polite">
        <div id="db-health-banner" class="db-health-banner pending">Verifica in corso…</div>
        <div class="db-health-stats" id="db-health-stats"></div>
        <p class="db-health-note">La verifica è in sola lettura. Le quantità delle righe sono stime fornite da MySQL/MariaDB; non vengono modificati dati o tabelle.</p>
        <div class="db-health-footer">
            <span id="db-health-checked">Ultimo controllo: --</span>
            <button type="button" class="db-health-button" id="db-health-refresh">Aggiorna controllo</button>
        </div>
    </section>

    <section class="panel-card">
        <div class="db-health-section-heading">
            <div><h2>Tabelle presenti</h2><p>Include schema GTFS e tabelle applicative.</p></div>
        </div>
        <div class="table-responsive">
            <table class="admin-table db-health-table">
                <thead><tr><th>Tabella</th><th>Stato</th><th>Righe stimate</th><th>Dimensione</th><th>Ultima modifica</th></tr></thead>
                <tbody id="db-health-table-body"><tr><td colspan="5">Caricamento…</td></tr></tbody>
            </table>
        </div>
    </section>

    <section class="panel-card" id="db-health-staging-panel" hidden>
        <div class="db-health-section-heading">
            <div><h2>Tabelle temporanee GTFS</h2><p>Un gruppo presente da oltre un’ora può indicare un aggiornamento interrotto; verifica il processo prima di intervenire.</p></div>
        </div>
        <div class="table-responsive">
            <table class="admin-table db-health-table">
                <thead><tr><th>Tabella temporanea</th><th>Righe stimate</th><th>Creata</th><th>Ultima modifica</th></tr></thead>
                <tbody id="db-health-staging-body"></tbody>
            </table>
        </div>
    </section>

    <section class="panel-card db-health-error-panel" id="db-health-error-panel" hidden>
        <h2>Dettaglio errore</h2>
        <pre id="db-health-error"></pre>
    </section>
</main>
</body>
</html>
