# Format JSON

## Stops

- endpoint: `https://oraritemporeale.actv.it/aut/backend/page/stops`

```json
[
    {
        "name": "2701-2951-3564-web-aut",
        "description": "Mogliano Centro [2701] [2951] [3564]",
        "longitude": 12.23476791381836,
        "latitude": 45.56003189086914,
        "terminal": true,
        "lines": [
            {
                "id": 5014,
                "alias": "8E",
                "operator": "ACTV",
                "type": "bus",
                "stops": [],
                "tags": [
                    "EN"
                ]
            }
        ],
        "types": [
            "bus"
        ],
        "tags": [
            "EN"
        ]
    },
    {
        "name": "704-web-aut",
        "description": "Ca' Solaro [704]",
        "longitude": 12.267489433288574,
        "latitude": 45.520660400390625,
        "terminal": false,
        "lines": [],
        "types": [],
        "tags": []
    },
    {
        "name": "1055-web-aut",
        "description": "Gazzera Alta Chiesa [1055]",
        "longitude": 12.218250274658203,
        "latitude": 45.49480438232422,
        "terminal": false,
        "lines": [
            {
                "id": 5056,
                "alias": "10",
                "operator": "ACTV",
                "type": "bus",
                "stops": [],
                "tags": [
                    "UM"
                ]
            }
        ],
        "types": [
            "bus"
        ],
        "tags": [
            "UM"
        ]
    },
]
```

## Incoming Trips (stop specific)

- endpoint: `https://oraritemporeale.actv.it/aut/backend/passages/${dataURL}`
- Data URL format: `${stopId}-web-aut`
- Stop ID examples: 
    - `611` (Giovannacci Ulloa)
    - `2701-2951-3564` (Mogliano)
    - `2570-2571` (Maerne Centro)

```json
[
    {
        "lineId": 5240,
        "line": "6L_UM",
        "path": "p.zza S.Antonio-p.zza Mercato-Concordia",
        "destination": "CORRENTI",
        "time": "departure",
        "stop": null,
        "real": false, /* Equivalent to GTFS */
        "timingPoints": [
            {
                "time": "13:14",
                "stop": "Giovannacci Ulloa"
            },
            {
                "time": "13:15",
                "stop": "Lavelli Paolucci"
            },
            {
                "time": "13:16",
                "stop": "Sant'Antonio Municipio"
            },
            ...
        ],
        "vehicle": null,
        "operator": null
    },
    {
        "lineId": 5284,
        "line": "18_UM",
        "path": "Lavelli-p.zza S.Antonio-p.zza Mercato-Rinascita-Beccaria-Pasini-Padana-Colombara-Ca' Sabbioni",
        "destination": "CA'SABBIONI",
        "time": "4'", 
        "stop": null,
        "real": true, /* Delayed (real-time) */
        "timingPoints": [
            {
                "time": "13:19",
                "stop": "Giovannacci Ulloa"
            },
            {
                "time": "13:20",
                "stop": "Lavelli Paolucci"
            },
            {
                "time": "13:20",
                "stop": "Sant'Antonio Municipio"
            },
            ...
        ],
        "vehicle": null,
        "operator": null
    },
]
```
