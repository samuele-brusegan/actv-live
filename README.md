# ACTV Live

[![Test JavaScript](https://github.com/samuele-brusegan/actv-live/actions/workflows/test.yml/badge.svg)](https://github.com/samuele-brusegan/actv-live/actions/workflows/test.yml)
[![Licenza MIT](https://img.shields.io/badge/licenza-MIT-green.svg)](LICENSE.md)

ACTV Live è una web app per consultare il trasporto pubblico ACTV nell’area di
Venezia. Unisce dati statici GTFS, feed GTFS-RT e sorgenti ACTV per offrire
orari, ricerca percorsi, fermate, mappa dei mezzi e dettagli delle corse.

Il progetto è scritto in PHP senza framework backend e JavaScript vanilla senza
un passaggio di build: la directory `public/` è il document root dell’applicazione.

## Funzionalità

- ricerca percorsi diretti e multimodali con cambi, con supporto a fermate e coordinate;
- elenco fermate, fermate preferite e tabellone con passaggi realtime e fallback GTFS;
- mappa delle linee e mappa realtime di autobus e mezzi di navigazione;
- orari per linea, varianti di percorso e Trip Finder per individuare una corsa;
- dettaglio fermata-per-fermata di una corsa e relative shape geografiche;
- statistiche locali sui ritardi e widget incorporabile per una fermata;
- area amministrativa per log, metriche, aggiornamento GTFS e ispezione GTFS-RT.

## Architettura in breve

```text
Browser
  │
  ├── pagine PHP in app/views/
  ├── JavaScript/CSS statici in public/
  └── API JSON /api/*
          │
          ├── cache JSON GTFS per il planner
          ├── MySQL/MariaDB per GTFS, log e feedback
          └── feed ACTV GTFS / GTFS-RT
```

Il routing è gestito da `app/Router.php`; le pagine sono esposte da
`app/controllers/Controller.php`, mentre gli endpoint JSON sono in
`app/controllers/ApiController.php`. La pipeline GTFS pubblica cache e tabelle
in modo atomico, così una nuova importazione incompleta non sostituisce i dati
attivi.

## Requisiti

- PHP 8.4 o superiore;
- estensioni PHP `curl`, `mbstring`, `pdo_mysql` e `mysqli`;
- `ZipArchive` oppure il comando di sistema `unzip`;
- MySQL o MariaDB;
- Composer 2.x;
- Node.js 20.x e npm per i test JavaScript;
- Apache con `mod_rewrite` abilitato, oppure un web server configurato per
  inoltrare le richieste non statiche a `public/index.php`;
- `crontab` e cron attivo solo se si abilita l’aggiornamento GTFS pianificato.

Su Debian/Ubuntu, una base utile è:

```bash
sudo apt install apache2 cron unzip php8.4-cli php8.4-curl \
  php8.4-mbstring php8.4-mysql php8.4-zip
sudo systemctl enable --now apache2 cron
```

Nei container PHP ufficiali è necessario installare le estensioni equivalenti
nell’immagine e avviare cron tramite il processo init o il supervisor dello
stack. Il repository non include un `Dockerfile` o un `docker-compose.yml`.

## Installazione locale

```bash
git clone https://github.com/samuele-brusegan/actv-live.git
cd actv-live
cp .env.example .env
composer install
npm ci
```

Compilare `.env` prima di avviare l’applicazione. Le tabelle GTFS principali
(`routes`, `trips`, `stops`, `stop_times`, `calendar`, `calendar_dates`,
`shapes`, `shapes_refined`) devono già esistere nel database con lo schema
compatibile descritto in [`docs/gtfs-format.md`](docs/gtfs-format.md).
Lo script `scripts/setup_db.php` crea solo le tabelle ausiliarie (`logs`,
`feedback` e le tabelle legacy della Time Machine), non sostituisce una
migrazione completa dello schema GTFS.

Per un’installazione Apache, impostare il document root su `public/` e consentire
override per il file [`public/.htaccess`](public/.htaccess). L’entry point
reindirizza le richieste non HTTPS: anche l’ambiente locale deve quindi passare
da un virtual host HTTPS o da un reverse proxy TLS quando si vogliono provare
geolocalizzazione, notifiche e il comportamento completo dell’app.

## Configurazione

Le variabili principali sono definite in [`.env.example`](.env.example):

| Variabile | Descrizione |
|---|---|
| `DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME` | Connessione MySQL/MariaDB |
| `URL_PATH` | URL base usato per i link interni |
| `ADMIN_PASSWORD` | Password dell’area `/admin/*`; obbligatoria in produzione |
| `GTFS_URLS` | URL dei feed GTFS candidati separati da virgola; viene scelto il più recente |
| `GTFS_PHP_CLI` | Percorso del PHP CLI usato dagli aggiornamenti in background |
| `ACTV_PERF_DIAGNOSTICS` | Se `1`, abilita `?perf=1` e gli header diagnostici |

Non committare mai `.env`. In produzione sostituire il valore di esempio di
`ADMIN_PASSWORD` con una password robusta e limitare l’accesso alle pagine admin.

## Dati GTFS e aggiornamenti

ACTV Live usa due profili separati:

- `automobilistico` per autobus;
- `navigation` per vaporetti e trasporto acqueo.

Per un aggiornamento completo del profilo automobilistico:

```bash
php scripts/update_gtfs.php --trigger=manual
```

Per aggiornare la cache di navigazione:

```bash
php scripts/update_navigation.php --force
```

Il parser manuale rimane disponibile per la cache locale:

```bash
php scripts/parse_gtfs.php
php scripts/parse_gtfs.php --navigation
```

L’area `/admin/gtfs-update` permette di avviare l’importazione e configurare la
pianificazione settimanale. La pianificazione installata dal pannello usa
`CRON_TZ=UTC`; il pannello mostra esplicitamente l’orario in UTC+00. Per
installazioni senza pannello è disponibile:

```bash
php scripts/sync_gtfs_cron.php
```

Stato e diagnostica dell’ultimo aggiornamento sono in `data/gtfs-update/`:
`config.json`, `state.json`, `update.log` e il log del cron. La directory `data/`
è generata a runtime e non deve essere committata.

## Avvio e test

Per un test rapido dietro un reverse proxy HTTPS si può usare il server PHP
integrato come upstream:

```bash
php -S 127.0.0.1:8080 -t public public/index.php
```

Il server integrato da solo non fornisce TLS e quindi non bypassa il redirect
HTTPS di `public/index.php`.

Suite PHP:

```bash
composer test
# oppure
vendor/bin/pest --no-coverage
```

Suite JavaScript:

```bash
npm test
```

La pipeline GitHub Actions esegue Jest su Node 20 e Pest su PHP 8.4 per push e
pull request. Le prove che dipendono da feed ACTV, MySQL o cache reali richiedono
un ambiente configurato oltre alla suite automatica.

## Documentazione

- [Indice tecnico](docs/README.md)
- [API JSON](docs/api.md)
- [Architettura](docs/architecture.md)
- [Pipeline GTFS](docs/features/gtfs-pipeline.md)
- [Trip Finder](docs/features/trip-finder.md)
- [Mappa realtime](docs/features/live-bus-map/README.md)
- [Database GTFS](docs/gtfs-format.md)
- [Strumenti amministrativi](docs/admin-tools.md)

Le rotte web e API effettivamente registrate sono sempre definite in
[`public/routes.php`](public/routes.php).

## Limitazioni note

- le notifiche dei ritardi sono locali al browser e non sono push server-side;
- preferiti, statistiche ritardi e stato del Trip Finder sono memorizzati nel
  browser, non associati a un account;
- il planner e le API GTFS dipendono dalla disponibilità e dalla validità delle
  cache e del database aggiornati;
- il widget può richiedere una configurazione CORS/proxy quando incorporato su
  domini terzi.

## Contribuire

Prima di aprire una pull request:

1. mantenere separati cambi funzionali e modifiche documentali;
2. aggiornare la documentazione della feature quando cambia un endpoint o un
   comportamento osservabile;
3. eseguire `git diff --check`, `vendor/bin/pest` e `npm test` quando disponibili;
4. non includere `.env`, cache, log o dati GTFS generati.

## Licenza

Il progetto è distribuito con licenza MIT. Vedere [`LICENSE.md`](LICENSE.md).
