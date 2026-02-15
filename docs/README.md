# ACTV Live - Documentazione Tecnica

Benvenuto nella documentazione tecnica di **ACTV Live**. Questo manuale è progettato per permettere a uno sviluppatore di comprendere, mantenere e ricostruire l'intero sistema da zero.

## Indice della Documentazione

1.  **[Architettura del Sistema](architecture.md)**: Struttura del progetto, ciclo di vita della richiesta e pattern MVC.
2.  **[Database & Modelli](database.md)**: Schema delle tabelle MySQL e gestione della persistenza.
3.  **[API Reference](api.md)**: Elenco completo degli endpoint, parametri e formati di risposta.
4.  **[GTFS & Route Planning](gtfs.md)**: Come vengono elaborati i dati statici e come funziona l'algoritmo di ricerca percorsi.
5.  **[Frontend & UI](frontend.md)**: Design system, moduli JavaScript e integrazione con Leaflet.
6.  **[Admin Tools & Logging](admin-tools.md)**: Gestione degli errori, logs centralizzati e la Time Machine.
7.  **[Live Bus Tracking](live-tracker.md)**: Logica di interpolazione delle posizioni e caricamento asincrono sulla mappa.

## Stack Tecnologico

-   **Backend**: PHP 8.x (Custom MVC).
-   **Database**: MySQL / MariaDB (per logs e dati persistenti), JSON Cache (per dati GTFS ad alte prestazioni).
-   **Frontend**: Vanilla JavaScript (ES6+), CSS3 (Custom Design System), Bootstrap 5 (Utility).
-   **Mappe**: Leaflet.js.
-   **Dati**: GTFS (General Transit Feed Specification).

## Requisiti di Installazione

1.  **Server Web**: Apache con `mod_rewrite` abilitato (vedi `.htaccess`).
2.  **PHP**: Versione >= 8.0.
3.  **Database**: MySQL con il dump fornito.
4.  **Configurazione**: Rinominare `.env.example` (se presente) in `.env` e configurare le credenziali DB.

## Avvio Rapido

Per rigenerare i dati GTFS (necessario al primo avvio o dopo un aggiornamento dei feed ACTV):

```bash
php scripts/parse_gtfs.php
```

Questo comando scaricherà il file ZIP ufficiale di ACTV, estrarrà i dati e genererà la cache JSON ottimizzata per il `RoutePlanner`.
