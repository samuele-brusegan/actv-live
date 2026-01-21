<!DOCTYPE html>
<html lang="it">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Mappa Linee - ACTV</title>
        <!-- CSS di Leaflet -->
        <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
        <?php require COMMON_HTML_HEAD; ?>
        <link rel="stylesheet" href="/css/linesMap.css">

        <!-- JavaScript di Leaflet -->
        <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
        <script src="/js/linesMap.js"></script>
    </head>

    <body>

        <!-- Header -->
        <div class="header-green">
            <a href="/" class="back-button">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-arrow-left" viewBox="0 0 16 16">
                    <path fill-rule="evenodd" d="M15 8a.5.5 0 0 0-.5-.5H2.707l3.147-3.146a.5.5 0 1 0-.708-.708l-4 4a.5.5 0 0 0 0 .708l4 4a.5.5 0 0 0 .708-.708L2.707 8.5H14.5A.5.5 0 0 0 15 8z" />
                </svg>
            </a>
            <div class="header-title">Mappa Linee</div>
            <div style="width: 24px;"></div> <!-- Spacer for centering -->
        </div>

        <div id="loading" class="loading-overlay">
            <div class="spinner"></div>
            <div>Caricamento linee...</div>
        </div>

        <div id="map"></div>
    </body>
</html>