<!DOCTYPE html>
<html lang="it">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Dettagli Viaggio - ACTV</title>
        <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
        <?php require COMMON_HTML_HEAD; ?>
        <link rel="stylesheet" href="/css/structure/structure-routeDetails.css">
        <link rel="stylesheet" href="/css/routeDetails.css">
        <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
        <script src="/js/routeDetails.js"></script>
    </head>
    <body>

        <!-- Header -->
        <div class="header-green">
            <div style="height: 20px;">
                <a href="javascript:history.back()" class="back-button">&larr;</a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">

            <!-- Info "parti da qui" (popolata via JS se l'utente è vicino) -->
            <div id="leave-info" class="leave-info" style="display: none;"></div>

            <div class="details-card">

                <!-- Header Info inside card -->
                <div class="header-info">
                    <div class="header-date" id="route-date"></div>
                    <div class="header-time" id="route-duration"></div>
                </div>

                <hr style="border: 0; border-top: 1px solid #eee; margin: 15px 0;">

                <div id="timeline-container" class="timeline">
                    <!-- Injected via JS -->
                </div>

            </div>

            <button class="btn-map" onclick="showMap()">visualizza sulla mappa</button>

        </div>

        <!-- Map Modal -->
        <div id="map-modal" class="map-modal-overlay" onclick="closeMap(event)">
            <div class="map-modal-content" onclick="event.stopPropagation()">
                <div class="map-modal-header">
                    <span>Mappa percorso</span>
                    <button class="map-modal-close" onclick="closeMap()">&times;</button>
                </div>
                <div id="route-map"></div>
            </div>
        </div>
    </body>
</html>
