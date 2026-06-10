# API Reference

Le API di ACTV Live sono esposte sotto `/api` e usano il database GTFS
configurato dall'applicazione.

## Convenzioni

- Tutti gli endpoint pubblici sono accessibili senza API key.
- Salvo dove indicato diversamente, le richieste sono `GET` e le risposte sono
  JSON.
- Gli orari GTFS possono superare `24:00:00` per rappresentare corse notturne.
- I nomi dei giorni sono in inglese minuscolo:
  `monday`, `tuesday`, `wednesday`, `thursday`, `friday`, `saturday`, `sunday`.
- Il router non applica attualmente il metodo HTTP. I metodi indicati in questa
  pagina sono quelli previsti dal contratto dell'API.
- Alcuni errori di validazione legacy restituiscono HTTP `200` con
  `success: false`; controllare sempre anche il corpo della risposta.

Esempio:

```bash
curl 'https://example.test/api/stop-lines?stop=1234&time=14:30'
```

## Indice

| Metodo | Endpoint | Descrizione |
|---|---|---|
| `GET` | `/api/stops` | Tutte le fermate |
| `GET` | `/api/plan-route` | Pianificazione di un percorso |
| `GET` | `/api/stop-lines` | Linee disponibili a una fermata |
| `GET` | `/api/lines-shapes` | Percorsi geografici di linee e corse |
| `GET` | `/api/trip-stops` | Fermate di una linea |
| `GET` | `/api/bus-position` | Fermate e shape di una corsa |
| `GET` | `/api/gtfs-bnr` | Corse attive vicino a un orario |
| `GET` | `/api/line-variants` | Varianti di percorso di una linea |
| `GET` | `/api/line-schedule` | Orario giornaliero di una o più varianti |
| `GET` | `/api/stop-upcoming` | Passaggi previsti nella prossima ora |
| `GET` | `/api/gtfs-identify` | Ricerca del `trip_id` GTFS |
| `GET` | `/api/gtfs-resolve` | Metadati di un `trip_id` |
| `GET` | `/api/gtfs-builder` | Fermate complete di un `trip_id` |
| `GET` | `/api/gtfs-stop-translater` | Dati fermata e stop time di una corsa |
| `GET` | `/api/gtfs-stops` | Elenco fermate GTFS legacy |
| `GET` | `/api/gtfs-passages` | Passaggi GTFS previsti per fermata ACTV |
| `POST` | `/api/log-js-error` | Registrazione di un errore frontend |
| `GET` | `/api/admin/gtfs-update/status` | Stato aggiornamento GTFS |
| `POST` | `/api/admin/gtfs-update/config` | Configurazione aggiornamento GTFS |
| `POST` | `/api/admin/gtfs-update/start` | Avvio aggiornamento GTFS |

## Fermate e linee

### `GET /api/stops`

Restituisce tutte le colonne della tabella GTFS `stops`.

```json
[
  {
    "stop_id": "1234",
    "stop_code": "1234",
    "stop_name": "Mestre Centro",
    "stop_lat": "45.493",
    "stop_lon": "12.242",
    "data_url": "1234-web-aut"
  }
]
```

### `GET /api/stop-lines`

Restituisce le linee disponibili presso una fermata a partire da un orario.

| Parametro | Obbligatorio | Descrizione |
|---|---:|---|
| `stop` | sì | `stop_id` GTFS |
| `time` | no | Orario `HH:MM` o `HH:MM:SS`; default: ora server |

```json
{
  "success": true,
  "stop_id": "1234",
  "lines": []
}
```

### `GET /api/trip-stops`

Seleziona una corsa rappresentativa di una linea e ne restituisce le fermate.

| Parametro | Obbligatorio | Descrizione |
|---|---:|---|
| `line` | sì | `route_short_name`, per esempio `5E` |
| `dest` | no | Testo da cercare nel capolinea |

```json
[
  {
    "id": "1234",
    "name": "Mestre Centro",
    "lat": "45.493",
    "lng": "12.242",
    "time": "14:35"
  }
]
```

Errori: `400` se manca `line`; `404` se la linea o una corsa non esistono.

## Pianificazione

### `GET /api/plan-route`

Calcola percorsi tra due fermate o coordinate geografiche.

| Parametro | Obbligatorio | Descrizione |
|---|---:|---|
| `from` | sì | `stop_id` oppure coordinate `lat,lon` |
| `to` | sì | `stop_id` oppure coordinate `lat,lon` |
| `time` | no | Partenza `HH:MM` o `HH:MM:SS`; default: ora server |
| `optimize` | no | `time`, `transfers` o `walking`; default: `time` |
| `debug` | no | Se presente restituisce dati diagnostici invece dei percorsi |

```bash
curl 'https://example.test/api/plan-route?from=45.49,12.24&to=5678&time=14:30&optimize=time'
```

```json
{
  "success": true,
  "optimize": "time",
  "routes": [
    {
      "departure_time": "14:30:00",
      "arrival_time": "15:05:00",
      "duration": 35,
      "legs": []
    }
  ]
}
```

Quando un estremo è espresso come coordinate, la risposta può includere una
tratta con `type: "walking"` verso o dalla fermata più vicina.

## Corse e posizione

### `GET /api/gtfs-bnr`

Restituisce le corse con almeno un passaggio entro 30 minuti dall'orario
richiesto. Include le corse notturne iniziate il giorno precedente.

| Parametro | Obbligatorio | Descrizione |
|---|---:|---|
| `time` | no | Orario `HH:MM`; default: ora server |
| `day` | no | Giorno in inglese; default: giorno server |

```json
{
  "time": "12:30",
  "day": "monday",
  "count": 1,
  "buses": [
    {
      "route_short_name": "5E",
      "route_id": "10",
      "trip_headsign": "Venezia",
      "arrival_time": "12:25:00",
      "trip_id": "123",
      "source": "today",
      "diff_min": -5
    }
  ]
}
```

### `GET /api/bus-position`

Restituisce fermate e shape stradale raffinata di una corsa.

| Parametro | Obbligatorio | Descrizione |
|---|---:|---|
| `tripId` | sì | Identificatore GTFS della corsa |

```json
{
  "stops": [
    {
      "stop_id": "1234",
      "stop_name": "Mestre Centro",
      "stop_lat": "45.493",
      "stop_lon": "12.242",
      "arrival_time": "14:35:00",
      "departure_time": "14:35:00",
      "stop_sequence": "4",
      "data_url": "1234-web-aut"
    }
  ],
  "shape": [
    {
      "lat": "45.493",
      "lng": "12.242",
      "sequence": "1",
      "dist_traveled": "0"
    }
  ]
}
```

Errore `400` se manca `tripId`. Se la corsa non ha fermate viene restituito
`{"error":"No stops found for this trip"}`.

### `GET /api/lines-shapes`

Restituisce i percorsi raggruppati per linea o corsa.

| Parametro | Obbligatorio | Descrizione |
|---|---:|---|
| `line` | no | Filtra per `route_short_name` |
| `tripId` / `trip_id` | no | Filtra per una corsa |
| `tripIds` | no | Lista di `trip_id` separati da virgola |
| `tripGroups` | no | Gruppi separati da `|`, ciascuno come `N:id1,id2` |

Senza filtri restituisce una corsa rappresentativa per ogni rotta. Con un
filtro include anche `shape`, contenente i punti di `shapes_refined`.

```json
[
  {
    "route_id": "10",
    "trip_id": "123",
    "group_number": null,
    "route_short_name": "5E",
    "route_long_name": "Noale - Venezia",
    "shape_id": "shape-1",
    "path": [{"lat": "45.493", "lng": "12.242", "name": "Mestre Centro"}],
    "shape": [{"lat": 45.493, "lng": 12.242}]
  }
]
```

## Orari

### `GET /api/line-variants`

Restituisce le varianti attive di una linea, raggruppate per shape e
destinazione.

| Parametro | Obbligatorio | Descrizione |
|---|---:|---|
| `line` | sì | `route_short_name` |
| `day` | no | Giorno in inglese; default: giorno server |
| `date` | no | Data `YYYY-MM-DD`; default: data server |

```json
{
  "success": true,
  "line": "5E",
  "variants": [
    {
      "shape_id": "shape-1",
      "headsign": "Venezia",
      "direction_id": "0",
      "trip_id": "123",
      "stops_count": 25,
      "trips_count": 12,
      "origin": "Noale",
      "terminus": "Venezia",
      "stops": [
        {
          "id": "1234",
          "name": "Mestre Centro",
          "lat": "45.493",
          "lng": "12.242",
          "seq": "10",
          "time": "14:35:00"
        }
      ]
    }
  ]
}
```

### `GET /api/line-schedule`

Restituisce la matrice fermate-orari delle varianti indicate.

| Parametro | Obbligatorio | Descrizione |
|---|---:|---|
| `line` | sì | `route_short_name` |
| `trips` | sì | `trip_id` rappresentativi separati da virgola |
| `trip` | alternativa | Alias di `trips` per una singola variante |
| `day` | no | Giorno in inglese; default: giorno server |
| `date` | no | Data `YYYY-MM-DD`; default: data server |

```json
{
  "success": true,
  "line": "5E",
  "day": "monday",
  "headsign": "Venezia",
  "stops": [{"id": "1234", "name": "Mestre Centro"}],
  "runs": [
    {
      "trip_id": "123",
      "start": "14:00",
      "times": ["14:35"]
    }
  ]
}
```

La posizione di ogni elemento in `times` corrisponde alla stessa posizione
nell'array `stops`. Un valore `null` indica che quella corsa non serve la
fermata.

### `GET /api/stop-upcoming`

Restituisce tutti i passaggi previsti nei 60 minuti successivi.

| Parametro | Obbligatorio | Descrizione |
|---|---:|---|
| `stop` | sì | `stop_id` GTFS |
| `time` | no | Orario `HH:MM`; default: ora server |
| `day` | no | Giorno in inglese; default: giorno server |

```json
{
  "success": true,
  "stop": "1234",
  "day": "monday",
  "time": "14:30",
  "count": 1,
  "buses": [
    {
      "route_short_name": "5E",
      "route_id": "10",
      "trip_headsign": "Venezia",
      "trip_id": "123",
      "time": "14:35",
      "in_min": 5
    }
  ]
}
```

## Compatibilità GTFS

Questi endpoint supportano parti legacy del frontend. Per gli endpoint che lo
richiedono, aggiungere `return=true` per ottenere JSON; `rtable=true` produce
invece una tabella HTML diagnostica.

### `GET /api/gtfs-identify`

Individua il `trip_id` che corrisponde ai dati di un passaggio ACTV.

| Parametro | Obbligatorio | Descrizione |
|---|---:|---|
| `return` | sì | Usare `true` per la risposta JSON |
| `time` | no | Orario del passaggio |
| `busTrack` | sì | Nome breve della linea |
| `busDirection` | sì | Destinazione visualizzata |
| `day` | sì | Giorno in inglese |
| `stop` | sì* | Nome della fermata |
| `stopId` | no | ID ACTV, preferito al nome quando disponibile |
| `lineId` | no | Accettato per compatibilità; attualmente non usato nella query |
| `limit` | no | Numero massimo di risultati; default `1` |

`stop` può essere omesso operativamente quando è disponibile `stopId`.
Con `limit=1` la risposta è un singolo oggetto GTFS; con un limite maggiore è
un array.

### `GET /api/gtfs-resolve`

| Parametro | Obbligatorio | Descrizione |
|---|---:|---|
| `return` | sì | Usare `true` |
| `tripId` | sì | Identificatore GTFS della corsa |

```json
{
  "line_id": "5011",
  "bus_track": "5E",
  "bus_direction": "Venezia",
  "line_tag": "..."
}
```

### `GET /api/gtfs-builder`

Richiede `trip_id` e restituisce tutte le fermate della corsa, ordinate per
`stop_sequence`. Le fermate duplicate consecutive vengono eliminate.

Errore `400` se manca `trip_id`.

### `GET /api/gtfs-stop-translater`

Richiede `trip_id` e restituisce l'unione delle colonne `stops` e `stop_times`
per la corsa, ordinata per orario di arrivo.

Errore `400` se manca `trip_id`.

### `GET /api/gtfs-stops`

Richiede `return=true`. Restituisce tutte le righe della tabella `stops`.

### `GET /api/gtfs-passages`

Restituisce i passaggi programmati per una fermata ACTV, usati come fallback
quando il real-time non è disponibile.

| Parametro | Obbligatorio | Descrizione |
|---|---:|---|
| `return` | sì | Usare `true` |
| `stop` | sì | ID ACTV contenuto in `stops.data_url` |

```json
[
  {
    "line": "10",
    "destination": "Venezia",
    "time": "14:35",
    "real": false,
    "stop": "1234",
    "lineId": "5011",
    "timingPoints": [
      {"stop": "Mestre Centro", "time": "14:35:00"}
    ]
  }
]
```

## Logging frontend

### `POST /api/log-js-error`

Registra un errore JavaScript tramite il logger applicativo.

```bash
curl -X POST 'https://example.test/api/log-js-error' \
  -H 'Content-Type: application/json' \
  -d '{"message":"TypeError","url":"/app","line":42,"stack":"..."}'
```

| Campo | Obbligatorio | Descrizione |
|---|---:|---|
| `message` | no | Messaggio; default `Unknown JS error` |
| `url` | no | URL o file sorgente |
| `line` | no | Numero di riga |
| `stack` | no | Stack trace |

Risposta valida: `{"success":true}`. Un body JSON non valido produce HTTP
`400`.

## API amministrative

Le API amministrative richiedono:

1. una sessione autenticata tramite `/admin/login`;
2. il cookie di sessione sulla richiesta;
3. per le operazioni `POST`, il token CSRF della sessione nel campo JSON
   `csrf`.

Errori comuni:

| Stato | Significato |
|---:|---|
| `401` | Sessione amministratore assente o scaduta |
| `403` | Token CSRF assente o non valido |
| `409` | Aggiornamento GTFS già attivo o non avviabile |
| `500` | Errore interno dell'operazione |

### `GET /api/admin/gtfs-update/status`

Restituisce configurazione, stato del processo, conteggi delle tabelle, data
della cache e coda del log.

```json
{
  "success": true,
  "data": {
    "config": {
      "enabled": false,
      "weekday": 1,
      "time": "03:00:00",
      "last_scheduled_week": null
    },
    "state": {
      "status": "idle",
      "running": false,
      "tasks": [],
      "stats": []
    },
    "database": {},
    "cache_updated_at": null,
    "log_tail": []
  }
}
```

### `POST /api/admin/gtfs-update/config`

```json
{
  "csrf": "token-della-sessione",
  "enabled": true,
  "weekday": 1,
  "time": "03:00:00"
}
```

`weekday` usa i numeri ISO `1`-`7` (lunedì-domenica). `time` usa `HH:MM:SS`.
Il giorno e l'orario della pianificazione sono sempre interpretati in UTC+00.
La risposta contiene `success` e la configurazione normalizzata in `config`.

### `POST /api/admin/gtfs-update/start`

```json
{"csrf": "token-della-sessione"}
```

Risposta:

```json
{
  "success": true,
  "started": true,
  "message": "Aggiornamento avviato."
}
```

## Endpoint non API

Le sorgenti esterne `oraritemporeale.actv.it` chiamate dal frontend non sono
proxy gestiti da ACTV Live e non fanno parte di questo contratto. Gli endpoint
`/api/tm/*` citati in versioni precedenti della documentazione non sono
registrati dal router corrente.
