<!DOCTYPE html>
<html lang="<?= I18n::lang() ?>">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <?php
        $SEO = ['title' => t('meta.delayStats.title'), 'desc' => t('meta.delayStats.desc')];
        require COMMON_HTML_HEAD;
        ?>
        <link rel="stylesheet" href="/css/delayStats.css">
        <script src="/js/delayHistory.js"></script>
    </head>
    <body>
        <!-- Header -->
        <div class="header-green">
            <div style="height: 20px;">
                <a href="/" style="color: white; text-decoration: none; font-size: 24px;">
                    <?= getIcon('arrow_back', 24) ?>
                </a>
            </div>
            <h1 class="header-title"><?= te('delay.title') ?></h1>
            <div class="header-subtitle"><?= te('delay.subtitle') ?></div>
        </div>

        <div class="main-content pb-5">

            <!-- Overview Cards -->
            <div class="stats-overview" id="stats-overview">
                <div class="stat-card">
                    <div class="stat-value" id="stat-total">-</div>
                    <div class="stat-label"><?= te('delay.total') ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" id="stat-avg">-</div>
                    <div class="stat-label"><?= te('delay.avg') ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" id="stat-max">-</div>
                    <div class="stat-label"><?= te('delay.max') ?></div>
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="quick-stats" id="quick-stats">
                <div class="quick-stat">
                    <span class="quick-label"><?= te('delay.worstLine') ?></span>
                    <span class="quick-value" id="stat-worst-line">-</span>
                </div>
                <div class="quick-stat">
                    <span class="quick-label"><?= te('delay.bestHour') ?></span>
                    <span class="quick-value" id="stat-best-hour">-</span>
                </div>
                <div class="quick-stat">
                    <span class="quick-label"><?= te('delay.worstHour') ?></span>
                    <span class="quick-value" id="stat-worst-hour">-</span>
                </div>
            </div>

            <!-- By Line -->
            <div class="section-title"><?= te('delay.byLine') ?></div>
            <div id="line-stats">
                <p class="text-center text-muted" id="line-empty"><?= te('delay.noDataLine') ?></p>
            </div>

            <!-- By Hour Chart -->
            <div class="section-title"><?= te('delay.byHour') ?></div>
            <div class="chart-container" id="hour-chart">
                <p class="text-center text-muted" id="hour-empty"><?= te('delay.noData') ?></p>
            </div>

            <!-- Actions -->
            <div class="stats-actions">
                <button class="btn btn-outline-secondary rounded-pill px-4 py-2" onclick="location.reload()">
                    <?= te('stop.refresh') ?>
                </button>
                <button class="btn btn-outline-danger rounded-pill px-4 py-2" onclick="confirmClear()">
                    <?= te('delay.clear') ?>
                </button>
            </div>

        </div>

        <script>
            function renderStats() {
                var overall = getOverallStats();
                document.getElementById('stat-total').textContent = overall.totalRecords;
                document.getElementById('stat-avg').textContent = overall.avgDelay + ' min';
                document.getElementById('stat-max').textContent = overall.maxDelay + ' min';
                document.getElementById('stat-worst-line').textContent = overall.worstLine;
                document.getElementById('stat-best-hour').textContent = overall.bestHour;
                document.getElementById('stat-worst-hour').textContent = overall.worstHour;

                renderLineStats();
                renderHourChart();
            }

            function renderLineStats() {
                var stats = getStatsByLine();
                var container = document.getElementById('line-stats');
                var empty = document.getElementById('line-empty');

                if (stats.length === 0) {
                    empty.style.display = 'block';
                    return;
                }

                empty.style.display = 'none';
                var maxAvg = stats[0].avgDelay;

                container.innerHTML = stats.map(function(s) {
                    var pct = maxAvg > 0 ? Math.round((s.avgDelay / maxAvg) * 100) : 0;
                    var barColor = s.avgDelay > 10 ? '#E30613' : s.avgDelay > 5 ? '#FF9800' : '#009E61';

                    return '<div class="line-stat-row">' +
                        '<div class="line-stat-name">' + s.line + '</div>' +
                        '<div class="line-stat-bar-container">' +
                            '<div class="line-stat-bar" style="width:' + pct + '%;background:' + barColor + '"></div>' +
                        '</div>' +
                        '<div class="line-stat-value">' + s.avgDelay + ' min</div>' +
                        '<div class="line-stat-count">(' + s.count + ')</div>' +
                    '</div>';
                }).join('');
            }

            function renderHourChart() {
                var stats = getStatsByHour();
                var container = document.getElementById('hour-chart');
                var empty = document.getElementById('hour-empty');
                var hasData = stats.some(function(h) { return h.count > 0; });

                if (!hasData) {
                    empty.style.display = 'block';
                    return;
                }

                empty.style.display = 'none';
                var maxAvg = Math.max.apply(null, stats.map(function(h) { return h.avgDelay; }));
                if (maxAvg === 0) maxAvg = 1;

                var barsHtml = stats.map(function(h) {
                    var pct = Math.round((h.avgDelay / maxAvg) * 100);
                    var barColor = h.avgDelay > 10 ? '#E30613' : h.avgDelay > 5 ? '#FF9800' : '#009E61';
                    var label = h.hour < 10 ? '0' + h.hour : String(h.hour);

                    return '<div class="hour-bar-wrapper">' +
                        '<div class="hour-bar" style="height:' + pct + '%;background:' + (h.count > 0 ? barColor : '#ddd') + '" title="' + label + ':00 - ' + h.avgDelay + ' min"></div>' +
                        '<div class="hour-label">' + label + '</div>' +
                    '</div>';
                }).join('');

                container.innerHTML = '<div class="hour-chart-bars">' + barsHtml + '</div>';
            }

            async function confirmClear() {
                const confirmed = await actvConfirm(<?= json_encode(t('delay.clearConfirm')) ?>, <?= json_encode(t('delay.clearTitle')) ?>);
                if (confirmed) {
                    clearDelayHistory();
                    renderStats();
                }
            }

            document.addEventListener('DOMContentLoaded', renderStats);
        </script>
    </body>
</html>
