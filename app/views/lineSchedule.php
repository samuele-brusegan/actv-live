<!DOCTYPE html>
<html lang="<?= I18n::lang() ?>">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <?php
        $SEO = ['title' => t('meta.lineSchedule.title'), 'desc' => t('meta.lineSchedule.desc')];
        require COMMON_HTML_HEAD;
        ?>
        <link rel="stylesheet" href="/css/lineSchedule.css">
        <script src="/js/tour.js"></script>
        <script defer src="/js/lineSchedule.js"></script>
    </head>
    <body class="schedule-page">
        <header class="header-green schedule-brand">
            <div class="schedule-header">
                <a href="/" class="schedule-back" aria-label="<?= te('ui.backHome') ?>">&larr;</a>
                <div><h1><?= te('lineSchedule.title') ?></h1><p><?= te('lineSchedule.subtitle') ?></p></div>
            </div>
        </header>
        <main class="schedule-main">
            <section class="schedule-controls" aria-label="<?= te('lineSchedule.controlsLabel') ?>">
                <div class="service-switch" role="group" aria-label="<?= te('lineSchedule.serviceLabel') ?>">
                    <button type="button" class="service-switch-btn" data-service="automobilistico" aria-label="<?= te('ui.buses') ?>" title="<?= te('ui.buses') ?>" aria-pressed="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="5" y="3" width="14" height="16" rx="3"/><path d="M5 11h14M9 3v8M7 19v2m10-2v2M8 15h1m6 0h1"/></svg></button>
                    <button type="button" class="service-switch-btn" data-service="navigation" aria-label="<?= te('ui.vaporetti') ?>" title="<?= te('ui.vaporetti') ?>" aria-pressed="false"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 13V7h14v6M9 7V4h6v3M3 14l9-3 9 3-3 5H6zM2 21q2-2 4 0t4 0 4 0 4 0 4 0 4 0"/></svg></button>
                </div>
                <div class="line-picker">
                    <div><label for="line-search"><?= te('lineSchedule.searchLabel') ?></label><input type="search" id="line-search" class="form-control" placeholder="<?= te('lineSchedule.searchPh') ?>" autocomplete="off" aria-controls="line-input"></div>
                    <div><label for="line-input"><?= te('lineSchedule.lineLabel') ?></label><select id="line-input" class="form-select" disabled><option value=""><?= te('lineSchedule.loading') ?></option></select></div>
                </div>
            </section>
            <div id="status" class="alert alert-info d-none" role="status" aria-live="polite"></div>
            <div class="schedule-workspace">
                <aside id="variants" aria-label="<?= te('lineSchedule.variantsAria') ?>"></aside>
                <section id="variant-detail" aria-label="<?= te('lineSchedule.fullAria') ?>" aria-live="polite">
                    <div class="schedule-empty"><h2><?= te('lineSchedule.emptyTitle') ?></h2><p><?= te('lineSchedule.emptySub') ?></p></div>
                </section>
            </div>
            <div id="feed-meta" class="schedule-feed"></div>
        </main>
    </body>
</html>
