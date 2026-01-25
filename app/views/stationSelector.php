<!DOCTYPE html>
<html lang="it">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Seleziona Fermata - ACTV</title>
        <?php require COMMON_HTML_HEAD; ?>

        <link rel="stylesheet" href="/css/structure/structure-stopList.css">
        <link rel="stylesheet" href="/css/structure/structure-stationSelector.css">
        <link rel="stylesheet" href="/css/stationSelector.css">
        
        <!-- StopCard Component -->
        <script src="/components/StopCard.js"></script>
        <script src="/js/stationSelector.js"></script>
    </head>
    <body>

        <!-- Header -->
        <div class="header-green">
            <div style="height: 20px;">
                <a href="/route-finder" class="back-button">&larr;</a>
            </div>
            <div class="header-title">
                <?php 
                $type = $_GET['type'] ?? 'origin';
                echo $type === 'origin' ? 'Dove vuoi<br>andare?' : 'Seleziona<br>destinazione';
                ?>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            
            <!-- Search Bar -->
            <div class="search-container">
                <input type="text" id="search-input" class="search-input" placeholder="🔍 Cerca fermata..." oninput="filterStops()">
            </div>

            <!-- Favorites Section -->
            <div id="favorites-section">
                <div class="section-title">fermate preferite</div>
                <div id="favorites-list"></div>
            </div>

            <!-- Recent Section -->
            <div id="recent-section">
                <div class="section-title">recenti</div>
                <div id="recent-list"></div>
            </div>

            <!-- All Stops Section -->
            <div id="all-stops-section" style="display: none;">
                <div class="section-title">tutte le fermate</div>
                <div id="all-stops-list" style="margin-bottom: 10px;"></div>
            </div>

        </div>

        <!-- Action Buttons -->
        <div class="action-buttons">
            <button class="mb-1 btn-secondary" onclick="cancelSelection()">annulla</button>
            <button class="mt-1 btn-primary" onclick="confirmSelection()">fatto</button>
        </div>
    </body>
</html>
