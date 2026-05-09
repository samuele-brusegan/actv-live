<!DOCTYPE html>
<html lang="it">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Dettaglio Fermata - ACTV</title>
        <?php require COMMON_HTML_HEAD; ?>
        <link rel="stylesheet" href="/css/structure/structure-stop.css">
        <link rel="stylesheet" href="/css/stop.css">
        <script src="/js/widget.js"></script>
        <script src="/js/stop.js"></script>
    </head>
    <body>
        <?php 
        // phpinfo();
        ?>

        <!-- Header -->
        <div class="header-green">
            <div style="height: 20px;">
                <a href="javascript:history.back()" style="color: white; text-decoration: none; font-size: 24px;">
                    <?= getIcon('arrow_back', 24) ?>
                </a>
            </div> 
            <div class="header-title" id="station-name">Caricamento...</div>
            <div class="header-subtitle" id="station-id"></div>
            <div class="header-actions">
                <button class="favorite-button" id="favorite-btn" onclick="toggleFavorite()" title="Aggiungi ai preferiti">
                    ★
                </button>
                <button class="share-button" id="share-btn" onclick="shareWidget()" title="Condividi widget">
                    &#x1F517;
                </button>
            </div>
        </div>
        <div class="parent-wrapper">
            <div id="filter-container"></div>
        </div>

        <!-- Tab Navigation -->
        <div class="stop-tabs">
            <button class="stop-tab active" data-tab="passages" onclick="switchTab('passages')">Passaggi</button>
            <button class="stop-tab" data-tab="lines" onclick="switchTab('lines')">Linee e Orari</button>
        </div>

        <!-- Contenuto Principale -->
        <div class="main-content pb-5">
            
            <!-- Tab Passaggi -->
            <div id="tab-passages" class="tab-content active">
                <div class="section-title">Prossimi Passaggi</div>

                <div id="noticeboard"></div>
                
                <div id="loading">Caricamento passaggi...</div>

                <div id="passages-list">
                    <!-- Popolato via JS -->
                </div>
                
                <div class="text-center mt-4">
                    <button class="btn btn-outline-secondary rounded-pill px-4 py-2" onclick="location.reload()">
                        Aggiorna
                    </button>
                </div>
            </div>

            <!-- Tab Linee e Orari -->
            <div id="tab-lines" class="tab-content">
                <div class="section-title">Linee che passano da questa fermata</div>

                <div id="lines-loading" class="loading">
                    <div class="spinner"></div>
                    <div>Caricamento linee...</div>
                </div>

                <div id="lines-list">
                    <!-- Popolato via JS -->
                </div>

                <div id="lines-empty" style="display: none;">
                    <p class="text-center text-muted">Nessuna linea trovata per questa fermata.</p>
                </div>
            </div>

        </div>
    </body>
</html>
