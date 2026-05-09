<!DOCTYPE html>
<html lang="it">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Dettaglio Fermata - ACTV</title>
        <?php require COMMON_HTML_HEAD; ?>
        <link rel="stylesheet" href="/css/structure/structure-stop.css">
        <link rel="stylesheet" href="/css/stop.css">
        <script src="/js/notifications.js"></script>
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
                <button class="notify-button" id="notify-btn" onclick="toggleStopNotifications()" title="Notifiche ritardi">
                    &#128276;
                </button>
            </div>
        </div>
        <div class="parent-wrapper">
            <div id="filter-container"></div>
        </div>

        <!-- Notification Settings Panel -->
        <div id="notification-panel" class="notification-panel" style="display: none;">
            <div class="notif-panel-header">
                <span>Notifiche Ritardi</span>
                <button class="notif-panel-close" onclick="closeNotificationPanel()">&times;</button>
            </div>
            <div class="notif-panel-body">
                <div class="notif-setting">
                    <label for="delay-threshold">Avvisa per ritardi superiori a:</label>
                    <div class="threshold-selector">
                        <button class="threshold-btn" onclick="setThreshold(3)">3 min</button>
                        <button class="threshold-btn" onclick="setThreshold(5)">5 min</button>
                        <button class="threshold-btn" onclick="setThreshold(10)">10 min</button>
                        <button class="threshold-btn" onclick="setThreshold(15)">15 min</button>
                    </div>
                </div>
                <p class="notif-info">Riceverai una notifica quando una linea che passa da questa fermata ha un ritardo superiore alla soglia impostata.</p>
            </div>
        </div>

        <!-- Contenuto Principale -->
        <div class="main-content pb-5">

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
    </body>
</html>
