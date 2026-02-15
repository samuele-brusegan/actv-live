Live Bus Tracker Map — Walkthrough
Cosa è stato fatto
Nuova pagina /live-map che mostra la posizione in tempo reale di tutti i bus ACTV in servizio su mappa Leaflet.

File modificati
File	Tipo	Dettaglio
ApiController.php
MODIFICATO	Riscritto 
gtfsBusesRunningNow()
 (inline, route_id, time/day dinamici), aggiunto 
busPosition()
routes.php
MODIFICATO	+2 rotte: /api/bus-position, /live-map
Controller.php
MODIFICATO	+metodo 
liveMap()
liveBusMap.php
NUOVO	View con header, filtri, mappa, status bar
liveBusMap.css
NUOVO	Stili fullscreen, icone bus, popup, dark mode
liveBusMap.js
NUOVO	Logica core: async loading, interpolazione, filtri
Come funziona
/api/bus-position
/api/gtfs-bnr
liveBusMap.js
/api/bus-position
/api/gtfs-bnr
liveBusMap.js
Filtra client-side → lancia solo i fetch necessari
Interpola posizione → piazza marker
Marker appare subito
par
[Max 6 in parallelo]
GET (time/day auto)
{buses: [{trip_id, route_short_name, route_id, ...}]}
GET ?tripId=A
[{stop_name, stop_lat, stop_lon, arrival_time, stop_sequence}]
GET ?tripId=B
[...]
Punti chiave:

Concorrenza controllata: max 6 fetch simultanei via 
parallelPool
Interpolazione: posizione lineare tra due fermate in base all'ora corrente
Filtri: route_short_name, trip_id, route_id, trip_headsign — debounce 400ms
Auto-refresh: ogni 60 secondi
Contatore live: "12 / 45" aggiornato ad ogni risposta
Come testare
Navigare a https://actv-live.test/live-map
I bus dovrebbero apparire progressivamente sulla mappa
Digitare "5E" nel filtro → solo linea 5E visibile
Cliccare su un marker → popup con linea, direzione, prossima fermata