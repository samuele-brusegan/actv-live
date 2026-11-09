<?php
// Dati fermata dai parametri URL (condivisi anche dai link interni dell'app)
$stopName = trim((string) ($_GET['name'] ?? ''));
$stopIdParam = trim((string) ($_GET['id'] ?? ''));

// Recupera coordinate dal feed GTFS per i dati strutturati (primo id valido)
$stopGeo = null;
$stopsFile = BASE_PATH . '/data/gtfs/stops.txt';
if ($stopIdParam !== '' && is_file($stopsFile)) {
    $wanted = array_filter(explode('-', $stopIdParam));
    $fh = fopen($stopsFile, 'r');
    $header = $fh ? fgetcsv($fh) : false;
    if ($header) {
        $iId = array_search('stop_id', $header);
        $iName = array_search('stop_name', $header);
        $iLat = array_search('stop_lat', $header);
        $iLon = array_search('stop_lon', $header);
        while (($row = fgetcsv($fh)) !== false) {
            if (in_array((string) ($row[$iId] ?? ''), $wanted, true)) {
                $stopGeo = [
                    'name' => $row[$iName] ?? '',
                    'lat' => (float) ($row[$iLat] ?? 0),
                    'lon' => (float) ($row[$iLon] ?? 0),
                ];
                break;
            }
        }
    }
    if ($fh) fclose($fh);
}

if ($stopName === '' && $stopGeo) {
    $stopName = $stopGeo['name'];
}

$SEO = $stopName !== ''
    ? ['title' => t('meta.stop.title', ['name' => $stopName]), 'desc' => t('meta.stop.desc', ['name' => $stopName])]
    : ['title' => t('meta.stop.fallbackTitle'), 'desc' => t('meta.stop.fallbackDesc')];

if ($stopGeo && $stopName !== '') {
    $SEO['jsonld'] = [
        '@context' => 'https://schema.org',
        '@type' => 'BusStop',
        'name' => $stopName,
        'geo' => ['@type' => 'GeoCoordinates', 'latitude' => $stopGeo['lat'], 'longitude' => $stopGeo['lon']],
    ];
}
?>
<!DOCTYPE html>
<html lang="<?= I18n::lang() ?>">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <?php require COMMON_HTML_HEAD; ?>
        <link rel="stylesheet" href="/css/structure/structure-stop.css">
        <link rel="stylesheet" href="/css/stop.css">
        <link rel="stylesheet" href="/css/home.css">
        <script src="/js/tour.js"></script>
        <script src="/js/notifications.js"></script>
        <script src="/js/widget.js"></script>
        <script src="/js/delayHistory.js"></script>
        <script src="/js/stop.js"></script>
    </head>
    <body>

        <!-- Header -->
        <div class="header-green">
            <div style="height: 20px;">
                <a href="javascript:history.back()" style="color: white; text-decoration: none; font-size: 24px;" aria-label="<?= te('ui.back') ?>">
                    <?= getIcon('arrow_back', 24) ?>
                </a>
            </div>
            <h1 class="header-title" id="station-name"><?= $stopName !== '' ? htmlspecialchars($stopName) : te('stop.loading') ?></h1>
            <div class="header-subtitle" id="station-id"></div>
            <div class="header-actions">
                <button class="favorite-button" id="favorite-btn" onclick="toggleFavorite()" title="<?= te('stop.fav') ?>" aria-label="<?= te('stop.fav') ?>">
                    <?= getIcon('star', 24) ?>
                </button>
                <button class="notify-button" id="notify-btn" onclick="toggleStopNotifications()" title="<?= te('stop.notify') ?>" aria-label="<?= te('stop.notify') ?>">
                    <?= getIcon('notifications', 24) ?>
                </button>
                <button class="share-button" id="share-btn" onclick="shareWidget()" title="<?= te('stop.share') ?>" aria-label="<?= te('stop.share') ?>">
                    <?= getIcon('share', 24) ?>
                </button>
            </div>
        </div>
        

        <!-- Notification Settings Panel -->
        <div id="notification-panel" class="notification-panel" style="display: none;">
            <div class="notif-panel-header">
                <span><?= te('stop.notifTitle') ?></span>
                <button class="notif-panel-close" onclick="closeNotificationPanel()">&times;</button>
            </div>
            <div class="notif-panel-body">
                <div class="notif-setting">
                    <label for="delay-threshold"><?= te('stop.notifThreshold') ?></label>
                    <div class="threshold-selector">
                        <button class="threshold-btn" onclick="setThreshold(3)">3 min</button>
                        <button class="threshold-btn" onclick="setThreshold(5)">5 min</button>
                        <button class="threshold-btn" onclick="setThreshold(10)">10 min</button>
                        <button class="threshold-btn" onclick="setThreshold(15)">15 min</button>
                    </div>
                </div>
                <p class="notif-info"><?= te('stop.notifInfo') ?></p>
            </div>
        </div>

        <!-- Contenuto Principale -->
        <div class="main-content pb-5">
            <div class="parent-wrapper">
                <div id="filter-container"></div>
            </div>

            <!-- Bacheca avvisi (popolata via JS, nascosta se vuota) -->
            <div id="noticeboard"></div>

            <div class="section-title"><?= te('stop.next') ?></div>

            <div id="loading"><?= te('stop.loadingPassages') ?></div>

            <div id="passages-list">
                <!-- Popolato via JS -->
            </div>

            <div class="text-center mt-4">
                <button class="btn btn-outline-secondary rounded-pill px-4 py-2" onclick="location.reload()">
                    <?= te('stop.refresh') ?>
                </button>
            </div>

        </div>
    </body>
</html>
