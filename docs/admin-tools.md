# Strumenti amministrativi e diagnostica

Questa pagina riassume gli strumenti operativi presenti nell’area admin. Per
il dettaglio dell’autenticazione vedere [features/admin/authentication.md](features/admin/authentication.md).

## Logging centralizzato

`app/services/Logger.php` intercetta errori PHP ed eccezioni dal bootstrap e
riceve gli errori JavaScript dal frontend tramite `POST /api/log-js-error`.
Gli eventi sono memorizzati nella tabella `logs` e sono consultabili in
`/admin/logs`, con filtro per tipo e limite agli ultimi 100 record.

I dati utili per la diagnosi sono:

- tipo e messaggio;
- file e riga, quando disponibili;
- stack trace per le eccezioni;
- contesto JSON, inclusi URL e dati client quando forniti dal logger.

## Dashboard operativa

`/admin/dashboard` carica dal frontend i dati della flotta e mostra bus attivi,
ritardo medio, ritardo massimo, copertura GPS e distribuzione per linea. La
dashboard usa le API realtime e non rappresenta un archivio storico server-side.

## Aggiornamento dati

`/admin/gtfs-update` è il pannello per l’importazione atomica dei feed GTFS.
Visualizza stato, task, statistiche, timestamp della cache e coda del log.
L’esecuzione manuale o pianificata è descritta in
[Pipeline GTFS](features/gtfs-pipeline.md).

## Ispezione GTFS-RT

`/admin/gtfs-rt-inspector` consente di scegliere il servizio
(`automobilistico` o `navigation`) e il feed (`vehicles` o `updates`).
Il pannello mostra il payload binario Base64, un’anteprima esadecimale e il
risultato del decoder protobuf locale.

## Diagnostica performance

Per una singola richiesta si possono abilitare gli header diagnostici con:

```ini
ACTV_PERF_DIAGNOSTICS=1
```

e aggiungendo `?perf=1` all’URL. Il server restituisce `Server-Timing: app` e
`X-ACTV-Perf`. La modalità è pensata per diagnosi temporanee e non dovrebbe
essere lasciata esposta senza necessità.

## Funzionalità storiche non attive

Le precedenti istruzioni relative a `/api/tm/heartbeat`,
`/api/tm/simulated-data` e `record_tm.php` non sono applicabili alla versione
corrente: queste rotte e questo script non esistono in `public/routes.php` e non
vanno usati per configurare l’installazione.
