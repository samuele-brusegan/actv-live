<!DOCTYPE html>
<html lang="<?= I18n::lang() ?>">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <?php
        $SEO = ['title' => t('meta.tripFinder.title'), 'desc' => t('meta.tripFinder.desc')];
        require COMMON_HTML_HEAD;
        ?>
        <link rel="stylesheet" href="/css/tripFinder.css">
        <script defer src="/js/tripFinder.js"></script>
    </head>
    <body>
        <header class="header-green">
            <div>
                <a class="back-button" href="/" aria-label="<?= te('ui.backHome') ?>">&larr;</a>
            </div>
            <h1 class="header-title"><?= te('tripFinder.title') ?></h1>
            <p class="header-subtitle"><?= te('tripFinder.subtitle') ?></p>
        </header>

        <main class="trip-finder-content" id="trip-finder">
            <section class="finder-card" aria-labelledby="finder-title">
                <div class="finder-card-heading">
                    <div>
                        <p class="eyebrow"><?= te('tripFinder.busService') ?></p>
                        <h2 id="finder-title"><?= te('tripFinder.findTrip') ?></h2>
                    </div>
                </div>

                <div class="finder-grid">
                    <div class="finder-field">
                        <label for="line-input"><?= te('lineSchedule.lineLabel') ?></label>
                        <select id="line-input" class="form-select" autocomplete="off">
                            <option value=""><?= te('lineSchedule.loading') ?></option>
                        </select>
                        <small class="field-help"><?= te('tripFinder.lineHelp') ?></small>
                    </div>
                    <div class="finder-field">
                        <label for="date-input"><?= te('tripFinder.dateLabel') ?></label>
                        <input id="date-input" class="form-control" type="date" value="<?= date('Y-m-d') ?>">
                    </div>
                </div>

                <div class="finder-field">
                    <label for="origin-input"><?= te('routeFinder.departureCap') ?></label>
                    <select id="origin-input" class="form-select" disabled>
                        <option value=""><?= te('tripFinder.pickLineFirst') ?></option>
                    </select>
                </div>

                <div class="direction-arrow" aria-hidden="true">↓</div>

                <div class="finder-field">
                    <label for="destination-input"><?= te('routeFinder.destinationCap') ?></label>
                    <select id="destination-input" class="form-select" disabled>
                        <option value=""><?= te('tripFinder.pickOriginFirst') ?></option>
                    </select>
                </div>

                <div id="status" class="finder-status" role="status" aria-live="polite"></div>
            </section>

            <section class="finder-card trips-card" aria-labelledby="trips-title">
                <div class="finder-card-heading">
                    <div>
                        <p class="eyebrow"><?= te('tripFinder.resultEyebrow') ?></p>
                        <h2 id="trips-title"><?= te('tripFinder.selectLeg') ?></h2>
                    </div>
                    <span id="trip-count" class="trip-count" hidden></span>
                </div>
                <div id="trips-list" class="trips-list">
                    <p class="empty-state"><?= te('tripFinder.emptyState') ?></p>
                </div>
                <div id="trips-navigation" class="trips-navigation" hidden>
                    <div class="pagination-row">
                        <button id="previous-page" class="pagination-button" type="button" aria-label="<?= te('tripFinder.prevPage') ?>">‹</button>
                        <span id="page-label" class="page-label"></span>
                        <button id="next-page" class="pagination-button" type="button" aria-label="<?= te('tripFinder.nextPage') ?>">›</button>
                    </div>
                    <div class="jump-row">
                        <div class="jump-control">
                            <label for="page-input"><?= te('tripFinder.goToPage') ?></label>
                            <div class="jump-input-group">
                                <input id="page-input" class="jump-input" type="number" min="1" step="1" inputmode="numeric" aria-label="<?= te('tripFinder.pageNum') ?>">
                                <button id="page-jump-button" class="jump-button" type="button"><?= te('tripFinder.go') ?></button>
                            </div>
                        </div>
                        <span class="jump-or"><?= te('tripFinder.or') ?></span>
                        <div class="jump-control">
                            <label for="time-input"><?= te('tripFinder.goToTime') ?></label>
                            <div class="jump-input-group">
                                <input id="time-input" class="jump-input time-input" type="time" aria-label="<?= te('tripFinder.departureTime') ?>">
                                <button id="time-jump-button" class="jump-button" type="button"><?= te('tripFinder.go') ?></button>
                            </div>
                        </div>
                    </div>
                </div>
                <button id="open-trip-button" class="btn-primary open-trip-button" type="button" disabled>
                    <?= te('tripFinder.openTrip') ?>
                </button>
            </section>
        </main>
    </body>
</html>
