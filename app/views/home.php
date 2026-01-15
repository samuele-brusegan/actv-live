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
            <div class="header-title">ACTV Live <br> Venezia</div>
            <a href="/admin/time-machine" class="admin-link d-none" id="admin-secret-link" title="Gestione Time Machine">
                <?= getIcon('settings', 24) ?>
            </a>
        </div>

        <!-- Contenuto Principale -->
        <div class="main-content /*pb-5*/ pb-2">
            
            <!-- Sezione Fermate Preferite -->
            <div id="favorites-section" style="display: none;">
                <div class="section-title">Fermate Preferite</div>
                <div id="favorites-list"></div>
            </div>
            
            <hr id="hr_favorites">
            
            <!-- Sezione Fermate Vicine (Dinamica) -->
            <div id="nearby-section">
                <div class="section-title">Fermate più vicine</div>
                <div id="nearby-list"></div>
            </div>

            <hr id="hr_nearby">
            
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
            <!-- <div class="text-center mt-4 mb-3">
                <button class="btn btn-secondary rounded-pill px-4 py-2" onclick="window.location.href='/lines-map'">
                    Mappa linee (NON definitivo)
                </button>
            </div> -->
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

        <!-- JavaScript di Leaflet -->
        <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
        <script src='https://api.mapbox.com/mapbox.js/plugins/leaflet-fullscreen/v1.0.1/Leaflet.fullscreen.min.js'></script>
        <link href='https://api.mapbox.com/mapbox.js/plugins/leaflet-fullscreen/v1.0.1/leaflet.fullscreen.css' rel='stylesheet' />
        <script src="/components/StopCard.js"></script>
        
        <script src="/js/script-home.js"></script>
        <script>
            // Hidden Admin Access: 5 clicks on footer
            let footerClicks = 0;
            let lastClickTime = 0;
            document.getElementById('footer-licence').addEventListener('click', (e) => {
                const currentTime = new Date().getTime();
                if (currentTime - lastClickTime < 500) {
                    footerClicks++;
                } else {
                    footerClicks = 1;
                }
                lastClickTime = currentTime;

                if (footerClicks >= 5) {
                    const adminLink = document.getElementById('admin-secret-link');
                    adminLink.classList.remove('d-none');
                    // Optional: automatic redirect
                    // window.location.href = '/admin/time-machine';
                    alert("Admin access unlocked!");
                }
            });
        </script>

    </body>
</html>