<!doctype html>
<html lang="<?= I18n::lang() ?>">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <?php
    $SEO = ['title' => t('meta.credits.title'), 'desc' => t('meta.credits.desc')];
    require COMMON_HTML_HEAD;
    ?>
    <link rel="stylesheet" href="/css/credits.css">
</head>
<body>
    <header class="header-green compact">
        <a href="/" class="back-button" aria-label="<?= te('ui.backHome') ?>">←</a>
        <h1 class="header-title"><?= te('credits.title') ?></h1>
        <p class="header-subtitle"><?= te('credits.subtitle') ?></p>
    </header>
    <main class="credits-page">

        <section class="credits-card">
            <h2><?= te('credits.maps') ?></h2>
            <p><?= t('credits.mapsP1') ?></p>
            <p><?= t('credits.mapsP2') ?></p>
        </section>

        <section class="credits-card">
            <h2><?= te('credits.transport') ?></h2>
            <p><?= t('credits.transportP1') ?></p>
            <p class="credits-disclaimer"><?= t('credits.disclaimer') ?></p>
        </section>

        <section class="credits-card">
            <h2><?= te('credits.libraries') ?></h2>
            <ul class="credits-list">
                <li><a href="https://leafletjs.com" target="_blank" rel="noopener">Leaflet</a> — BSD 2-Clause</li>
                <li><a href="https://github.com/Leaflet/Leaflet.fullscreen" target="_blank" rel="noopener">Leaflet.fullscreen</a> — BSD 2-Clause</li>
                <li><a href="https://getbootstrap.com" target="_blank" rel="noopener">Bootstrap</a> — MIT</li>
                <li><a href="https://popper.js.org" target="_blank" rel="noopener">Popper.js</a> — MIT</li>
                <li><a href="https://axios-http.com" target="_blank" rel="noopener">Axios</a> — MIT</li>
                <li><a href="https://github.com/Choices-js/Choices" target="_blank" rel="noopener">Choices.js</a> — MIT</li>
                <li><a href="https://github.com/griffinmichl/ldrs" target="_blank" rel="noopener">ldrs</a> — MIT</li>
            </ul>
        </section>

        <section class="credits-card">
            <h2><?= te('credits.project') ?></h2>
            <p><?= t('credits.projectP1') ?></p>
        </section>

    </main>
</body>
</html>
