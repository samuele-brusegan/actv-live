## Data Model

### Stops

**Url:** https://oraritemporeale.actv.it/aut/backend/page/stops

```json
[
    {
        //Id (id1-id2-web-aut)
        "name": "4824-4825-web-aut", 

        //Name [id1] [id2]
        "description": "Spinea Centro Sportivo [4824] [4825]", 
        "longitude": 12.1491775512695,
        "latitude": 45.4879188537598,

        //Is capolinea?
        "terminal": true,
        "lines": [
            {
                "id": 5013,
                "alias": "7E",
                "operator": "ACTV",
                "type": "bus",
                "stops": [],
                "tags": [
                    "EN" 
                    //"US" -> ???, "UM" -> urbano?, "EN" -> ???
                ]
            },
            ...
        ],
        "types": [
            "bus"
        ],
        "tags": [
            "EN",
            "US"
        ]
    }
]
```

### Lines

**Url:**

*if id1 is null:* https://oraritemporeale.actv.it/aut/backend/passages/id1-web-aut <br/>
*else:* https://oraritemporeale.actv.it/aut/backend/passages/id1-id2-web-aut

```json
[
    {
        //identifier of the LINE (no stop or vehicle)
        "lineId": 5198, 

        "line": "GSB_US",
        "path": "CREA-FORNASE",
        "destination": "SFMR Spinea",
        "time": "17:32",
        "stop": "4825",
        "real": true, //Is real time?
        "timingPoints": [
            {
                "time": "17:32",
                "stop": "Spinea Centro Sportivo"
            },
            {
                "time": "17:33",
                "stop": "Liberta' Costituzione"
            },
            {
                "time": "17:40",
                "stop": "Crea Civico 21"
            },
            ...
        ],
        "vehicle": null, //Always null, why?
        "operator": null //Always null, why?
    },
]
```
