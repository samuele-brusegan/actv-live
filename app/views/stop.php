<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dettaglio Fermata - ACTV</title>
    <?php
    require COMMON_HTML_HEAD;
    ?>
    <link rel="stylesheet" href="/css/style.css" />
    <link rel="stylesheet" href="/css/stop.css">
</head>
<body>

    <!-- Header -->
    <div class="header-green">
        <div style="height: 20px;">
             <a href="javascript:history.back()" style="color: white; text-decoration: none; font-size: 24px;">&larr;</a>
        </div> 
        <div class="header-title" id="station-name">Caricamento...</div>
        <div class="header-subtitle" id="station-id"></div>
        <button class="favorite-button" id="favorite-btn" onclick="toggleFavorite()" title="Aggiungi ai preferiti">
            ★
        </button>
    </div>

    <!-- Contenuto Principale -->
    <div class="main-content pb-5">
        
        <div class="section-title">Prossimi Passaggi</div>

        <div id="noticeboard"></div>
        
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

        // Favorite management functions
        function getFavorites() {
            const favorites = localStorage.getItem('favorite_stops');
            return favorites ? JSON.parse(favorites) : [];
        }

        function isFavorite() {
            const favorites = getFavorites();
            // Check if any favorite has this ID (could be in ids array)
            return favorites.some(fav => {
                if (fav.ids && Array.isArray(fav.ids)) {
                    return fav.ids.some(id => stationId.includes(id) || id === stationId.split('-')[0]);
                }
                return fav.id === stationId || stationId.includes(fav.id);
            });
        }

        function toggleFavorite() {
            let favorites = getFavorites();
            const favoriteBtn = document.getElementById('favorite-btn');
            
            if (isFavorite()) {
                // Remove from favorites
                favorites = favorites.filter(fav => {
                    if (fav.ids && Array.isArray(fav.ids)) {
                        return !fav.ids.some(id => stationId.includes(id) || id === stationId.split('-')[0]);
                    }
                    return fav.id !== stationId && !stationId.includes(fav.id);
                });
                favoriteBtn.classList.remove('favorited');
                favoriteBtn.title = 'Aggiungi ai preferiti';
            } else {
                // Add to favorites
                // Parse IDs from stationId (format: "4825" or "4825-4826")
                const ids = stationId.split('-');
                favorites.push({
                    id: ids[0],
                    ids: ids,
                    name: stationName || `Fermata ${stationId}`
                });
                favoriteBtn.classList.add('favorited');
                favoriteBtn.title = 'Rimuovi dai preferiti';
            }
            
            localStorage.setItem('favorite_stops', JSON.stringify(favorites));
        }

        function updateFavoriteButton() {
            const favoriteBtn = document.getElementById('favorite-btn');
            if (isFavorite()) {
                favoriteBtn.classList.add('favorited');
                favoriteBtn.title = 'Rimuovi dai preferiti';
            } else {
                favoriteBtn.classList.remove('favorited');
                favoriteBtn.title = 'Aggiungi ai preferiti';
            }
        }

        if (!stationId) {
            document.getElementById('station-name').innerText = "Errore: ID mancante";
            document.getElementById('loading').style.display = 'none';
        } else {
            console.log(urlParams.get('name'), urlParams.get('id'));
            console.log(stationName);
            document.getElementById('station-name').innerText = stationName ?? "Fermata " + stationId;
            document.getElementById('station-id').innerText = stationId;
        }

        async function getPassages() {
            if (!stationId) return;

            try {
                let response = await fetch(`https://oraritemporeale.actv.it/aut/backend/passages/${stationId}-web-aut`);
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

        async function updateNoticeboard() {
            if (!stationId) return;
            
            try {
                let response = await fetch(`https://oraritemporeale.actv.it/aut/backend/page/${stationId}-web-aut`);
                if (!response.ok) {
                    throw new Error(`Response status: ${response.status}`);
                }

                let rs = await response.json();
                
                let text = rs.text;

                document.getElementById('noticeboard').innerHTML = `
                <div class="card mb-2">
                    <div class="sciopero">
                        <h5 class="card-title">Attenzione</h5>
                        <p class="card-text">${text}</p>
                    </div>
                </div>
                ` || "";

            } catch (error) {
                console.error(error.message);
                document.getElementById('loading').innerText = "Errore nel caricamento dei passaggi.";
                return [];
            }
        }
        
        async function loadPassages() {
            if (!stationId) return;
            
            let passages = await getPassages();
            document.getElementById('loading').style.display = 'none';
            
            let listContainer = document.getElementById('passages-list');
            listContainer.innerHTML = "";

            if(passages.length === 0 ) {
                if (passages.message === null || passages.message === undefined){
                    listContainer.innerHTML = "<p class='text-center text-muted'>Nessun passaggio previsto.</p>";
                } else {
                    listContainer.innerHTML = "<p class='text-center text-muted'>" + passages.message + "</p>";
                }
                //document.getElementById('station-name').innerText = "Fermata " + stationId;
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
                let lineNameRaw = p.line; // e.g. "GSB_US" or "7E" if lucky
                let dest = p.destination;
                let time = p.time;
                let isReal = p.real;
                let stop = p.stop ?? stationId;
                
                
                //Split line name
                let lineNameParts = lineNameRaw.split("_");
                let lineName = lineNameParts[0];
                let lineTag = lineNameParts[1];

                
                let badgeColor = "badge-red"
                if(lineTag === "US" || lineTag === "UN" || lineTag === "EN") {
                    badgeColor = "badge-blue"
                }
                if(lineName.startsWith("N")) {
                    badgeColor = "badge-night"
                }

                
                let timeHtml = isReal 
                    ? /*html*/`
                    <div class="d-flex align-items-center">
                        <div class="real-time-indicator"></div>
                        <span class="time-badge real-time">${time}</span>
                    </div>`
                    : `<span class="time-badge scheduled">${time}</span>`;

                let timeStr = isReal ? time + " min" : time;
                
                // Escape strings for JS
                const safeLineName = lineName.replace(/'/g, "\\'");
                const safeLineTag = lineTag.replace(/'/g, "\\'");
                const safeDest = dest.replace(/'/g, "\\'");
                const safeStationId = stationId.replace(/'/g, "\\'");
                const safeTimeStr = timeStr.replace(/'/g, "\\'");

                listContainer.innerHTML += /*html*/`
                <div class="passage-card" onclick="window.location.href='/trip-details?line=' + encodeURIComponent('${safeLineName}_${safeLineTag}') + '&dest=' + encodeURIComponent('${safeDest}') + '&stopId=' + encodeURIComponent('${safeStationId}') + '&time=' + encodeURIComponent('${safeTimeStr}')" style="cursor: pointer;">
                    <div class="d-flex align-items-center">
                        <div class="line-badge ${badgeColor}">${lineName}</div>
                        <div class="passage-info">
                            <span class="passage-dest"><b>${dest}</b></span><br/>
                            <span class="passage-meta">Presso <b>${stop}</b></span>
                        </div>
                    </div>
                    <div>
                        ${timeHtml}
                    </div>
                </div>
                `;
            });
        }

        async function init() {
            if (!stationId) return;
            
            // Set ID in header temporarily
            document.getElementById('station-id').innerText = stationId;
            
            // Update favorite button state
            updateFavoriteButton();
            
            await loadPassages();
            await updateNoticeboard();

            // Refresh every 15 seconds
            setInterval(loadPassages, 15000);
        }

        window.onload = init;
    </script>

</body>
</html>
