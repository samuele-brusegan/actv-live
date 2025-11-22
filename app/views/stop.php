<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dettaglio Fermata - ACTV</title>
    <?php
    require COMMON_HTML_HEAD;
    ?>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #F5F5F5;
            margin: 0;
            padding: 0;
        }

        /* Header Verde */
        .header-green {
            background: #009E61;
            padding: 2rem 1.5rem 6rem;
            color: white;
            border-bottom-left-radius: 0;
            border-bottom-right-radius: 0;
            clip-path: polygon(0 0, 100% 0, 100% 75%, 0 100%);
            margin-bottom: -4rem;
        }

        .header-title {
            font-family: 'Inter', sans-serif;
            font-weight: 800;
            font-size: 28px;
            line-height: 1.2;
            margin-top: 1rem;
        }
        
        .header-subtitle {
            font-family: 'SF Pro', sans-serif;
            font-weight: 500;
            font-size: 16px;
            opacity: 0.9;
            margin-top: 0.5rem;
        }

        /* Sezioni Titoli */
        .section-title {
            font-family: 'SF Pro', sans-serif;
            font-weight: 590;
            font-size: 20px;
            color: #000000;
            margin: 1.5rem 1.5rem 0.5rem;
        }

        /* Card Passaggio */
        .passage-card {
            background: #FFFFFF;
            box-shadow: 2px 0px 9.7px -4px rgba(0, 0, 0, 0.24);
            border-radius: 15px;
            padding: 1rem;
            margin: 0.5rem 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: relative;
        }

        .passage-info {
            display: flex;
            flex-direction: column;
        }

        .passage-dest {
            font-family: 'SF Pro', sans-serif;
            font-weight: 700;
            font-size: 18px;
            color: #000000;
            margin-bottom: 0.2rem;
        }

        .passage-meta {
            font-family: 'SF Pro', sans-serif;
            font-weight: 510;
            font-size: 14px;
            color: #666;
        }

        /* Badge Linea */
        .line-badge {
            background: #0152BB;
            border-radius: 7px;
            color: #FFFFFF;
            font-family: 'SF Pro', sans-serif;
            font-weight: 700;
            font-size: 18px;
            padding: 4px 10px;
            min-width: 45px;
            text-align: center;
            display: inline-block;
            margin-right: 15px;
        }
        
        .time-badge {
            font-family: 'SF Pro', sans-serif;
            font-weight: 700;
            font-size: 18px;
            color: #009E61;
        }
        
        .time-badge.real-time {
            color: #009E61;
        }
        
        .time-badge.scheduled {
            color: #333;
        }
        
        .real-time-indicator {
            width: 8px;
            height: 8px;
            background-color: #009E61;
            border-radius: 50%;
            display: inline-block;
            margin-right: 5px;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% {
                transform: scale(0.95);
                box-shadow: 0 0 0 0 rgba(0, 158, 97, 0.7);
            }
            70% {
                transform: scale(1);
                box-shadow: 0 0 0 10px rgba(0, 158, 97, 0);
            }
            100% {
                transform: scale(0.95);
                box-shadow: 0 0 0 0 rgba(0, 158, 97, 0);
            }
        }

        /* Loading */
        #loading {
            text-align: center;
            padding: 2rem;
            color: #666;
        }

    </style>
</head>
<body>

    <!-- Header -->
    <div class="header-green">
        <div style="height: 20px;">
             <a href="javascript:history.back()" style="color: white; text-decoration: none; font-size: 24px;">&larr;</a>
        </div> 
        <div class="header-title" id="station-name">Caricamento...</div>
        <div class="header-subtitle" id="station-id"></div>
    </div>

    <!-- Contenuto Principale -->
    <div class="main-content pb-5">
        
        <div class="section-title">Prossimi Passaggi</div>
        
        <div id="loading">Caricamento passaggi...</div>

        <div id="passages-list">
            <!-- Popolato via JS -->
        </div>
        
        <div class="text-center mt-4">
            <button class="btn btn-outline-secondary rounded-pill px-4 py-2" onclick="location.reload()">
                Aggiorna
            </button>
        </div>

    </div>

    <script>
        // Get ID from URL
        const urlParams = new URLSearchParams(window.location.search);
        const stationId = urlParams.get('id');
        const stationName = urlParams.get('name');

        if (!stationId) {
            document.getElementById('station-name').innerText = "Errore: ID mancante";
            document.getElementById('loading').style.display = 'none';
        } else {
            if(stationName) {
                document.getElementById('station-name').innerText = stationName;
            } else {
                document.getElementById('station-name').innerText = "Fermata " + stationId;
            }
            document.getElementById('station-id').innerText = stationId;
        }

        async function getPassages() {
            if (!stationId) return;

            try {
                let response = await fetch(`https://oraritemporeale.actv.it/aut/backend/passages/${stationId}`);
                if (!response.ok) {
                    throw new Error(`Response status: ${response.status}`);
                }

                let rs = await response.json();
                return rs || [];

            } catch (error) {
                console.error(error.message);
                document.getElementById('loading').innerText = "Errore nel caricamento dei passaggi.";
                return [];
            }
        }
        
        // Helper to get station name (optional, if we want to display it nicely)
        // Since the passages endpoint might not return the station name, we might need to fetch it or pass it.
        // For now, let's try to infer it or just show the ID if name is missing.
        // Actually, the passages endpoint usually returns a list of passages.
        // Let's check if we can get the name from the first passage or if we need to fetch station info.
        // The docs/data.md example for passages has "stop": "4825" and "timingPoints" with "stop": "Spinea Centro Sportivo".
        // Maybe we can use that?
        
        async function init() {
            if (!stationId) return;
            
            // Set ID in header temporarily
            document.getElementById('station-id').innerText = stationId;
            
            let passages = await getPassages();
            document.getElementById('loading').style.display = 'none';
            
            let listContainer = document.getElementById('passages-list');
            listContainer.innerHTML = "";

            if(passages.length === 0) {
                listContainer.innerHTML = "<p class='text-center text-muted'>Nessun passaggio previsto.</p>";
                document.getElementById('station-name').innerText = "Fermata " + stationId;
                return;
            }
            
            // Try to extract station name from the first passage if available
            // In the example: "timingPoints": [{"stop": "Spinea Centro Sportivo" ...}]
            // But timingPoints are the schedule.
            // Let's just use the ID for now or "Fermata" + ID.
            // Ideally we would have passed the name in the URL or fetched the single station info.
            // But there is no single station info endpoint documented, only "stops" (all).
            // We could fetch all stops and find this one, but that's heavy.
            // Let's check if the user passed 'name' in URL query params?
            // I didn't add it in home.php link.
            // document.getElementById('station-name').innerText = "Fermata " + stationId;

            passages.forEach(p => {
                let lineName = p.line; // e.g. "GSB_US" or "7E" if lucky
                let dest = p.destination;
                let time = p.time;
                let isReal = p.real;
                
                let timeHtml = isReal 
                    ? `<div class="d-flex align-items-center"><div class="real-time-indicator"></div><span class="time-badge real-time">${time}</span></div>`
                    : `<span class="time-badge scheduled">${time}</span>`;

                listContainer.innerHTML += `
                <div class="passage-card">
                    <div class="d-flex align-items-center">
                        <div class="line-badge">${lineName}</div>
                        <div class="passage-info">
                            <span class="passage-dest">${dest}</span>
                            <span class="passage-meta">Verso ${dest}</span>
                        </div>
                    </div>
                    <div>
                        ${timeHtml}
                    </div>
                </div>
                `;
            });
        }

        window.onload = init;
    </script>

</body>
</html>
