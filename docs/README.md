# ACTV Live - Documentazione Tecnica

Benvenuto nella documentazione tecnica di **ACTV Live**. Questo manuale è progettato per permettere a uno sviluppatore di comprendere, mantenere e ricostruire l'intero sistema da zero.

## Indice della Documentazione

1.  **[Architettura del Sistema](architecture.md)**: Struttura del progetto, ciclo di vita della richiesta e pattern MVC.
2.  **[Database & Modelli](database.md)**: Schema delle tabelle MySQL e gestione della persistenza.
3.  **[API Reference](api.md)**: Elenco completo degli endpoint, parametri e formati di risposta.
4.  **[Database GTFS](gtfs-format.md)**: Tabelle, campi, relazioni e contenuti di esempio.
5.  **[GTFS & Route Planning](GTFS.md)**: Come vengono elaborati i dati statici e come funziona l'algoritmo di ricerca percorsi.
6.  **[Frontend & UI](frontend.md)**: Design system, moduli JavaScript e integrazione con Leaflet.
7.  **[Admin Tools & Logging](admin-tools.md)**: Gestione degli errori, logs centralizzati e la Time Machine.
8.  **[Live Bus Tracking](live-tracker.md)**: Logica di interpolazione delle posizioni e caricamento asincrono sulla mappa.
9.  **[Documentazione per Feature](features/README.md)**: Documentazione ad albero, una pagina per feature (le feature più grandi sono cartelle con un README e file dedicati ai sotto-problemi).

## Stack Tecnologico

-   **Backend**: PHP 8.x (Custom MVC).
-   **Database**: MySQL / MariaDB (per logs e dati persistenti), JSON Cache (per dati GTFS ad alte prestazioni).
-   **Frontend**: Vanilla JavaScript (ES6+), CSS3 (Custom Design System), Bootstrap 5 (Utility).
-   **Mappe**: Leaflet.js.
-   **Dati**: GTFS (General Transit Feed Specification).

## Requisiti di Installazione

1.  **Server Web**: Apache con `mod_rewrite` abilitato (vedi `.htaccess`).
2.  **PHP**: Versione >= 8.4, con estensioni `curl` e `pdo_mysql`.
3.  **Estrazione GTFS**: Estensione PHP `zip` (`ZipArchive`) oppure comando di sistema `unzip`.
4.  **Pianificazione GTFS**: Comando `crontab` disponibile e servizio cron attivo.
5.  **Database**: MySQL o MariaDB con il dump fornito.
6.  **Configurazione**: Rinominare `.env.example` (se presente) in `.env` e configurare le credenziali DB.

Su Debian/Ubuntu le dipendenze PHP e GTFS possono essere installate con:

```bash
sudo apt install cron php8.4-cli php8.4-curl php8.4-mysql php8.4-zip unzip
```

## Avvio Rapido

Per rigenerare i dati GTFS (necessario al primo avvio o dopo un aggiornamento dei feed ACTV):

```bash
php scripts/parse_gtfs.php
```

Questo comando scaricherà il file ZIP ufficiale di ACTV, estrarrà i dati e genererà la cache JSON ottimizzata per il `RoutePlanner`.
