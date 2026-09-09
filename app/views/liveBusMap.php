<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mezzi Live Map - ACTV</title>
    <meta name="description" content="Mappa in tempo reale dei bus e dei vaporetti ACTV in servizio">

    <!-- Leaflet -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>

    <?php require COMMON_HTML_HEAD; ?>
    <link rel="stylesheet" href="/css/liveBusMap.css">
</head>
<body>

    <!-- Header -->
    <div class="header-green">
        <a href="javascript:history.back()" class="back-button">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 16 16">
                <path fill-rule="evenodd" d="M15 8a.5.5 0 0 0-.5-.5H2.707l3.147-3.146a.5.5 0 1 0-.708-.708l-4 4a.5.5 0 0 0 0 .708l4 4a.5.5 0 0 0 .708-.708L2.707 8.5H14.5A.5.5 0 0 0 15 8z" />
            </svg>
        </a>
        <div class="header-title">Mezzi Live</div>
        <div style="width: 24px;"></div>
    </div>

    <!-- Filters bar -->
    <div id="filters-bar">
        <div id="service-switch" class="service-switch" role="group" aria-label="Mezzi visualizzati">
            <button type="button" class="service-switch-btn" data-service="both">Entrambi</button>
            <button type="button" class="service-switch-btn" data-service="navigation">Navigazione</button>
            <button type="button" class="service-switch-btn" data-service="automobilistico">Automobilistico</button>
        </div>
        <input type="text" id="filter-input" placeholder="Filtra: linea, tripId, routeId..." autocomplete="off">
        <button id="filter-clear" class="filter-btn" title="Cancella filtro">✕</button>
        <button id="btn-toggle-nd" class="filter-btn nd-toggle-btn" type="button" title="Mostra i mezzi senza linea"><span class="nd-toggle-icon">⊘</span><span class="nd-toggle-label">Mostra N/D</span></button>
        <button id="btn-refresh" class="filter-btn" title="Aggiorna">↻</button>
    </div>

    <!-- Map -->
    <div id="map"></div>

    <!-- Status bar -->
    <div id="status-bar">
        <div id="bus-counter">
            <span class="spinner-small"></span>
            <span id="counter-text">Caricamento...</span>
        </div>
        <div id="last-update"></div>
    </div>

    <script src="/js/liveBusMap.js"></script>
</body>
</html>
