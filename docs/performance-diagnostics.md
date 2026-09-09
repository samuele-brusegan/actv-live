# Diagnostica temporanea del lag

La diagnostica è disponibile sul branch `perf/lag-diagnosis-2026-09-03` ed è
intenzionalmente disattivata di default.

## Attivazione

In ambiente locale o staging impostare:

```dotenv
ACTV_PERF_DIAGNOSTICS=1
```

Aprire poi la pagina con `?perf=1`, ad esempio:

```text
https://actv-live.test/live-map?perf=1
```

Dal DevTools console usare:

```js
ACTVPerf.snapshot()
ACTVPerf.download()
```

## Dati raccolti

- durata, stato e dimensione delle richieste `fetch`;
- richieste API osservate dal Resource Timing API;
- Long Tasks del main thread;
- frame oltre 50 ms;
- numero di nodi DOM e marker Leaflet;
- memoria JS quando il browser la espone;
- marker e fasi specifiche della mappa bus/navigazione;
- `Server-Timing: app` e `X-ACTV-Perf` per il tempo applicativo PHP.

I dati restano nel browser e non vengono inviati a servizi esterni.

## Scenari minimi

Raccogliere uno snapshot dopo il caricamento iniziale per home, route finder,
stop, line schedule e live map. Sulla live map ripetere senza filtro, con una
linea, con un trip, in modalità bus, navigazione ed entrambe; eseguire anche
refresh manuale e cambio rapido del filtro.

Per ogni scenario salvare almeno tre snapshot e annotare browser, viewport,
rete, ora e disponibilità del feed realtime. Confrontare `fetches`,
`api_resources`, `longTasks`, `slowFrames`, `marks` e `marker_nodes`.

## Limiti della verifica attuale

Nel checkout di sviluppo la suite Pest e il lint PHP sono disponibili. Node,
un browser automatizzabile, Docker e il servizio HTTP `actv-live.test` non
sono disponibili; Jest e la verifica runtime devono quindi essere eseguiti in
staging o su una macchina con questi componenti.
