<!DOCTYPE html>
<html lang="it">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>ACTV Live — Il tuo viaggio in tempo reale</title>
        <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
        <?php require COMMON_HTML_HEAD; ?>
        <link rel="stylesheet" href="/css/homeAlt.css">
        <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
        <script src='https://api.mapbox.com/mapbox.js/plugins/leaflet-fullscreen/v1.0.1/Leaflet.fullscreen.min.js'></script>
        <link href='https://api.mapbox.com/mapbox.js/plugins/leaflet-fullscreen/v1.0.1/leaflet.fullscreen.css' rel='stylesheet' />
        <script src="/components/StopCard.js"></script>
        <script src="/js/tour.js"></script>
        <script src="/js/script-home.js"></script>
    </head>
    <body class="home-alt">

        <header class="ha-hero">
            <div class="ha-hero-top">
                <span class="ha-brand">ACTV Live</span>
                <button class="theme-button ha-theme" onclick="toggleTheme()" aria-label="Cambia tema">
                    <img src="/svg/light_mode.svg" alt="" id="theme-icon">
                </button>
            </div>
            <h1 class="ha-title">Il tuo viaggio,<br>in tempo reale.</h1>
            <p class="ha-subtitle">Orari, fermate e percorsi ACTV a Venezia</p>

            <a class="ha-search" href="/stopList">
                <svg class="ha-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 4 4"/></svg>
                <span class="ha-search-label">Cerca una fermata</span>
                <span class="ha-search-go" aria-hidden="true">›</span>
            </a>
        </header>

        <main class="ha-main">

            <section id="home-tutorial" class="ha-card ha-tutorial" aria-labelledby="home-actions-title" style="display:none;">
                <h2 id="home-actions-title">Vuoi fare un giro?</h2>
                <p>Ti mostro in pochi secondi dove sono fermate, mappa, preferiti e tutte le altre funzioni dell'app.</p>
                <div class="ha-tutorial-actions">
                    <button type="button" class="ha-btn ha-btn-primary" onclick="ACTVTour.start()">Inizia il tour</button>
                    <button type="button" class="ha-btn ha-btn-ghost" onclick="dismissHomeTutorial()">Salta</button>
                </div>
            </section>

            <section id="journey-resume" class="ha-card ha-resume" aria-labelledby="journey-resume-title">
                <div class="ha-card-label">Il tuo ultimo viaggio</div>
                <h2 id="journey-resume-title" class="ha-resume-route"></h2>
                <div class="ha-resume-actions">
                    <a href="/route-results" class="ha-btn ha-btn-primary" id="resume-journey">Riprendi viaggio</a>
                    <a href="/route-finder" class="ha-btn ha-btn-ghost">Modifica</a>
                </div>
            </section>

            <nav class="ha-actions" aria-label="Funzioni principali">
                <a class="ha-action" href="/route-finder">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="6" cy="19" r="2.5"/><circle cx="18" cy="5" r="2.5"/><path d="M8.5 19H15a3 3 0 0 0 0-6H9a3 3 0 0 1 0-6h6.5"/></svg>
                    <span class="ha-action-text"><strong>Percorsi</strong><small>Come arrivare</small></span>
                </a>
                <a class="ha-action" href="/live-map">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="2"/><path d="M12 5a7 7 0 0 1 7 7M12 1a11 11 0 0 1 11 11M12 5a7 7 0 0 0-7 7M12 1A11 11 0 0 0 1 12"/></svg>
                    <span class="ha-action-text"><strong>Mappa live</strong><small>Mezzi in movimento</small></span>
                </a>
                <a class="ha-action" href="/line-schedule">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/></svg>
                    <span class="ha-action-text"><strong>Orari linee</strong><small>Tabella oraria</small></span>
                </a>
                <a class="ha-action" href="/trip-finder">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="5" y="3" width="14" height="16" rx="3"/><path d="M5 11h14M9 3v8M7 19v2m10-2v2M8 15h1m6 0h1"/></svg>
                    <span class="ha-action-text"><strong>Cerca corsa</strong><small>Per linea o tripId</small></span>
                </a>
                <a class="ha-action" href="/delay-stats">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 20V10M10 20V4M16 20v-8M22 20H2"/></svg>
                    <span class="ha-action-text"><strong>Ritardi</strong><small>Statistiche servizio</small></span>
                </a>
                <a class="ha-action" href="/lines-map">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 4 3 6v14l6-2 6 2 6-2V4l-6 2-6-2Zm0 0v14m6-12v14"/></svg>
                    <span class="ha-action-text"><strong>Rete</strong><small>Tutte le linee</small></span>
                </a>
            </nav>

            <section id="favorites-section" class="ha-section" style="display:none;">
                <h2 class="ha-section-title">Le tue fermate</h2>
                <div id="favorites-list" class="ha-stop-list"></div>
            </section>
            <hr id="hr_favorites" class="ha-hr">

            <section id="nearby-section" class="ha-section">
                <h2 class="ha-section-title">Fermate vicine</h2>
                <div id="nearby-list" class="ha-stop-list"></div>
            </section>
            <hr id="hr_nearby" class="ha-hr">

            <section class="ha-section" aria-labelledby="ha-map-title">
                <h2 id="ha-map-title" class="ha-section-title">Mappa delle fermate</h2>
                <div id="status" class="alert alert-info d-flex align-items-center" role="alert">
                    <svg class="bi flex-shrink-0 me-2" width="24" height="24" role="img" aria-label="Info:"><use xlink:href="#info-fill"/></svg>
                    <div>In attesa di geolocalizzazione...</div>
                </div>
                <div id="map-container">
                    <div id="map"></div>
                </div>
            </section>

            <div id="important-info-btn" class="hidden">!</div>
            <div id="important-info-toast" style="display:none;"></div>

            <footer class="ha-footer" id="footer-licence">
                <p>MIT License (2025–2026) · App di <a href="https://github.com/samuele-brusegan">Samuele Brusegan</a> e <a href="https://github.com/andreadavanzo09-bit">Andrea Davanzo</a></p>
                <p><a href="https://github.com/samuele-brusegan/actv-live">GitHub</a> · <a href="/credits">Crediti e licenze</a></p>
                <p class="ha-footer-note">Progetto non ufficiale, non affiliato ad ACTV S.p.A. — dati mappe © OpenStreetMap contributors</p>
            </footer>

        </main>
    </body>
</html>
