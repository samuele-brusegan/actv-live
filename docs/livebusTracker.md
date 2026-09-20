# Nota di compatibilità: Live Bus Tracker

Questo file era un walkthrough della prima implementazione della mappa realtime
ed è mantenuto solo per non spezzare eventuali riferimenti esterni.

La documentazione operativa aggiornata è in
[`features/live-bus-map/README.md`](features/live-bus-map/README.md). In
particolare, non è più corretto descrivere `/api/gtfs-bnr` come unica sorgente
dei mezzi: la mappa corrente usa anche `/api/realtime/vehicles` e
`/api/navigation/vehicles`, con caricamento progressivo delle shape.
