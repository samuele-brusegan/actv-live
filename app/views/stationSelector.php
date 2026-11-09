<!DOCTYPE html>
<html lang="<?= I18n::lang() ?>">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <?php
        $SEO = ['title' => t('meta.stationSelector.title'), 'desc' => t('meta.stationSelector.desc'), 'robots' => 'noindex,follow'];
        require COMMON_HTML_HEAD;
        ?>

        <link rel="stylesheet" href="/css/structure/structure-stopList.css">
        <link rel="stylesheet" href="/css/structure/structure-stationSelector.css">
        <link rel="stylesheet" href="/css/stationSelector.css">
        
        <!-- StopCard Component -->
        <script src="/components/StopCard.js"></script>
        <script src="/js/stationSelector.js"></script>
    </head>
    <body class="<?= (($_GET['embedded'] ?? '') === '1') ? 'embedded-picker' : '' ?>">

        <!-- Header -->
        <div class="header-green">
            <div style="height: 20px;">
                <a href="/route-finder" class="back-button">&larr;</a>
            </div>
            <h1 class="header-title">
                <?php
                $type = $_GET['type'] ?? 'origin';
                echo $type === 'origin' ? t('stationSelector.origin') : t('stationSelector.destination');
                ?>
            </h1>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            
            <!-- Search Bar -->
            <div class="search-container">
                <input type="text" id="search-input" class="search-input" placeholder="🔍 <?= te('stops.searchPh') ?>" oninput="filterStops()">
            </div>

            <!-- Recent Section -->
            <div id="recent-section" style="display: none;">
                <div class="picker-section-title"><?= te('stationSelector.recent') ?></div>
                <div id="recent-list"></div>
            </div>

            <!-- Favorites Section -->
            <div id="favorites-section" style="display: none;">
                <div class="picker-section-title"><?= te('stationSelector.favorites') ?></div>
                <div id="favorites-list"></div>
            </div>

            <!-- All Stops Section -->
            <div id="all-stops-section" style="display: none;">
                <div class="section-title"><?= te('stationSelector.all') ?></div>
                <div id="all-stops-list" style="margin-bottom: 10px;"></div>
            </div>

        </div>

        <!-- Action Buttons -->
        <div class="action-buttons">
            <button class="mb-1 btn-secondary" onclick="cancelSelection()"><?= te('stationSelector.cancel') ?></button>
            <button class="mt-1 btn-primary" onclick="confirmSelection()"><?= te('stationSelector.done') ?></button>
        </div>
    </body>
</html>
