<!DOCTYPE html>
<html lang="it">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Mappa Stazioni ACTV</title>
        <!-- CSS di Leaflet -->
        <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
        <?php require COMMON_HTML_HEAD; ?>
        <link rel="stylesheet" href="/css/structure/structure-home.css">
        <link rel="stylesheet" href="/css/home.css">

        <!-- JavaScript di Leaflet -->
        <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
        <script src='https://api.mapbox.com/mapbox.js/plugins/leaflet-fullscreen/v1.0.1/Leaflet.fullscreen.min.js'></script>
        <link href='https://api.mapbox.com/mapbox.js/plugins/leaflet-fullscreen/v1.0.1/leaflet.fullscreen.css' rel='stylesheet' />
        <script src="/components/StopCard.js"></script>
        
        <script src="/js/script-home.js"></script>
    </head>
    <body>

        <!-- Header -->
        <div class="header-green">
            <!-- Logo o Icona Menu (Placeholder basato su spazio vuoto nel design) -->
            <div style="height: 20px;"></div> 
            <h1 class="header-title">ACTV Live <br> Venezia</h1>
            <h3 class="header-subtitle">Orari in Tempo Reale</h3>
            <div class="theme-toggle" style="position: absolute; top: 20px; right: 20px;">
                <button class="btn btn-primary rounded-pill px-4 py-2" onclick="toggleTheme()">
                    <img src="/svg/light_mode.svg" alt="Toggle Theme" id="theme-icon"> (Demo)
                </button>
            </div>
        </div>

        <!-- Contenuto Principale -->
        <div class="main-content /*pb-5*/ pb-2">
            
            <!-- Sezione Fermate Preferite -->
            <div id="favorites-section" style="display: none;">
                <h6 class="section-title">Fermate Preferite</h6>
                <div id="favorites-list"></div>
            </div>
            
            <hr id="hr_favorites">
            
            <!-- Sezione Fermate Vicine (Dinamica) -->
            <div id="nearby-section">
                <h6 class="section-title">Fermate più vicine</h6>
                <div id="nearby-list"></div>
            </div>

            <hr id="hr_nearby">
            
            <h6 class="section-title">Mappa</h6>
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

            
            <!-- Pulsanti di navigazione -->
            <div class="home-actions">
                <button class="btn rounded-pill px-4 py-2 home-action-btn" onclick="window.location.href='/stopList'">
                    Vedi tutte le stazioni
                </button>
                <button class="btn rounded-pill px-4 py-2 home-action-btn" onclick="window.location.href='/route-finder'">
                    Trova percorso
                </button>
                <button class="btn rounded-pill px-4 py-2 home-action-btn" onclick="window.location.href='/delay-stats'">
                    Storico ritardi e statistiche
                </button>
            </div>
            <div id="important-info-btn" class="hidden">!</div>
            <div id="important-info-toast" style="display: none;">
                <h6>Attenzione!</h6>
                
            </div>

            <div class="mit-licence" id="footer-licence">
                MIT License (2025)
                <hr>
                App sviluppata da <a href="https://github.com/samuele-brusegan">Samuele Brusegan</a> <br>
                Grafica e design da <a href="https://github.com/andreadavanzo09-bit">Andrea Davanzo</a> <br>
                <br>
                <a href="https://github.com/samuele-brusegan/actv-live">GitHub</a>
            </div>

        </div>
    </body>
</html>