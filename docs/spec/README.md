# ACTV Live — Specifica completa dell'applicazione

Questa serie di documenti descrive **in modo esaustivo** l'applicazione web
**ACTV Live**: architettura, backend, database, API, pipeline dati, interfaccia
grafica e ogni singola funzionalità, corredata da screenshot reali in formato
mobile (390×844) e diagrammi UML in sintassi Mermaid.

L'obiettivo è che da questa documentazione sia possibile **ricreare la stessa
identica applicazione**.

## Indice

| Documento | Contenuto |
|---|---|
| [01-panoramica.md](01-panoramica.md) | Cos'è l'app, stack tecnologico, sorgenti dati, struttura del repository, installazione |
| [02-architettura.md](02-architettura.md) | Bootstrap, ciclo di vita della richiesta, router, controller, sessione, gestione errori, configurazione `.env` |
| [03-database.md](03-database.md) | Schema completo di tutte le tabelle MySQL e diagramma ER |
| [04-api.md](04-api.md) | Riferimento completo di tutti gli endpoint `/api/*` (parametri, risposte, errori) |
| [05-pipeline-gtfs.md](05-pipeline-gtfs.md) | Feed GTFS/GTFS-RT, parser, cache JSON, connection cache, aggiornamento pianificato, script CLI |
| [06-ui-design.md](06-ui-design.md) | Design system: palette colori (light/dark), tipografia, componenti UI, icone, layout |
| [07-frontend.md](07-frontend.md) | Tutti i moduli JavaScript, chiavi `localStorage`/`sessionStorage`, componenti condivisi, PWA e Service Worker |
| [08-pagine.md](08-pagine.md) | Ogni pagina dell'app descritta nel dettaglio, con screenshot mobile |
| [09-uml.md](09-uml.md) | Diagrammi UML Mermaid: classi, ER, sequenza, componenti, deployment, stati |
| [10-deploy-test.md](10-deploy-test.md) | Configurazione web server, HTTPS, test (Pest/Jest), CI |
| [screenshots/](screenshots/) | Screenshot reali a 390×844 di tutte le pagine, light e dark |

## Note sugli screenshot

Gli screenshot in `screenshots/` sono stati catturati con viewport **390×844 @2x**
(iPhone-like). Le schermate `09-route-results` e `10-route-details` mostrano la UI
reale popolata con dati di esempio rappresentativi del formato dell'API.

## Mappa rapida dei file sorgente

| Area | Percorso |
|---|---|
| Entry point | `public/index.php` |
| Rotte | `public/routes.php` |
| Router | `app/Router.php` |
| Controller pagine | `app/controllers/Controller.php` |
| Controller API | `app/controllers/ApiController.php` |
| Viste | `app/views/` |
| Servizi | `app/services/` |
| Modelli | `app/models/` |
| Asset pubblici | `public/css`, `public/js`, `public/components`, `public/svg`, `public/pwa` |
| Dati GTFS | `data/gtfs/` |
| Script CLI | `scripts/` |
| Test | `tests/Unit`, `tests/Feature`, `tests/jest` |
