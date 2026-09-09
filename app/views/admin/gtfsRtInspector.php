<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GTFS-RT Inspector - ACTV Live</title>
    <?php require COMMON_HTML_HEAD; ?>
    <link rel="stylesheet" href="/css/admin.css">
    <link rel="stylesheet" href="/css/gtfsRtInspector.css">
    <script defer src="/js/gtfsRtInspector.js"></script>
</head>
<body class="admin-page">
<main class="admin-container" id="gtfs-rt-inspector">
    <header class="admin-header">
        <div>
            <h1>GTFS-RT Inspector</h1>
            <p class="inspector-subtitle">Payload protobuf ricevuto dal feed ACTV e risultato del decoder locale.</p>
        </div>
        <div class="inspector-actions">
            <a href="/admin/dashboard" class="inspector-button secondary">Dashboard</a>
            <a href="/admin/gtfs-update" class="inspector-button secondary">Aggiornamento GTFS</a>
            <a href="/admin/logout" class="inspector-button secondary">Logout</a>
        </div>
    </header>

    <section class="panel-card inspector-controls">
        <div>
            <label for="gtfs-rt-service">Servizio</label>
            <select id="gtfs-rt-service">
                <option value="automobilistico">Automobilistico</option>
                <option value="navigation">Navigazione</option>
            </select>
        </div>
        <div>
            <label for="gtfs-rt-kind">Feed</label>
            <select id="gtfs-rt-kind">
                <option value="vehicles">Vehicle Positions</option>
                <option value="updates">Trip Updates</option>
            </select>
        </div>
        <button type="button" class="inspector-button" id="gtfs-rt-refresh">Carica feed</button>
        <label class="inspector-checkbox"><input type="checkbox" id="gtfs-rt-auto"> Aggiorna ogni 30 secondi</label>
        <span id="gtfs-rt-status" class="inspector-status">In attesa</span>
    </section>

    <section id="gtfs-rt-stats" class="stats-grid inspector-stats"></section>

    <section class="panel-card">
        <div class="panel-header inspector-panel-header">
            <div>
                <h2>Payload raw protobuf</h2>
                <p>Il feed GTFS-RT è binario: la rappresentazione completa è mostrata in Base64.</p>
            </div>
            <button type="button" class="inspector-button secondary" id="gtfs-rt-copy">Copia Base64</button>
        </div>
        <textarea id="gtfs-rt-raw" class="inspector-raw" readonly spellcheck="false" placeholder="Carica un feed per visualizzare il payload raw..."></textarea>
        <details class="inspector-details">
            <summary>Anteprima esadecimale (primi 256 byte)</summary>
            <pre id="gtfs-rt-hex">--</pre>
        </details>
    </section>

    <section class="panel-card">
        <div class="panel-header inspector-panel-header">
            <div>
                <h2>Decodifica locale</h2>
                <p>Record interpretati dal decoder PHP usato dagli endpoint realtime.</p>
            </div>
        </div>
        <pre id="gtfs-rt-decoded" class="inspector-json">Carica un feed per visualizzare i dati decodificati.</pre>
    </section>
</main>
</body>
</html>
