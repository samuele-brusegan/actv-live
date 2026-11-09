<!DOCTYPE html>
<html lang="<?= I18n::lang() ?>">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <!-- CSS di Leaflet -->
        <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
        <?php
        $SEO = ['title' => t('meta.linesMap.title'), 'desc' => t('meta.linesMap.desc')];
        require COMMON_HTML_HEAD;
        ?>
        <link rel="stylesheet" href="/css/linesMap.css">

        <!-- JavaScript di Leaflet -->
        <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
        <script src="/js/mapRouteLanes.js"></script>
        <script src="/js/linesMap.js"></script>
    </head>

    <body id="lines-map-page">

        <!-- Header -->
        <div class="header-green">
            <a href="javascript:history.back()" class="back-button" aria-label="<?= te('ui.back') ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-arrow-left" viewBox="0 0 16 16">
                    <path fill-rule="evenodd" d="M15 8a.5.5 0 0 0-.5-.5H2.707l3.147-3.146a.5.5 0 1 0-.708-.708l-4 4a.5.5 0 0 0 0 .708l4 4a.5.5 0 0 0 .708-.708L2.707 8.5H14.5A.5.5 0 0 0 15 8z" />
                </svg>
            </a>
            <h1 class="header-title"><?= te('linesMap.title') ?></h1>
            <div style="width: 24px;"></div> <!-- Spacer for centering -->
        </div>

        <div id="lines-toolbar">
            <div class="lines-toolbar-row">
            <div id="lines-service-switch" class="service-switch" role="group" aria-label="<?= te('linesMap.shown') ?>">
                <button type="button" class="service-switch-btn" data-service="automobilistico" aria-label="<?= te('ui.buses') ?>" title="<?= te('ui.buses') ?>" aria-pressed="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="5" y="3" width="14" height="16" rx="3"/><path d="M5 11h14M9 3v8M7 19v2m10-2v2M8 15h1m6 0h1"/></svg></button>
                <button type="button" class="service-switch-btn" data-service="navigation" aria-label="<?= te('ui.vaporetti') ?>" title="<?= te('ui.vaporetti') ?>" aria-pressed="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 13V7h14v6M9 7V4h6v3M3 14l9-3 9 3-3 5H6zM2 21q2-2 4 0t4 0 4 0 4 0 4 0"/></svg></button>
            </div>
                <p id="lines-filter-summary"><?= te('linesMap.allLines') ?></p>
                <button id="btn-lines-filters" class="lines-control" type="button" aria-controls="lines-filters-panel" aria-expanded="false">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/><circle cx="9" cy="6" r="2" fill="var(--color-white)"/><circle cx="15" cy="12" r="2" fill="var(--color-white)"/><circle cx="9" cy="18" r="2" fill="var(--color-white)"/></svg>
                    <span><?= te('linesMap.filtersBtn') ?></span><span id="lines-filter-count" hidden></span>
                </button>
            </div>
            <section id="lines-filters-panel" class="lines-filters-panel" aria-labelledby="lines-filters-title" hidden>
                <div class="lines-panel-heading"><h2 id="lines-filters-title"><?= te('linesMap.filters') ?></h2><button id="btn-close-lines-filters" type="button" class="lines-control" aria-label="<?= te('ui.closeFilters') ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="m6 6 12 12M6 18 18 6"/></svg></button></div>
                <form id="lines-filter-form">
                    <label for="lines-filter-mode"><?= te('linesMap.searchBy') ?></label>
                    <select id="lines-filter-mode"><option value="line"><?= te('linesMap.byLine') ?></option><option value="trips"><?= te('linesMap.byTrip') ?></option></select>
                    <label id="lines-query-label" for="lines-filter-query"><?= te('linesMap.byLines') ?></label>
                    <input id="lines-filter-query" type="text" placeholder="5E, 21" autocomplete="off" aria-describedby="lines-query-help">
                    <p id="lines-query-help"><?= te('linesMap.queryHelp') ?></p>
                    <label class="lines-stops-toggle" for="lines-show-stops"><span><?= te('linesMap.showStops') ?></span><input id="lines-show-stops" type="checkbox" role="switch"></label>
                    <div class="lines-filter-actions"><button id="btn-reset-lines-filters" type="button" class="lines-control"><?= te('linesMap.reset') ?></button><button type="submit" class="lines-control primary"><?= te('linesMap.apply') ?></button></div>
                </form>
            </section>
        </div>
        <div id="lines-map-stage">
            <div id="loading" class="loading-overlay" role="status"><div class="spinner"></div><div><?= te('lineSchedule.loading') ?></div></div>
            <div id="map"></div>
        </div>
        <div id="lines-map-status" role="status" aria-live="polite"></div>
    </body>
</html>