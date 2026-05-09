<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ACTV Live Widget</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #F5F5F5;
            color: #333;
        }
        .widget {
            max-width: 400px;
            margin: 0 auto;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .widget-header {
            background: #009E61;
            color: #fff;
            padding: 12px 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .widget-header h3 {
            font-size: 14px;
            font-weight: 700;
        }
        .widget-header .widget-stop-id {
            font-size: 11px;
            opacity: 0.8;
        }
        .widget-brand {
            font-size: 10px;
            opacity: 0.7;
            text-decoration: none;
            color: #fff;
        }
        .widget-body {
            padding: 0;
        }
        .widget-loading {
            text-align: center;
            padding: 2rem;
            color: #999;
        }
        .widget-loading .spinner {
            border: 3px solid #eee;
            border-top: 3px solid #009E61;
            border-radius: 50%;
            width: 28px;
            height: 28px;
            animation: spin 1s linear infinite;
            margin: 0 auto 8px;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        .widget-passage {
            display: flex;
            align-items: center;
            padding: 10px 16px;
            border-bottom: 1px solid #f0f0f0;
            gap: 12px;
        }
        .widget-passage:last-child { border-bottom: none; }
        .widget-badge {
            font-weight: 700;
            font-size: 13px;
            color: #fff;
            padding: 4px 10px;
            border-radius: 6px;
            min-width: 36px;
            text-align: center;
            flex-shrink: 0;
        }
        .widget-badge.badge-red { background: #E30613; }
        .widget-badge.badge-blue { background: #0152BB; }
        .widget-badge.badge-night { background: #002B57; }
        .widget-dest {
            flex: 1;
            font-size: 13px;
            color: #555;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .widget-time {
            font-weight: 700;
            font-size: 14px;
            color: #333;
            flex-shrink: 0;
        }
        .widget-time.realtime { color: #009E61; }
        .widget-empty {
            text-align: center;
            padding: 1.5rem;
            color: #999;
            font-size: 13px;
        }
        .widget-footer {
            padding: 8px 16px;
            text-align: center;
            border-top: 1px solid #f0f0f0;
        }
        .widget-footer a {
            font-size: 11px;
            color: #009E61;
            text-decoration: none;
            font-weight: 600;
        }
        .widget-footer a:hover { text-decoration: underline; }
        .widget-error {
            text-align: center;
            padding: 1.5rem;
            color: #E30613;
            font-size: 13px;
        }
    </style>
</head>
<body>
    <div class="widget" id="widget">
        <div class="widget-header">
            <div>
                <h3 id="widget-stop-name">Caricamento...</h3>
                <div class="widget-stop-id" id="widget-stop-id"></div>
            </div>
            <a class="widget-brand" href="/" target="_blank">ACTV Live</a>
        </div>
        <div class="widget-body" id="widget-body">
            <div class="widget-loading">
                <div class="spinner"></div>
                <div>Caricamento passaggi...</div>
            </div>
        </div>
        <div class="widget-footer">
            <a id="widget-link" href="/" target="_blank">Apri su ACTV Live &rarr;</a>
        </div>
    </div>

    <script>
        const params = new URLSearchParams(window.location.search);
        const stopId = params.get('stop') || '';
        const stopName = params.get('name') || '';
        const maxItems = parseInt(params.get('max') || '8', 10);
        const theme = params.get('theme') || 'light';

        if (theme === 'dark') {
            document.querySelector('.widget').style.background = '#1a1a1a';
            document.querySelector('.widget-header').style.background = '#004d30';
            document.body.style.background = '#1a1a1a';
            document.querySelectorAll('.widget-dest, .widget-time, .widget-empty').forEach(function(el) {
                el.style.color = '#ccc';
            });
        }

        document.getElementById('widget-stop-name').textContent = stopName || 'Fermata ' + stopId;
        document.getElementById('widget-stop-id').textContent = stopId;
        document.getElementById('widget-link').href = '/aut/stops/stop?id=' + encodeURIComponent(stopId) + '&name=' + encodeURIComponent(stopName);

        function getBadgeClass(line) {
            var parts = (line || '').split('_');
            var name = parts[0] || '';
            var tag = parts[1] || '';
            if (name.startsWith('N')) return 'badge-night';
            if (['US', 'UN', 'EN'].indexOf(tag) !== -1) return 'badge-blue';
            return 'badge-red';
        }

        function formatTime(time) {
            if (!time) return '--';
            if (time === 'departure') return 'Ora';
            return time;
        }

        async function loadWidget() {
            if (!stopId) {
                document.getElementById('widget-body').innerHTML =
                    '<div class="widget-error">Parametro stop mancante. Usa ?stop=ID&name=Nome</div>';
                return;
            }

            try {
                var resp = await fetch('https://oraritemporeale.actv.it/aut/backend/passages/' + stopId + '-web-aut');
                if (!resp.ok) throw new Error('HTTP ' + resp.status);
                var passages = await resp.json();

                if (!Array.isArray(passages) || passages.length === 0) {
                    document.getElementById('widget-body').innerHTML =
                        '<div class="widget-empty">Nessun passaggio previsto al momento.</div>';
                    return;
                }

                var html = passages.slice(0, maxItems).map(function(p) {
                    var lineParts = (p.line || '').split('_');
                    var lineName = lineParts[0] || p.line;
                    var badgeClass = getBadgeClass(p.line);
                    var timeClass = p.real ? 'widget-time realtime' : 'widget-time';
                    var timeStr = formatTime(p.time);

                    return '<div class="widget-passage">' +
                        '<div class="widget-badge ' + badgeClass + '">' + lineName + '</div>' +
                        '<div class="widget-dest">' + (p.destination || '') + '</div>' +
                        '<div class="' + timeClass + '">' + timeStr + '</div>' +
                        '</div>';
                }).join('');

                document.getElementById('widget-body').innerHTML = html;
            } catch (err) {
                document.getElementById('widget-body').innerHTML =
                    '<div class="widget-error">Errore nel caricamento dei dati.</div>';
            }
        }

        loadWidget();
        setInterval(loadWidget, 30000);
    </script>
</body>
</html>
