<!DOCTYPE html>
<html lang="<?= I18n::lang() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Leaflet -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>

    <?php
    $SEO = ['title' => t('meta.liveMap.title'), 'desc' => t('meta.liveMap.desc')];
    require COMMON_HTML_HEAD;
    ?>
    <link rel="stylesheet" href="/css/liveBusMap.css">
    <script src="/js/tour.js"></script>
</head>
<body>

    <!-- Header -->
    <div class="header-green">
        <a href="javascript:history.back()" class="back-button" aria-label="<?= te('ui.back') ?>">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 16 16">
                <path fill-rule="evenodd" d="M15 8a.5.5 0 0 0-.5-.5H2.707l3.147-3.146a.5.5 0 1 0-.708-.708l-4 4a.5.5 0 0 0 0 .708l4 4a.5.5 0 0 0 .708-.708L2.707 8.5H14.5A.5.5 0 0 0 15 8z" />
            </svg>
        </a>
        <h1 class="header-title"><?= te('liveMap.title') ?></h1>
        <div style="width: 24px;"></div>
    </div>

    <div id="map-toolbar">
        <div id="filters-bar">
            <div id="service-switch" class="service-switch" role="group" aria-label="<?= te('linesMap.shown') ?>">
                <button type="button" class="service-switch-btn" data-service="automobilistico" aria-label="<?= te('ui.buses') ?>" title="<?= te('ui.buses') ?>" aria-pressed="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="5" y="3" width="14" height="16" rx="3"/><path d="M5 11h14M9 3v8M7 19v2m10-2v2M8 15h1m6 0h1"/></svg></button>
                <button type="button" class="service-switch-btn" data-service="navigation" aria-label="<?= te('ui.vaporetti') ?>" title="<?= te('ui.vaporetti') ?>" aria-pressed="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 13V7h14v6M9 7V4h6v3M3 14l9-3 9 3-3 5H6zM2 21q2-2 4 0t4 0 4 0 4 0 4 0"/></svg></button>
            </div>
            <div class="map-search">
                <span class="search-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 4 4"/></svg></span>
                <input type="search" id="filter-input" placeholder="<?= te('liveMap.searchPh') ?>" aria-label="<?= te('liveMap.filterAria') ?>" aria-describedby="search-help" autocomplete="off">
                <button id="filter-clear" class="filter-btn search-action" type="button" aria-label="<?= te('liveMap.clear') ?>" title="<?= te('liveMap.clear') ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 6 12 12M6 18 18 6"/></svg></button>
                <button id="btn-search-history" class="filter-btn search-action" type="button" aria-label="<?= te('liveMap.recentSearches') ?>" title="<?= te('liveMap.recentSearches') ?>" aria-controls="search-history-panel" aria-expanded="false"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 10a9 9 0 1 1 2 8M3 4v6h6m3-3v5l3 2"/></svg></button>
            </div>
            <div class="map-actions">
                <button id="btn-my-position" class="filter-btn" type="button" aria-label="<?= te('liveMap.myPos') ?>" title="<?= te('liveMap.myPos') ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="7"/><circle cx="12" cy="12" r="2"/><path d="M12 2v3m0 14v3M2 12h3m14 0h3"/></svg></button>
                <button id="btn-refresh" class="filter-btn" type="button" aria-label="<?= te('liveMap.refresh') ?>" title="<?= te('liveMap.refresh') ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 7v5h-5M4 17v-5h5M6 6a8 8 0 0 1 14 6M4 12a8 8 0 0 0 14 6"/></svg></button>
                <button id="btn-map-settings" class="filter-btn" type="button" aria-label="<?= te('liveMap.settings') ?>" title="<?= te('liveMap.settings') ?>" aria-controls="map-settings-panel" aria-expanded="false"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/><circle cx="9" cy="6" r="2" fill="var(--color-white)"/><circle cx="15" cy="12" r="2" fill="var(--color-white)"/><circle cx="9" cy="18" r="2" fill="var(--color-white)"/></svg></button>
            </div>
        </div>
        <div id="search-history-panel" class="map-floating-panel hidden" role="region" aria-label="<?= te('liveMap.recentSearches') ?>"></div>
        <section id="map-settings-panel" class="map-floating-panel hidden" aria-labelledby="map-settings-title">
            <div class="map-panel-heading"><h2 id="map-settings-title"><?= te('liveMap.settings') ?></h2><button type="button" class="filter-btn" data-close-panel aria-label="<?= te('liveMap.closeSettings') ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 6 12 12M6 18 18 6"/></svg></button></div>
            <fieldset class="search-fields"><legend><?= te('liveMap.searchFields') ?></legend>
                <label class="map-switch-row" for="search-field-line"><span><?= te('linesMap.byLine') ?></span><input id="search-field-line" type="checkbox" role="switch" data-search-field="line" checked></label>
                <label class="map-switch-row" for="search-field-destination"><span><?= te('liveMap.destination') ?></span><input id="search-field-destination" type="checkbox" role="switch" data-search-field="destination" checked></label>
                <label class="map-switch-row" for="search-field-tripId"><span><?= te('linesMap.byTrip') ?> <small>tripId</small></span><input id="search-field-tripId" type="checkbox" role="switch" data-search-field="tripId" checked></label>
            </fieldset>
            <p id="search-help" class="map-panel-help"><?= te('liveMap.searchHelp') ?></p>
            <p id="search-fields-warning" class="map-panel-help hidden" role="status"><?= te('liveMap.fieldsWarning') ?></p>
            <label class="map-switch-row nd-settings" for="btn-toggle-nd"><span><?= te('liveMap.noLine') ?> <small><?= te('liveMap.noLineSub') ?></small></span><input id="btn-toggle-nd" type="checkbox" role="switch"></label>
        </section>
    </div>

    <!-- Map -->
    <div id="map"></div>

    <!-- Status bar -->
    <div id="status-bar">
        <div id="bus-counter">
            <span class="spinner-small"></span>
            <span id="counter-text"><?= te('ui.loading') ?></span>
        </div>
        <div id="last-update"></div>
    </div>

    <aside id="bus-details-panel" class="bus-details-panel hidden" aria-live="polite">
        <button id="bus-details-close" class="bus-details-close" type="button" aria-label="<?= te('liveMap.closeDetails') ?>">×</button>
        <div id="bus-details-content"></div>
    </aside>

    <script src="/js/liveBusMap.js"></script>
</body>
</html>
