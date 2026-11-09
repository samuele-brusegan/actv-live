<!DOCTYPE html>
<html lang="<?= I18n::lang() ?>">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <!-- CSS di Leaflet -->
        <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
        <?php
        $SEO = [
            'title' => t('meta.home.title'),
            'desc' => t('meta.home.desc'),
            'jsonld' => [
                '@context' => 'https://schema.org',
                '@type' => 'WebApplication',
                'name' => 'ACTV Live',
                'applicationCategory' => 'TravelApplication',
                'operatingSystem' => 'Web',
                'inLanguage' => ['it', 'en'],
                'description' => t('meta.home.desc'),
            ],
        ];
        require COMMON_HTML_HEAD;
        ?>
        <link rel="stylesheet" href="/css/structure/structure-home.css">
        <link rel="stylesheet" href="/css/home.css">

        <!-- JavaScript di Leaflet -->
        <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
        <script src='https://api.mapbox.com/mapbox.js/plugins/leaflet-fullscreen/v1.0.1/Leaflet.fullscreen.min.js'></script>
        <link href='https://api.mapbox.com/mapbox.js/plugins/leaflet-fullscreen/v1.0.1/leaflet.fullscreen.css' rel='stylesheet' />
        <script src="/components/StopCard.js"></script>
        <script src="/js/tour.js"></script>
        <script src="/js/widget.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.js" integrity="sha384-8FWZA6BGMXhsfO+BLtrJK0We6gg5o1JyO8xQm6peWDEUs17ACA5ziE/NIAkl9z2k" crossorigin="anonymous"></script>
        <script src="/js/script-home.js"></script>
    </head>
    <body>

        <!-- Header -->
        <div class="header-green">
            <div class="brand-lockup"><span>ACTV Live</span></div>
            <h1 class="header-title"><?= t('home.title') ?></h1>
            <p class="header-subtitle"><?= te('home.subtitle') ?></p>
            <div class="theme-toggle" style="position: absolute; top: 20px; right: 20px;">
                <button class="theme-button" onclick="toggleTheme()" aria-label="<?= te('ui.changeTheme') ?>">
                    <img src="/svg/light_mode.svg" alt="" id="theme-icon">
                </button>
            </div>
        </div>

        <!-- Contenuto Principale -->
        <div class="main-content /*pb-5*/ pb-2">
            
            <section id="home-tutorial" class="home-welcome" aria-labelledby="home-actions-title" style="display:none;">
                <div class="eyebrow"><?= te('home.welcome') ?></div>
                <h2 id="home-actions-title"><?= te('home.tourTitle') ?></h2>
                <p class="home-tutorial-text"><?= te('home.tourText') ?></p>
                <div class="home-tutorial-actions">
                    <button type="button" class="btn btn-primary rounded-pill px-4 py-2" onclick="ACTVTour.start()"><?= te('home.tourStart') ?></button>
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4 py-2" onclick="dismissHomeTutorial()"><?= te('home.tourSkip') ?></button>
                </div>
            </section>

            <section class="home-welcome" aria-labelledby="home-stops-title">
                <div class="eyebrow"><?= te('home.serviceEyebrow') ?></div>
                <h2 id="home-stops-title"><?= te('home.stopsTitle') ?></h2>
                <a class="home-primary-action" href="/stopList"><span class="action-symbol">◉</span><span><strong><?= te('home.searchStop') ?></strong><small><?= te('home.searchStopSub') ?></small></span><span aria-hidden="true">›</span></a>
            </section>

            <section id="journey-resume" class="journey-resume" aria-labelledby="journey-resume-title">
                <div class="journey-resume-label"><?= te('home.lastJourney') ?></div>
                <h2 id="journey-resume-title" class="journey-resume-route"></h2>
                <div class="journey-resume-actions">
                    <a href="/route-results" class="resume-primary" id="resume-journey"><?= te('home.resume') ?></a>
                    <a href="/route-finder" class="resume-secondary"><?= te('home.edit') ?></a>
                </div>
            </section>

            <!-- Sezione Fermate Preferite -->
            <div id="favorites-section" style="display: none;">
                <h2 class="section-title"><?= te('home.favStops') ?></h2>
                <div id="favorites-list"></div>
            </div>
            
            <hr id="hr_favorites">
            
            <!-- Sezione Fermate Vicine (Dinamica) -->
            <div id="nearby-section">
                <h2 class="section-title"><?= te('home.nearby') ?></h2>
                <div id="nearby-list"></div>
            </div>

            <hr id="hr_nearby">
            
            <h2 class="section-title"><?= te('home.map') ?></h2>
            <!-- Status Geolocation -->
            <div id="status" class="alert alert-info d-flex align-items-center" role="alert">
                <svg class="bi flex-shrink-0 me-2" width="24" height="24" role="img" aria-label="Info:"><use xlink:href="#info-fill"/></svg>
                <div>
                    <?= te('home.waitingGeo') ?>
                </div>
            </div>
        
            <!-- Mappa -->
            <div id="map-container">
                <div id="map"></div>
            </div>

            
            <!-- Pulsanti di navigazione -->
            <div class="home-actions">
                <button class="btn rounded-pill px-4 py-2 home-action-btn" onclick="window.location.href='/stopList'">
                    <?= te('home.searchStop') ?>
                </button>
                <button class="btn rounded-pill px-4 py-2 home-action-btn" onclick="window.location.href='/route-finder'">
                    <?= te('home.btnRoutes') ?>
                </button>
                <button class="btn rounded-pill px-4 py-2 home-action-btn" onclick="window.location.href='/delay-stats'">
                    <?= te('home.btnDelays') ?>
                </button>
                <button class="btn rounded-pill px-4 py-2 home-action-btn" onclick="window.location.href='/line-schedule'">
                    <?= te('home.btnSchedules') ?>
                </button>
                <button class="btn rounded-pill px-4 py-2 home-action-btn" onclick="window.location.href='/trip-finder'">
                    <?= te('home.btnTrip') ?>
                </button>
                <a class="btn rounded-pill px-4 py-2 home-action-btn feedback-home-btn" href="/feedback">
                    ✎ <?= te('feedback.title') ?>
                </a>
            </div>
            <div id="important-info-btn" class="hidden">!</div>
            <div id="important-info-toast" style="display: none;"></div>

            <div class="mit-licence" id="footer-licence">
                MIT License (2025–2026)
                <hr>
                <?= te('home.devBy') ?> <a href="https://github.com/samuele-brusegan">Samuele Brusegan</a> <br>
                <?= te('home.designBy') ?> <a href="https://github.com/andreadavanzo09-bit">Andrea Davanzo</a> <br>
                <br>
                <a href="https://github.com/samuele-brusegan/actv-live">GitHub</a> ·
                <a href="/credits"><?= te('credits.title') ?></a> <br>
                <br>
                <small><?= t('home.unofficial') ?></small>
            </div>

        </div>

        <button class="qr-fab" id="qr-btn" onclick="showQrDialog(window.location.href)" title="<?= te('home.qr') ?>" aria-label="<?= te('home.qr') ?>"><?= getIcon('qr_code', 26) ?></button>
    </body>
</html>
