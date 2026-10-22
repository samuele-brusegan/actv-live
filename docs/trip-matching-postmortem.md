# Post-mortem: match tripId, timeline trip-details e passaggi realtime

Documento tecnico su tutti i problemi riscontrati (e risolti) nel sistema di
corrispondenza tra dati realtime ACTV e GTFS statico, con focus su
`stop.js` → `tripDetails.js` → `gtfsTripResolver.php`.

Principio guida adottato ovunque: **un dato realtime assente è meglio di un
dato realtime sbagliato**.

---

## 1. Modello del problema

Tre fonti dati con identificatori e orari diversi:

| Fonte | Contenuto | Formato ID | Formato orario |
|---|---|---|---|
| GTFS statico (DB) | Corse pianificate | `AUT_ACTV_794_*` | `HH:MM:SS`, ore >24 dopo mezzanotte |
| Feed passages ACTV | Stime per fermata (`/passages/{id}-web-aut`) | `AUT_LL_793_*` o assente | `HH:MM` o countdown `"N'"` |
| Feed GTFS-RT veicoli | Posizioni/trip attivi | `AUT_LL_793_*` | — |

Il feed "passages" di una fermata N elenca solo i timing point **da N in poi**:
non dice nulla sulla posizione del bus né sulle fermate precedenti.

---

## 2. Problemi riscontrati e fix

### 2.1 Fermate sbagliate in pagina Stop (`LIKE '%96%'`)

- **Sintomo**: cercando la fermata 96 venivano caricate 40 fermate (196, 960,
  1096...) con passaggi irrilevanti.
- **Causa**: match per sottostringa su `data_url`.
- **Fix**: `gtfsPassages.php` usa match a token
  (`CONCAT('-',data_url,'-') LIKE CONCAT('%-',?,'-%')`) + `stop_id = ?`,
  con supporto a id composti `96-1096` (stazioni a due direzioni unite).

### 2.2 Trip ID realtime ≠ GTFS (`AUT_LL_793_29893` vs `AUT_ACTV_794_29893`)

- **Sintomo**: `/trip-details?tripId=AUT_LL_793_29893` → "No trips found";
  `activeTripIds` non matchava mai → disambiguazione GTFS-RT di fatto inerte.
- **Causa**: prefissi diversi tra feed RT e GTFS statico.
- **Verifica**: il suffisso numerico è univoco per servizio (0 duplicati su
  ~27.500 corse bus).
- **Fix**:
  - `gtfsTripResolver.php`: fallback `LIKE '%_<suffix>'` se lookup esatto fallisce.
  - `ApiController::enrichRealtimeVehicleTrips`: normalizza i `trip_id` RT
    all'id GTFS (indice per suffisso), conservando l'originale in `trip_id_rt`.

### 2.3 Match per destinazione impossibile

- **Sintomo**: nessun match anche con la corsa giusta in lista.
- **Causa**: il feed RT usa denominazioni corte ("27 OTTOBRE", "RISORGIM.")
  mentre il GTFS usa headsign diversi ("Cavergnago Taliercio",
  "Risorgimento Miranese"). Il filtro per uguaglianza eliminava tutti i
  candidati.
- **Fix**: mai più filtrare per nome destinazione; match su fermata+orario,
  destinazione solo come hint. Il warning "Destinazione GTFS diversa" è
  informativo, non bloccante.

### 2.4 Orario di contesto: schedulato vs stimato (il bug dell'anticipo)

- **Sintomo**: timeline con "−12 min di anticipo" e fermate "future" già
  servite; match della corsa sbagliata.
- **Causa**: il contesto (`contextStop`/`contextTime`) viene dall'ultimo
  timing point della card cliccata sulla pagina stop. Se la card era
  **programmata**, l'orario è tabellare — ma veniva confrontato con le
  **stime RT**. Una corsa in ritardo può avere stima capolinea coincidente
  con lo schedulato della corsa successiva → match spurio.
  Esempio reale: corsa 22:49+2min (stima capolinea 23:04) fusa nella pagina
  della corsa schedulata 23:04.
- **Fix**: `stop.js` propaga `contextRealtime: '1'|'0'` (URL +
  sessionStorage); `tripDetails.js` con `realtime === false` salta il match
  per vicinanza oraria — conta solo l'identità (trip_id esatto/suffisso).

### 2.5 Tolleranza oraria troppo stretta

- **Sintomo**: "No matching trip found" con candidati corretti in lista.
- **Causa**: tolleranza ±3 min; la stima RT al capolinea si aggiorna di
  qualche minuto tra il click sulla card e il caricamento della pagina.
- **Fix**: tolleranza ±10 min, vince il candidato più vicino **se univoco**;
  a parità si accettano solo firme fermata+orario identiche (duplicati feed).

### 2.6 Fit-check GTFS che rifiutava il match esatto

- **Sintomo**: `AUT_ACTV_794_27709` scartato pur essendo la corsa richiesta.
- **Causa**: `timingPointsFitGtfs` (sottosequenza ordinata ≥60%) applicato
  anche al match per identità. Il feed RT può legittimamente differire dal
  piano (fermate saltate, deviazioni).
- **Fix**: match esatto/suffisso accettato sempre; il fit-check resta solo
  sui candidati euristici. `timingPointsFitRatio` esposto nei log di debug.

### 2.7 Linee circolari: `matches.shift()`

- **Sintomo**: 32H (Ospedale → … → Ospedale): l'arrivo al capolinea veniva
  assegnato alla *prima* occorrenza della fermata → countdown sulla partenza.
- **Fix** (`mergeStops`): con occorrenze ripetute si assegna il punto RT
  all'occorrenza con orario programmato più vicino.

### 2.8 Varianti di percorso diverse fuse insieme

- **Sintomo**: minuti non monotoni (…14, 18, poi di nuovo 6, 7…) e fermate
  "PASSATE" inesistenti nella variante RT.
- **Causa**: la linea 6 ha più varianti; il match per contesto sceglieva una
  corsa con ordine fermate diverso da quello visualizzato.
- **Fix**: `timingPointsFitRatio` — i punti RT devono formare una
  sottosequenza ordinata delle fermate GTFS (≥60% se confrontabile; se zero
  nomi in comune il check non è applicabile e passa).

### 2.9 `data_url` null → crash e "PASSATO" spurio

- **Sintomo**: `TypeError: can't access property "split", stop.data_url is
  null` (fermata 11380 Liberta' Fincantieri).
- **Fix**: fallback a `stop.stop_id`. Inoltre l'assenza di `data_url` rende
  l'enrichment impossibile → vedi 2.11.

### 2.10 La falsa equivalenza "senza realtime = passata"

- **Sintomo**: `PASSATO (23:01)*` su una fermata futura; con feed assente
  TUTTE le fermate risultavano passate.
- **Causa**: `isPrevious = !hasRealTime`.
- **Fix**: "passata" solo se l'orario è trascorso (confronto circolare mod
  24h per gestire gli orari GTFS >24:00). Le fermate future senza RT
  mostrano `HH:MM*` e hanno stile `no-rt` (grigio chiaro).

### 2.11 Enrichment delle fermate precedenti: la corsa successiva

- **Sintomo**: "parte da VENEZIA 5 minuti dopo essere passato a Giovannacci".
- **Causa**: `getPreviousStopsRealTime` interroga il feed di ogni fermata
  precedente; il feed elenca i passaggi *futuri schedulati*, compresa la
  corsa successiva, e il resolver temporale (`fetchTripId` su penultimo
  punto) poteva attribuirla allo stesso trip_id.
- **Fix**: guard di monotonicità — il passaggio a una fermata precedente non
  può essere più tardi dell'arrivo al primo punto RT (tolleranza 3 min).
  `toAbsMinutes` gestisce entrambi i formati (`"4'"` e `"HH:MM"`).

### 2.12 "Tutto prima del primo punto RT = passato" — euristica sbagliata

- **Tentativo fallito**: marcare passate tutte le fermate precedenti al
  primo punto RT, ragionando che il bus le avesse già servite.
- **Perché è sbagliato**: il feed parte dalla fermata *interrogata*, non
  dalla posizione del bus. Con bus in anticipo/non partito, le fermate
  precedenti sono legittimamente future.
- **Semantica finale**:
  - `PASSATO` = orario trascorso (circolare) — niente inferenze;
  - `HH:MM*` grigio = futuro senza conferma RT;
  - `N MIN` verde = stima RT;
  - blu = fermata selezionata.

### 2.13 Performance `/api/lines-shapes` → 504

- **Causa**: snapping OSRM on-the-fly per ~96 linee nella vista completa +
  mancanza di indice su `trips.trip_id` (query da 18.4s).
- **Fix**: snap solo su richieste filtrate; vista completa da cache JSON;
  `idx_trips_trip_id` aggiunto (0.04s). L'indice sopravvive agli update GTFS
  perché le staging tables usano `CREATE TABLE ... LIKE`.

---

## 3. Stato finale della pipeline

1. `stop.js` — `mergePassages`: chiave linea+destinazione normalizzata
   (`normalizeDestination`: minuscole, no accenti, no suffissi corsia),
   candidati attivi GTFS-RT ordine-preservanti, fallback nearest-in-time
   circolare con tiebreak su `stationId`, corse successive non più
   soppressi (copertura ±10 min).
2. `tripDetails.js` — selezione: esatto → suffisso unico → contesto
   (solo se `realtime !== false`, ±10 min, univoco) → fit-check sui soli
   candidati euristici → altrimenti niente RT.
3. `mergeStops`: occorrenze ripetute per vicinanza oraria, `data_url` null
   tollerato, `scheduled_arrival`/`rtTimeline` tracciati.
4. Rendering: stato derivato dal tempo reale (circolare), monotonicità
   garantita per costruzione.

## 4. Limiti residui noti

- Contesto stale oltre ~10 min (reload molto dopo il click): può vincere la
  corsa adiacente. Il warning "No matching trip" ora logga `gtfsFitRatio` e
  `contextStopDiffMin` per diagnosi.
- Il feed può continuare a mostrare un passaggio schedulato a una fermata
  già servita (bus in anticipo): limite del feed, non del merge.
- Risoluzione trip via `fetchTripId` resta temporale (penultimo punto):
  ambigua con corse ravvicinate in ritardo uniforme — mitigata da
  `assignedTripIds` esclusivi, non eliminata.
- `contextIsRealtime` assente su link vecchi → comportamento legacy (match
  per contesto comunque consentito).

## 5. Correzione successiva: enrichment incoerente delle fermate precedenti

Caso osservato su `AUT_ACTV_794_27711`, fermata selezionata `611`:
Venezia e Santa Chiara mostravano **11 MIN**, seguite da Giovannacci
Ulloa a **8 MIN**. La tolleranza di 3 minuti descritta in 2.11 consentiva
esattamente questa inversione; il resolver temporale usato separatamente
per ogni fermata non garantiva l'identità della corsa.

Negli snapshot acquisiti durante la diagnosi, il feed di Giovannacci
conteneva una corsa alle 23:22 con arrivo a Risorgimento alle 23:35 e una
successiva alle 23:36 con arrivo alle 23:48. Il feed di Venezia riportava
una partenza alle 23:25 con Giovannacci alle 23:36 e Risorgimento alle
23:48. Santa Chiara includeva anche corse dirette a Venezia.

L'enrichment ora sostituisce il criterio di 2.11 con un match conservativo:

- stessa linea e `real === true`;
- un solo candidato con un'unica occorrenza del primo punto della timeline;
- seguito completo con le stesse fermate, nello stesso ordine e agli stessi
  orari della timeline realtime già selezionata;
- orario della fermata precedente non successivo al primo punto realtime,
  senza tolleranza, anche attraverso mezzanotte e con ore GTFS oltre 24.

Le fermate già associate nella timeline principale e quelle prive di
`data_url` non vengono interrogate. Le risposte ricevute dopo un refresh
vengono ignorate. Nessun nuovo `fetchTripId` viene usato per l'enrichment.

Con stime discordanti tra i feed o candidati ambigui si mantiene il dato
GTFS senza conferma realtime. Questo controllo protegge l'enrichment;
non elimina i limiti del matching iniziale della timeline descritti sopra.

## 6. Falso ritardo di 60 minuti sulla 31H

Caso `AUT_ACTV_794_26811`, fermata `270-1270`, contesto Mestre Centro B7
alle 23:47. Il GTFS della corsa richiesta prevede Bissuola Cadorna alle
**22:44**, mentre lo snapshot realtime indica **23:44**. La corsa GTFS
`AUT_ACTV_794_26812` prevede invece Cadorna alle **23:44**: il `+60 min`
derivava quindi dall'associazione di due corse diverse.

Due percorsi permettevano l'errore: la pagina stop privilegiava gli ID
presenti nel feed veicoli senza limitarne la distanza oraria; trip-details
accettava direttamente il contesto prima di risolvere l'identità GTFS.

Correzioni:

- L'associazione euristica sulla pagina stop considera solo corse entro
  10 minuti, anche quando l'ID compare tra quelli attivi. Un ID fornito
  esplicitamente dalla sorgente resta preservato. Ritardi maggiori non
  vengono dedotti dalla sola presenza nel feed veicoli.
- Trip-details risolve i candidati prima di usare il contesto. Un candidato
  con ID risolto diverso non può essere riattribuito alla corsa nell'URL
  dalla sola corrispondenza di fermata e orario.
- Quando viene fornito lo stopId della fermata interrogata, il resolver
  riceve il primo punto del feed con il suo orario, anziché l'orario del
  capolinea. Il contesto di navigazione conserva il punto originale.
- Se non rimane una stima associata, l'orario realtime e il ritardo
  precedentemente mostrati vengono cancellati.

Un vecchio URL con ID errato mantiene la corsa GTFS richiesta, senza
fonderla con il passaggio di una corsa diversa. Nel caso osservato il link
con ID `AUT_ACTV_794_26812` identifica la corsa programmata alle 23:44.

## 7. Capolinea finale assegnato alla partenza nelle linee circolari

Nella 31H, Mestre Centro B7 compariva a **20 MIN** in apertura della
timeline, prima di San Marco Musatti a **1 MIN**, mentre il capolinea
finale rimaneva senza realtime. Il feed interrogato a Cadorna conteneva
solo la parte conclusiva della corsa.

Il fix descritto in 2.7 confrontava più punti realtime con lo stesso nome,
ma consumava subito un punto unico alla prima occorrenza GTFS. Non
distingueva quindi il capolinea iniziale da quello finale quando il feed
riportava solo l'arrivo.

`mergeStops` ora allinea le due sequenze nel loro insieme: massimizza il
numero di punti associati preservando l'ordine; fra allineamenti con lo
stesso numero di punti sceglie quello con la minore distanza oraria
circolare. Le occorrenze non associate mantengono l'orario programmato.
Il punto finale di Mestre Centro viene così assegnato dopo Cadorna e
Colombo. I test coprono anche una singola occorrenza realtime, ritardi
elevati e orari GTFS oltre mezzanotte.

## 8. Pertini Gavagnin: identità della fermata nei feed uniti

Il feed `258-1258-web-aut` contiene entrambe le direzioni. Nello snapshot
acquisito il passaggio 31H diretto a Mestre Centro riportava `stop: "1258"`.
L'enrichment verificava il seguito della corsa ma ignorava questo ID,
assegnando lo stesso passaggio sia al GTFS `258` prima di Pertini Carrer,
sia al GTFS `1258` dopo Carrer. Da qui la sequenza **6 MIN, 1 MIN, 6 MIN**.

Ora l'enrichment verifica l'uguaglianza fra `trip.stop` e `gtfs.stop_id`
prima di confrontare linea e seguito. Se il passaggio non espone l'ID,
un feed unito non viene usato per scegliere una direzione dal solo nome.
Resta possibile usare un feed con un solo ID coincidente con quello GTFS.

Il caricamento della timeline conserva l'ID del passaggio nel primo punto
realtime e lo passa al resolver, al posto dell'ID composto della stazione.
L'allineamento usa l'ID quando disponibile; i punti successivi, che nel
feed acquisito hanno soltanto il nome, continuano a usare ordine e orari.
Gli aggiornamenti del DOM individuano inoltre l'indice preciso della
fermata, evitando di aggiornare sempre la prima riga quando uno stesso ID
ricorre più volte nella corsa.
