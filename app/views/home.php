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
            <div class="brand-lockup"><span>ACTV Live</span></div>
            <h1 class="header-title">Il tuo viaggio,<br>in tempo reale.</h1>
            <h3 class="header-subtitle">Orari, fermate e percorsi ACTV a Venezia</h3>
            <div class="theme-toggle" style="position: absolute; top: 20px; right: 20px;">
                <button class="theme-button" onclick="toggleTheme()" aria-label="Cambia tema">
                    <img src="/svg/light_mode.svg" alt="" id="theme-icon">
                </button>
            </div>
        </div>

        <!-- Contenuto Principale -->
        <div class="main-content /*pb-5*/ pb-2">
            
            <section class="home-welcome" aria-labelledby="home-actions-title">
                <div class="eyebrow">Servizio ACTV</div>
                <h2 id="home-actions-title">Da dove vuoi partire?</h2>
                <a class="home-primary-action" href="/route-finder"><span class="action-symbol">⇄</span><span><strong>Trova un percorso</strong><small>Partenza, destinazione e orario</small></span><span aria-hidden="true">›</span></a>
            </section>

            <section id="journey-resume" class="journey-resume" aria-labelledby="journey-resume-title">
                <div class="journey-resume-label">Il tuo ultimo viaggio</div>
                <h2 id="journey-resume-title" class="journey-resume-route"></h2>
                <div class="journey-resume-actions">
                    <a href="/route-results" class="resume-primary" id="resume-journey">Riprendi viaggio</a>
                    <a href="/route-finder" class="resume-secondary">Modifica</a>
                </div>
            </section>

            <!-- Sezione Fermate Preferite -->
            <div id="favorites-section" style="display: none;">
                <h2 class="section-title">Le tue fermate</h2>
                <div id="favorites-list"></div>
            </div>
            
            <hr id="hr_favorites">
            
            <!-- Sezione Fermate Vicine (Dinamica) -->
            <div id="nearby-section">
                <h2 class="section-title">Fermate vicine</h2>
                <div id="nearby-list"></div>
            </div>

            <hr id="hr_nearby">
            
            <h2 class="section-title">Mappa</h2>
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
                    Cerca una fermata
                </button>
                <button class="btn rounded-pill px-4 py-2 home-action-btn" onclick="window.location.href='/route-finder'">
                    Percorsi
                </button>
                <button class="btn rounded-pill px-4 py-2 home-action-btn" onclick="window.location.href='/delay-stats'">
                    Statistiche ritardi
                </button>
                <button class="btn rounded-pill px-4 py-2 home-action-btn" onclick="window.location.href='/line-schedule'">
                    Orari delle linee
                </button>
                <button class="btn rounded-pill px-4 py-2 home-action-btn" onclick="window.location.href='/trip-finder'">
                    Cerca una corsa
                </button>
                <a class="btn rounded-pill px-4 py-2 home-action-btn feedback-home-btn" href="/feedback">
                    ✎ Invia feedback
                </a>
            </div>
            <div id="important-info-btn" class="hidden">!</div>
            <div id="important-info-toast" style="display: none;">
                <h6>Attenzione!</h6>
                
            </div>

            <div class="mit-licence" id="footer-licence">
                MIT License (2025–2026)
                <hr>
                App sviluppata da <a href="https://github.com/samuele-brusegan">Samuele Brusegan</a> <br>
                Grafica e design da <a href="https://github.com/andreadavanzo09-bit">Andrea Davanzo</a> <br>
                <br>
                <a href="https://github.com/samuele-brusegan/actv-live">GitHub</a>
            </div>

        </div>
    </body>
</html>
