<!DOCTYPE html>
<html lang="it">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Dettaglio Fermata - ACTV</title>
        <?php require COMMON_HTML_HEAD; ?>
        <link rel="stylesheet" href="/css/stop.css">
        <script src="/js/stop.js"></script>
    </head>
    <body>

        <!-- Header -->
        <div class="header-green">
            <div style="height: 20px;">
                <a href="javascript:history.back()" style="color: white; text-decoration: none; font-size: 24px;">
                    <?= getIcon('arrow_back', 24) ?>
                </a>
            </div> 
            <div class="header-title" id="station-name">Caricamento...</div>
            <div class="header-subtitle" id="station-id"></div>
            <button class="favorite-button" id="favorite-btn" onclick="toggleFavorite()" title="Aggiungi ai preferiti">
                ★
            </button>
        </div>
        <div class="parent-wrapper">
            <div id="filter-container"></div>
        </div>

        <!-- Contenuto Principale -->
        <div class="main-content pb-5">
            
            <div class="section-title">Prossimi Passaggi</div>

            <div id="noticeboard"></div>
            
            <div id="loading">Caricamento passaggi...</div>

            <!-- Time Machine Banner -->
            <div id="tm-banner" class="alert alert-warning py-2 mb-3 small d-none">
                <div class="d-flex justify-content-between align-items-center">
                    <span><?= getIcon('history', 18, 'align-middle') ?> <strong>Time Machine attiva</strong></span>
                    <span id="tm-current-time"></span>
                </div>
            </div>

            <div id="passages-list">
                <!-- Popolato via JS -->
            </div>
            
            <div class="text-center mt-4">
                <button class="btn btn-outline-secondary rounded-pill px-4 py-2" onclick="location.reload()">
                    Aggiorna
                </button>
            </div>

        </div>
    </body>
</html>