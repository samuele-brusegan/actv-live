# Mappa realtime

Questa pagina mantiene il collegamento storico per la documentazione della
mappa bus. Il riferimento aggiornato è:

- [Panoramica della mappa realtime](features/live-bus-map/README.md)
- [Caricamento asincrono e filtri](features/live-bus-map/async-loading.md)
- [Posizione e shape](features/live-bus-map/position-and-shape.md)

La mappa corrente usa prima `/api/realtime/vehicles?service=automobilistico`
per le posizioni GTFS-RT, `/api/navigation/vehicles` per la navigazione e le
API schedulate/geometry come fallback e dettaglio. La versione precedente che
descriveva `/api/gtfs-bnr` come unica sorgente realtime è superata.
