<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mappa Stazioni ACTV</title>
    <!-- CSS di Leaflet -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
    <?php require COMMON_HTML_HEAD; ?>
    <link rel="stylesheet" href="/css/home.css">
</head>
<body>

    <!-- Header -->
    <div class="header-green">
        <!-- Logo o Icona Menu (Placeholder basato su spazio vuoto nel design) -->
        <div style="height: 20px;"></div> 
        <div class="header-title">Dove vuoi<br>andare?</div>
    </div>

    <!-- Contenuto Principale -->
    <div class="main-content pb-5">
        
        <!-- Sezione Fermate Preferite -->
        <div id="favorites-section" style="display: none;">
            <div class="section-title">Fermate Preferite</div>
            <div id="favorites-list"></div>
        </div>
        
        <hr>
        
        <!-- Sezione Fermate Vicine (Dinamica) -->
        <div id="nearby-section">
            <div class="section-title">Fermate più vicine</div>
            <div id="nearby-list"></div>
        </div>

        <hr>
        
        <div class="section-title">Mappa</div>
        <!-- Status Geolocation -->
        <div id="status" class="alert alert-info d-flex align-items-center" role="alert">
            <svg class="bi flex-shrink-0 me-2" width="24" height="24" role="img" aria-label="Info:"><use xlink:href="#info-fill"/></svg>
            <div>
                In attesa di geolocalizzazione...
            </div>
        </div>
    
        <!-- Mappa -->
        <div id="map-container">
            <div id="map"></div>
        </div>

        
        <!-- Pulsante Lista Completa -->
        <div class="text-center mt-4 mb-3">
            <button class="btn btn-outline-primary rounded-pill px-4 py-2" onclick="window.location.href='/stopList'" style="width: 100%;">
                Vedi tutte le stazioni
            </button>
        </div>

		<!-- Pulsante Trova Percorso -->
        <div class="text-center mt-4 mb-3">
            <button class="btn btn-primary rounded-pill px-4 py-2" onclick="window.location.href='/route-finder'">
                Trova percorso
            </button>
        </div>

        <!-- Pulsante Mappa Linee -->
        <div class="text-center mt-4 mb-3">
            <button class="btn btn-secondary rounded-pill px-4 py-2" onclick="window.location.href='/lines-map'">
                Mappa linee (NON definitivo)
            </button>
        </div>

    </div>

    <!-- JavaScript di Leaflet -->
    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
    <script src="/components/StopCard.js"></script>
    
    <script src="/js/script-home.js"></script>

</body>
</html>