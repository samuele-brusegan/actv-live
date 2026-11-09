<!DOCTYPE html>
<html lang="<?= I18n::lang() ?>">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css">
        <?php
        $SEO = ['title' => t('meta.routeResults.title'), 'desc' => t('meta.routeResults.desc'), 'robots' => 'noindex,follow'];
        require COMMON_HTML_HEAD;
        ?>
        <link rel="stylesheet" href="/css/structure/structure-routeResults.css">
        <link rel="stylesheet" href="/css/routeResults.css">
        <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
        <script src="/js/routeResults.js"></script>
    </head>
    <body>

        <!-- Header -->
        <div class="header-green">
            <div style="height: 20px;">
                <a href="/route-finder" class="back-button">&larr;</a>
            </div>
            <h1 class="header-title"><?= t('routeResults.title') ?></h1>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            
            <!-- Route Summary -->
            <div class="route-summary">
                <div class="route-stops">
                    <div class="route-stop">
                        <div class="route-stop-label"><?= te('routeResults.from') ?></div>
                        <div class="route-stop-name" id="origin-name">-</div>
                    </div>
                    <div class="route-arrow">&rarr;</div>
                    <div class="route-stop">
                        <div class="route-stop-label"><?= te('routeResults.to') ?></div>
                        <div class="route-stop-name" id="destination-name">-</div>
                    </div>
                </div>
                <div class="route-datetime" id="datetime-info">-</div>
            </div>

            <!-- Loading State -->
            <div id="loading" class="loading">
                <div class="spinner"></div>
                <div><?= te('routeResults.searching') ?></div>
            </div>

            <!-- Routes List -->
            <div id="routes-container" style="display: none;">
                <!-- Direction Tabs (solo viaggio di ritorno) -->
                <div id="direction-tabs" class="direction-tabs" style="display: none;">
                    <button class="direction-tab active" id="tab-andata" onclick="switchDirection('andata')"><?= te('routeResults.outbound') ?></button>
                    <button class="direction-tab" id="tab-ritorno" onclick="switchDirection('ritorno')"><?= te('routeResults.return') ?></button>
                </div>
                <div class="section-title-row">
                    <div class="section-title"><?= te('routeResults.available') ?></div>
                    <button class="btn-compare-toggle" id="btn-compare-toggle" onclick="toggleCompareMode()"><?= te('routeResults.compare') ?></button>
                </div>
                <div id="routes-list"></div>
                <div id="compare-bar" class="compare-bar" style="display: none;">
                    <span id="compare-count"><?= te('routeResults.selected0') ?></span>
                    <button class="btn-compare" onclick="openCompareModal()"><?= te('routeResults.compareBtn') ?></button>
                </div>
            </div>

            <!-- No Routes -->
            <div id="no-routes" style="display: none;">
                <div class="no-routes">
                    <div class="no-routes-icon">&#x1F6AB;</div>
                    <div class="no-routes-text"><?= te('routeResults.none') ?></div>
                    <div class="no-routes-subtext"><?= te('routeResults.noneSub') ?></div>
                    <button class="btn-back" onclick="window.location.href='/route-finder'"><?= te('ui.back') ?></button>
                </div>
            </div>

        </div>

        <!-- Modal Dettagli -->
        <div id="details-modal" class="modal-overlay" onclick="closeModal(event)">
            <div class="modal-content" onclick="event.stopPropagation()">
                <div class="modal-header">
                    <div class="modal-title"><?= te('routeResults.details') ?></div>
                    <button class="modal-close" onclick="closeModal()">&times;</button>
                </div>
                <div id="modal-body"></div>
                <button class="btn-back" style="margin-top: 1rem;" onclick="closeModal()"><?= te('routeFinder.close') ?></button>
            </div>
        </div>

        <!-- Modal Confronto Percorsi -->
        <div id="compare-modal" class="modal-overlay" onclick="closeCompareModal(event)">
            <div class="modal-content compare-modal-content" onclick="event.stopPropagation()">
                <div class="modal-header">
                    <div class="modal-title"><?= te('routeResults.compareTitle') ?></div>
                    <button class="modal-close" onclick="closeCompareModal()">&times;</button>
                </div>
                <div id="compare-map-shell" class="compare-map-shell">
                    <div id="compare-map" class="compare-map" aria-label="<?= te('routeResults.compareMapAria') ?>"></div>
                    <button type="button" class="compare-map-expand" onclick="toggleComparisonMap()" aria-label="<?= te('routeResults.expandMap') ?>">⛶</button>
                </div>
                <div id="compare-map-status" class="compare-map-status"><?= te('routeResults.loadingMap') ?></div>
                <div id="compare-body"></div>
            </div>
        </div>
    </body>
</html>
