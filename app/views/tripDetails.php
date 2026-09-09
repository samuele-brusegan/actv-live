<!DOCTYPE html>
<html lang="it">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Dettaglio Corsa - ACTV</title>
        <?php require COMMON_HTML_HEAD; ?>
        <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css">
        <link rel="stylesheet" href="/css/structure/structure-tripDetails.css">
        <link rel="stylesheet" href="/css/tripDetails.css">
        <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
        <script type="text/javascript" src="/js/tripDetails.js"></script>
        <script type="module" src="https://cdn.jsdelivr.net/npm/ldrs/dist/auto/hatch.js"></script>
    </head>

    <body>

    <!-- Header -->
    <div class="header-green">
        <div style="height: 20px;">
            <a href="javascript:history.back()" style="color: white; text-decoration: none; font-size: 24px;">
                <?= getIcon('arrow_back', 24) ?>
            </a>
        </div>

            <div class="trip-header-info">
                <div class="line-info">
                    <div class="line-box line-badge" id="line-number">--</div>
                </div>
                <div class="direction-info">
                    <div class="direction-label">DIREZIONE</div>
                    <div class="direction-name" id="direction-name">Caricamento...</div>
                </div>
            </div>

            <div class="time-remaining" id="time-container" style="display: none;">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                    <path d="M8 3.5a.5.5 0 0 0-1 0V9a.5.5 0 0 0 .252.434l3.5 2a.5.5 0 0 0 .496-.868L8 8.71V3.5z" />
                    <path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm7-8A7 7 0 1 1 1 8a7 7 0 0 1 14 0z" />
                </svg>
                <span id="time-text">-- min</span>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-card">
            <div id="stops-container">
                <div class="loading-state">
                    <div style="display: flex; flex-direction: column; align-items: center; gap: 10px;">
                        <l-hatch
                            size="28"
                            stroke="4"
                            speed="3.5"
                            color="black" 
                        ></l-hatch>
                        <br>

                        Caricamento fermate...
                    </div>
                </div>
            </div>
        </div>

        <button class="map-fab" onclick="openMap()" title="Vedi sulla mappa" aria-controls="trip-map-dialog">
            <?= getIcon('bus', 24) ?>
        </button>

        <div id="trip-map-dialog" class="trip-map-dialog" hidden aria-label="Mappa della corsa">
            <div id="trip-map"></div>
            <header class="trip-map-header">
                <button class="trip-map-close" type="button" onclick="closeMap()" aria-label="Chiudi mappa">&larr;</button>
                <div class="trip-map-heading">
                    <span id="trip-map-line" class="trip-map-line-badge">--</span>
                    <div><strong id="trip-map-title">Mappa corsa</strong><small id="trip-map-direction">Caricamento...</small></div>
                </div>
                <div id="trip-map-status" class="trip-map-status"><span class="trip-map-live-dot"></span>Caricamento percorso...</div>
            </header>
            <section id="trip-map-stops" class="trip-map-stops">
                <button class="trip-map-stops-header" type="button" onclick="toggleMapStops()" aria-expanded="true">
                    <span>Fermate della corsa</span><span class="trip-map-chevron">⌄</span>
                </button>
                <div class="trip-map-stops-legend"><span class="trip-map-legend-dot visited"></span> Già passata <span class="trip-map-legend-dot upcoming"></span> Da raggiungere</div>
                <div id="trip-map-stops-list" class="trip-map-stops-list"></div>
            </section>
        </div>
    </body>
</html>
