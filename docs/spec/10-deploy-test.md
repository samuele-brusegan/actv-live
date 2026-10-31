# 10 — Deploy, test e CI

## Web server di riferimento

L'installazione di produzione usa docker: `php-fpm` + `nginx` dietro
`nginx-proxy-manager` (TLS). Document root: `public/`.

### nginx (vhost reale dell'installazione)

```nginx
server {
    listen 80;
    server_name actv-live.test;
    root /var/www/html/actv-live/public;
    index index.php index.html;
    autoindex on;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_pass php:9000;
        fastcgi_index index.php;
    }
}
```

### Apache equivalente (`public/.htaccess`, già incluso)

```apache
RewriteEngine On
RewriteBase /
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]
```

### TLS

Obbligatorio: `index.php` forza il redirect a `https://` quando
`HTTP_X_FORWARDED_SCHEME != https` e `HTTPS != on`. Dietro proxy, passare
`X-Forwarded-Proto/Scheme`.

## `.env` completo

```ini
DB_HOST=localhost
DB_PORT=3306
DB_USER=...
DB_PASS=...
DB_NAME=actv
URL_PATH=https://actv-live.test
ADMIN_PASSWORD=changeme
GTFS_URLS=https://actv.avmspa.it/sites/default/files/attachments/opendata/automobilistico/actv_aut.zip
GTFS_PHP_CLI=/usr/local/bin/php
ACTV_PERF_DIAGNOSTICS=0
```

## Permessi scrittura

`data/` deve essere scrivibile dall'utente PHP: `data/gtfs/cache`,
`data/gtfs/realtime`, `data/gtfs-update`, `data/gtfs/navigation`. Se il planner
dir non è scrivibile, `ConnectionCacheBuilder` usa una dir temporanea.

## Setup iniziale

```bash
cp .env.example .env && edit .env
composer install && npm ci
php scripts/setup_db.php          # tabelle app (logs, feedback, tm_*)
php scripts/update_gtfs.php --trigger=manual   # primo import feed
php scripts/refine_shapes.php     # shapes_refined (se non già fatto dall'update)
php scripts/update_navigation.php # feed navigazione
php scripts/build_planner_cache.php $(date +%F) $(date -d tomorrow +%F)
```

## Test

### PHP — Pest 4 (`composer.json` dev)

```bash
vendor/bin/pest                          # tutta la suite
vendor/bin/pest tests/Unit/RouterTest.php
```

`phpunit.xml`: suite `Unit` (`tests/Unit`) e `Feature` (`tests/Feature`),
source `app/` + `src/`, bootstrap `vendor/autoload.php`. Test presenti:
`RouterTest`, `DatabaseConnectorTest`, `ApiControllerTest`,
`ConnectionScanPlannerTest` (fixture multimodale Mestre FS→Pellestrina),
`GTFSParserTest`, `GtfsRealtimeTest`, `LineScheduleProviderTest`,
`RoutePlannerTest`, `LoggerTest`. Fixture GTFS in `tests/fixtures/`
(feed ridotti + cache precostruite).

### JS — Jest 30 + jsdom

```bash
npm test
npx jest --testPathPattern=routeFinder
```

`jest.config.js`: `testEnvironment: 'jsdom'`, `setupFiles: tests/jest/setup.js`,
`testMatch: tests/jest/**/*.test.js`, coverage su `public/js` +
`public/components`. ~25 file di test che coprono quasi ogni modulo
(utils, theme, cookie-notice, notifications, offline, widget, delayHistory,
stop, routeFinder/Results/Details, stationSelector, tripFinder/Details,
liveBusMap(+Loading), linesMap, mapRouteLanes, lineSchedule, script-home,
StopCard, tripTracking).

## CI — `.github/workflows/test.yml`

Su ogni push/PR, due job paralleli:

- **test-js**: Node 20 → `npm ci` → `npm test`;
- **test-php**: PHP 8.4 (mbstring, xml, ctype, iconv, mysql, sqlite3) →
  `composer install` → `vendor/bin/pest`.

## Diagnostica performance (opzionale)

`ACTV_PERF_DIAGNOSTICS=1` + `?perf=1` sull'URL → header `Server-Timing` e
`X-ACTV-Perf` lato server; `ACTVPerf.snapshot()/download()` in console per le
metriche browser (fetch, long task, slow frame, marker Leaflet, memoria).
Modalità pensata per diagnosi temporanee, da non lasciare esposta.

## Dipendenze runtime esterne (CDN)

Bootstrap 5.3.3 CSS / 5.3.6 JS, Popper 2.11.8, Axios, Leaflet (unpkg),
Leaflet.fullscreen (mapbox CDN), qrcode-generator 1.4.4, ldrs (hatch),
Material Symbols (woff2 locale). Se serve on-premise completo, scaricare e
servire localmente questi asset.
