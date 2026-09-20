# Trip Finder

Il Trip Finder permette di individuare una corsa automobilistica specifica e
aprire direttamente il relativo dettaglio fermata-per-fermata.

- **Route:** `/trip-finder` → `Controller::tripFinder()`
- **View:** `app/views/tripFinder.php`
- **Client JS:** `public/js/tripFinder.js`
- **Stili:** `public/css/tripFinder.css`

## Flusso utente

1. l’utente sceglie la data di servizio;
2. il catalogo carica le linee automobilistiche attive per quella data;
3. la partenza viene popolata solo con la prima fermata delle varianti della
   linea, cioè i capolinea da cui una corsa effettivamente parte;
4. la destinazione viene popolata solo con l’ultima fermata delle varianti e
   solo quando è successiva alla partenza selezionata;
5. le corse sono caricate per la coppia `partenza → destinazione`;
6. la selezione di una corsa abilita il passaggio a `/trip-details`.

## Endpoint utilizzati

### `GET /api/line-catalog`

Carica le linee disponibili. Il Trip Finder usa:

```text
/api/line-catalog?service=automobilistico&date=YYYY-MM-DD
```

### `GET /api/line-variants`

Carica le varianti e le relative fermate:

```text
/api/line-variants?line=LINEA&service=automobilistico&date=YYYY-MM-DD&day=monday
```

Le varianti sono la fonte per i due dropdown dei capolinea; non vengono usate
semplicemente tutte le fermate attraversate dalla linea.

### `GET /api/line-trips`

Carica le corse che servono entrambe le fermate nella sequenza corretta e che
sono attive nella data richiesta, incluse le eccezioni `calendar_dates`.

```text
/api/line-trips?line=LINEA&origin=STOP_ID&destination=STOP_ID
  &date=YYYY-MM-DD&day=monday
```

La risposta contiene `trip_id`, direzione, orario di partenza e orario di
arrivo alla destinazione:

```json
{
  "success": true,
  "service": "automobilistico",
  "line": "5E",
  "origin": "1234",
  "destination": "5678",
  "date": "2026-09-19",
  "trips": [
    {
      "trip_id": "AUT_ACTV_5E_123",
      "headsign": "Venezia",
      "departure_time": "08:10",
      "arrival_time": "08:42"
    }
  ]
}
```

## Paginazione e ricerca rapida

Quando il filtro produce più di 10 corse, la lista mostra 10 risultati per
pagina. I controlli sono sotto le corse e consentono di:

- passare alla pagina precedente o successiva;
- inserire direttamente il numero di pagina;
- cercare la prima corsa con partenza non precedente a un orario indicato.

La paginazione è client-side: l’API viene chiamata una sola volta per la
coppia di fermate selezionata.

## Evidenziazione della corsa in movimento

Se la data scelta coincide con quella locale del browser, una corsa è marcata
come **“In movimento secondo orario”** quando l’ora corrente rientra nella
finestra:

```text
partenza - 30 minuti ≤ ora corrente ≤ arrivo + 30 minuti
```

Il calcolo usa esclusivamente gli orari statici restituiti da `/api/line-trips`;
non rappresenta una posizione realtime del mezzo.

## Stato della sessione

I valori di data, linea, partenza, destinazione, corsa selezionata e pagina
corrente sono salvati nella `sessionStorage` del browser con la chiave
`actvLive.tripFinder`. Quando la pagina viene riaperta, i dropdown vengono
ricostruiti in ordine e i valori vengono ripristinati solo se sono ancora
presenti nei dati correnti.

La sessione è locale alla scheda/browser: non è un salvataggio server-side e si
perde quando il browser elimina la sessione della scheda.

## Apertura dei dettagli

Il pulsante finale apre `/trip-details` con il `tripId` e il contesto della
ricerca (`line`, fermata di partenza, destinazione e orario). Il dettaglio
usa poi il `trip_id` per caricare la sequenza completa delle fermate e le
informazioni geometriche disponibili.
