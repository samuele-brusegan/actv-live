# Documentazione tecnica di ACTV Live

Questa directory raccoglie la documentazione per sviluppo, manutenzione e
deploy di ACTV Live. Il codice resta la fonte di verità per le rotte e per i
contratti API: le rotte registrate sono in [`public/routes.php`](../public/routes.php),
mentre le implementazioni sono nei controller e nei service sotto `app/`.

## Indice

### Fondamenta

- [Architettura](architecture.md) — bootstrap, router e flusso delle richieste.
- [Frontend](frontend.md) — CSS, JavaScript, PWA e componenti condivisi.
- [Database e modelli](database.md) — persistenza applicativa e relazioni.
- [Schema GTFS](gtfs-format.md) — tabelle importate e campi applicativi.
- [Formato JSON ACTV](actv-json-format.md) — payload utilizzati dalle sorgenti ACTV.
- [API](api.md) — endpoint pubblici e amministrativi.

### Dati e pianificazione

- [Pipeline GTFS](features/gtfs-pipeline.md) — download, cache, import atomico e cron.
- [GTFS e route planning](GTFS.md) — cache del planner e algoritmi di ricerca.
- [Route Finder](features/route-finder/README.md) — flusso di pianificazione multimodale.
- [Trip Finder](features/trip-finder.md) — selezione di linea, capolinea, corsa e dettagli.

### Esperienza utente

- [Home](features/home.md)
- [Elenco fermate](features/stop-list.md)
- [Dettaglio fermata](features/stop-details/README.md)
- [Mappa delle linee](features/lines-map.md)
- [Mappa realtime](features/live-bus-map/README.md)
- [Dettaglio corsa](features/trip-details.md)
- [Statistiche ritardi](features/delay-stats.md)
- [Widget incorporabile](features/shareable-widget.md)

### Amministrazione e diagnosi

- [Strumenti admin e logging](admin-tools.md)
- [Autenticazione admin](features/admin/authentication.md)
- [Dashboard e log](features/admin/dashboard.md)
- [Diagnostica performance](performance-diagnostics.md)
- [Code review storica](CODE_REVIEW.md)

## Stack

| Area | Tecnologia |
|---|---|
| Backend | PHP 8.4+, MVC leggero senza framework |
| Routing | Router custom in `app/Router.php` |
| Frontend | JavaScript vanilla, CSS, Bootstrap/Leaflet via asset condivisi |
| Dati statici | Feed GTFS automobilistico e navigazione |
| Dati realtime | Feed GTFS-RT ACTV per vehicle positions e trip updates |
| Persistenza | MySQL/MariaDB e cache JSON locale |
| Test | Pest per PHP, Jest + jsdom per JavaScript |
| Automazione | GitHub Actions su push e pull request |

## Avvio rapido per sviluppatori

Dalla radice del repository:

```bash
cp .env.example .env
composer install
npm ci
vendor/bin/pest --no-coverage
npm test
```

Per i prerequisiti di database, il document root e gli aggiornamenti GTFS vedere
il [README principale](../README.md). Per usare il server PHP integrato dietro
un reverse proxy HTTPS:

```bash
php -S 127.0.0.1:8080 -t public public/index.php
```

Il server integrato non fornisce TLS; usato direttamente, l’entry point
reindirizza a HTTPS. Per il comportamento completo usare Apache o un reverse
proxy TLS davanti all’upstream PHP.

## Regole di manutenzione della documentazione

- aggiornare `docs/api.md` quando si aggiunge o modifica un endpoint;
- aggiornare la pagina della feature quando cambia un flusso visibile all’utente;
- distinguere dati statici GTFS, dati realtime e fallback locali;
- non documentare endpoint non presenti in `public/routes.php` come se fossero attivi;
- indicare sempre quando un dato è locale al browser (`localStorage` o
  `sessionStorage`) e non sincronizzato lato server.
