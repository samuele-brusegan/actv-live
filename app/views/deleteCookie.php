<!doctype html>
<html lang="<?= I18n::lang() ?>">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <?php
    $SEO = ['title' => t('meta.privacy.title'), 'desc' => t('meta.privacy.desc')];
    require COMMON_HTML_HEAD;
    ?>
    <link rel="stylesheet" href="/css/privacy.css">
</head>
<body>
    <header class="header-green compact">
        <a href="/" class="back-button" aria-label="<?= te('ui.backHome') ?>">←</a>
        <h1 class="header-title"><?= te('privacy.title') ?></h1>
        <p class="header-subtitle"><?= t('privacy.subtitle') ?></p>
    </header>
    <main class="privacy-page">

        <section class="privacy-card">
            <h2><?= te('privacy.local') ?></h2>
            <p><?= te('privacy.localP') ?></p>
            <ul class="privacy-list">
                <li><strong>favorite_stops</strong> — <?= te('privacy.liFav') ?></li>
                <li><strong>recent_stops</strong> — <?= te('privacy.liRecent') ?></li>
                <li><strong>route_origin / route_destination / route_*</strong> — <?= te('privacy.liRoute') ?></li>
                <li><strong>theme</strong> — <?= te('privacy.liTheme') ?></li>
                <li><strong>home_tutorial_seen</strong> — <?= te('privacy.liTutorial') ?></li>
                <li><strong>actv-*-service</strong> — <?= te('privacy.liService') ?></li>
                <li><strong>cookie_consent_accepted</strong> — <?= te('privacy.liConsent') ?></li>
            </ul>
            <div class="privacy-actions">
                <button class="pv-btn pv-btn-danger" id="pv-clear-local"><?= te('privacy.delLocal') ?></button>
                <button class="pv-btn pv-btn-ghost" id="pv-revoke-consent"><?= te('privacy.revokeConsent') ?></button>
            </div>
        </section>


        <section class="privacy-card">
            <h2><?= t('privacy.android') ?></h2>
            <p><?= te('privacy.androidP') ?></p>
            <ul class="privacy-list">
                <li><strong><?= te('privacy.liLocalData') ?></strong> — <?= t('privacy.androidLocal') ?></li>
                <li><strong><?= te('privacy.liPos') ?></strong> — <?= t('privacy.androidPos') ?></li>
                <li><strong><?= te('privacy.liNotif') ?></strong> — <?= te('privacy.androidNotif') ?></li>
                <li><strong><?= t('privacy.liNoTrack') ?></strong> — <?= t('privacy.androidNoTrack') ?></li>
                <li><strong><?= te('privacy.liThird') ?></strong> — <?= te('privacy.androidThird') ?></li>
            </ul>
        </section>

        <section class="privacy-card">
            <h2><?= te('privacy.cookies') ?></h2>
            <p><?= t('privacy.cookiesP') ?></p>
            <div class="privacy-actions">
                <button class="pv-btn pv-btn-danger" id="pv-clear-cookies"><?= te('privacy.delCookies') ?></button>
            </div>
            <p class="privacy-status" id="pv-status" role="status"></p>
        </section>

        <section class="privacy-card">
            <h2><?= te('privacy.third') ?></h2>
            <ul class="privacy-list">
                <li><strong>OpenStreetMap / Nominatim</strong> — <?= te('privacy.osm') ?> <a href="https://wiki.osmfoundation.org/wiki/Privacy_Policy" target="_blank" rel="noopener">Privacy OSMF</a></li>
                <li><strong>ACTV S.p.A.</strong> — <?= te('privacy.actv') ?></li>
                <li><strong>Google Analytics</strong> — <?= te('privacy.ga') ?></li>
            </ul>
        </section>

        <section class="privacy-card">
            <h2><?= te('privacy.feedbackSec') ?></h2>
            <p><?= t('privacy.feedbackP1') ?></p>
            <p><?= t('privacy.feedbackP2') ?></p>
        </section>

        <p style="text-align:center;color:#888;font-size:13px;"><?= te('privacy.updated') ?></p>
    </main>

<script>
(function () {
    const status = document.getElementById('pv-status');
    const say = msg => { status.textContent = msg; setTimeout(() => status.textContent = '', 4000); };

    document.getElementById('pv-clear-local').addEventListener('click', async () => {
        const ok = await actvConfirm(<?= json_encode(t('privacy.confirmLocal')) ?>, <?= json_encode(t('privacy.delLocal')) ?>);
        if (!ok) return;
        localStorage.clear();
        sessionStorage.clear();
        say(<?= json_encode(t('privacy.localDeleted')) ?>);
        setTimeout(() => location.reload(), 1200);
    });

    document.getElementById('pv-revoke-consent').addEventListener('click', async () => {
        const ok = await actvConfirm(<?= json_encode(t('privacy.confirmRevoke')) ?>, <?= json_encode(t('privacy.revokeConsent')) ?>);
        if (!ok) return;
        localStorage.removeItem('cookie_consent_accepted');
        say(<?= json_encode(t('privacy.revoked')) ?>);
    });

    document.getElementById('pv-clear-cookies').addEventListener('click', async () => {
        const ok = await actvConfirm(<?= json_encode(t('privacy.confirmCookies')) ?>, <?= json_encode(t('privacy.delCookies')) ?>);
        if (!ok) return;
        try {
            await fetch('/api/delete-cookie').then(r => r.json());
        } catch (e) { /* i cookie lato client vengono comunque rimossi sotto */ }
        document.cookie.split(';').forEach(c => {
            const name = c.split('=')[0].trim();
            document.cookie = name + '=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
        });
        say(<?= json_encode(t('privacy.cookiesDeleted')) ?>);
    });
})();
</script>
</body>
</html>
