# Live Bus Tracking

Il modulo Live Bus Tracker (`/live-map`) permette di vedere la posizione stimata di tutti i bus in circolazione. Poiché i bus non trasmettono le coordinate GPS ogni secondo, il sistema utilizza una **interpolazione lineare** basata sull'orario previsto.

## Strategia di Caricamento Asincrono

Dato che caricare centinaia di bus contemporaneamente rallenterebbe l'interfaccia, `liveBusMap.js` utilizza un caricamento progressivo:

1.  **Chiamata Iniziale**: `/api/gtfs-bnr` restituisce la lista di tutti i `trip_id` attivi in questo momento.
2.  **Parallelismo Controllato**: Viene utilizzata una funzione `parallelPool` che esegue le richieste per le posizioni singole con una concorrenza massima di 6 alla volta.
3.  **Visualizzazione Progressiva**: Appena arriva la risposta per un bus, il marker viene aggiunto alla mappa. L'utente vede i bus apparire uno alla volta, rendendo l'app reattiva da subito.

---

## Logica di Interpolazione delle Posizioni

Il bus viene posizionato confrontando l'orario del server (`nowSec`) con gli orari di arrivo alle fermate.

### 1. Identificazione del Segmento
Il sistema scorre l'elenco delle fermate del trip finché non trova due fermate (A e B) tali che:
`Orario Fermata A <= Ora Attuale < Orario Fermata B`

### 2. Calcolo del Progresso
Viene calcolato quanto tempo è passato rispetto alla durata totale del segmento:
`progress = (Ora Attuale - Orario A) / (Orario B - Orario A)`
Se il bus deve impiegare 4 minuti tra A e B, e sono passati 2 minuti, il progresso è `0.5` (50%).

### 3. Calcolo Coordinate
Le nuove coordinate (Lat, Lng) sono calcolate linearmente:
`Lat_Nuova = Lat_A + (Lat_B - Lat_A) * progress`
`Lng_Nuova = Lng_A + (Lng_B - Lng_A) * progress`

---

## Funzionalità lato Client

### Filtri Dinamici
L'utente può digitare una linea (es. "5E") o un ID nella barra di ricerca.
- **Logica**: Il filtro viene applicato *prima* di lanciare i fetch delle singole posizioni. Se un bus non corrisponde al filtro, la sua richiesta di posizione non viene nemmeno effettuata.
- **Debounce**: Il filtro attende 400ms dall'ultimo tasto premuto prima di ricaricare i dati, per non sovraccaricare il server.

### Auto-Refresh
La mappa si aggiorna automaticamente ogni 60 secondi per riflettere i nuovi bus entrati in servizio o quelli che hanno terminato la corsa.
