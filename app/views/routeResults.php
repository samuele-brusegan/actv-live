<!DOCTYPE html>
<html lang="it">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Risultati Percorso - ACTV</title>
        <?php require COMMON_HTML_HEAD; ?>
        <link rel="stylesheet" href="/css/routeResults.css">
        <script src="/js/routeResults.js"></script>
    </head>
    <body>

        <!-- Header -->
        <div class="header-green">
            <div style="height: 20px;">
                <a href="/route-finder" class="back-button">&larr;</a>
            </div>
            <div class="header-title">Risultati<br>Percorso</div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            
            <!-- Route Summary -->
            <div class="route-summary">
                <div class="route-stops">
                    <div class="route-stop">
                        <div class="route-stop-label">da</div>
                        <div class="route-stop-name" id="origin-name">-</div>
                    </div>
                    <div class="route-arrow">→</div>
                    <div class="route-stop">
                        <div class="route-stop-label">a</div>
                        <div class="route-stop-name" id="destination-name">-</div>
                    </div>
                </div>
                <div class="route-datetime" id="datetime-info">-</div>
            </div>

            <!-- Loading State -->
            <div id="loading" class="loading">
                <div class="spinner"></div>
                <div>Ricerca percorsi in corso...</div>
            </div>

            <!-- Routes List -->
            <div id="routes-container" style="display: none;">
                <div class="section-title">percorsi disponibili</div>
                <div id="routes-list"></div>
            </div>

            <!-- No Routes -->
            <div id="no-routes" style="display: none;">
                <div class="no-routes">
                    <div class="no-routes-icon">🚫</div>
                    <div class="no-routes-text">Nessun percorso trovato</div>
                    <div class="no-routes-subtext">Prova a selezionare fermate diverse</div>
                    <button class="btn-back" onclick="window.location.href='/route-finder'">Torna indietro</button>
                </div>
            </div>

        </div>

        <!-- Modal -->
        <div id="details-modal" class="modal-overlay" onclick="closeModal(event)">
            <div class="modal-content" onclick="event.stopPropagation()">
                <div class="modal-header">
                    <div class="modal-title">Dettagli Viaggio</div>
                    <button class="modal-close" onclick="closeModal()">&times;</button>
                </div>
                <div id="modal-body">
                    <!-- Content injected by JS -->
                </div>
                <button class="btn-back" style="margin-top: 1rem;" onclick="closeModal()">Chiudi</button>
            </div>
        </div>
    </body>
</html>