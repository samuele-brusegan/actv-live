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
             <a href="javascript:history.back()" style="color: white; text-decoration: none; font-size: 24px;">
                 <?= getIcon('arrow_back', 24) ?>
             </a>
        </div> 
        <div class="header-title" id="station-name">Caricamento...</div>
        <div class="header-subtitle" id="station-id"></div>
        <button class="favorite-button" id="favorite-btn" onclick="toggleFavorite()" title="Aggiungi ai preferiti">
            ★
        </button>
    </div>
    <div class="parent-wrapper">
        <div id="filter-container">
            <!-- <div class="filter-box box-red selected">21</div>
            <div class="filter-box box-blue">5E</div>
            <div class="filter-box box-night">31H</div> -->
        </div>
    </div>

    <!-- Contenuto Principale -->
    <div class="main-content pb-5">
        
        <div class="section-title">Prossimi Passaggi</div>

        <div id="noticeboard"></div>
        
        <div id="loading">Caricamento passaggi...</div>

        <!-- Time Machine Banner -->
        <div id="tm-banner" class="alert alert-warning py-2 mb-3 small d-none">
            <div class="d-flex justify-content-between align-items-center">
                <span><?= getIcon('history', 18, 'align-middle') ?> <strong>Time Machine attiva</strong></span>
                <span id="tm-current-time"></span>
            </div>
        </div>

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
        } 
        else {
            console.log(urlParams.get('name'), urlParams.get('id'));
            console.log(stationName);
            document.getElementById('station-name').innerText = stationName ?? "Fermata " + stationId;
            document.getElementById('station-id').innerText = stationId;
        }

        async function getPassages() {
            if (!stationId) return;

            try {
                let response = await fetch(`https://oraritemporeale.actv.it/aut/backend/passages/${stationId}-web-aut`, {cache: 'no-cache'});
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
        async function updateNoticeboard() {
            if (!stationId) return;
            
            try {
                let response = await fetch(`https://oraritemporeale.actv.it/aut/backend/page/${stationId}-web-aut`);
                if (!response.ok) {
                    throw new Error(`Response status: ${response.status}`);
                }

                let rs = await response.json();
                
                let text = rs.text;


                if (text === null || text === undefined || text === "") {
                    document.getElementById('noticeboard').innerHTML = "";
                    return;
                }
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
                        <span class="time-badge real-time">${time=="departure" ? "Ora" : time}</span>
                    </div>`
                    : `<span class="time-badge scheduled">${time}</span>`;

                let timeStr = isReal ? time + " min" : time;
                
                // Escape strings for JS
                const safeLineName = lineName.replace(/'/g, "\\'");
                const safeLineTag = lineTag.replace(/'/g, "\\'");
                const safeDest = dest.replace(/'/g, "\\'");
                const safeStationId = stationId.replace(/'/g, "\\'");
                const safeTimeStr = timeStr.replace(/'/g, "\\'");
                
                let url = '/trip-details?line=' + encodeURIComponent(safeLineName + "_" + safeLineTag) + 
                '&dest=' + safeDest + 
                '&stopId=' + encodeURIComponent(safeStationId) + 
                '&time=' + safeTimeStr;


                let array = ["sunday", "monday", "tuesday", "wednesday", "thursday", "friday", "saturday"];
                let today = array[window.TimeMachine ? TimeMachine.now().getDay() % 7 : new Date().getDay() % 7];
                
                let fermataTemporizzata = p.timingPoints[p.timingPoints.length - 1];
                let stopTimed = fermataTemporizzata.stop;
                let tempo = fermataTemporizzata.time;

                
                function sendToNewPage(stopTimed, busTrack, realTime, lastStop, url) {
                    sessionStorage.setItem('timedStop',stopTimed);
                    sessionStorage.setItem('busTrack', safeLineName);
                    sessionStorage.setItem('realTime', tempo);
                    sessionStorage.setItem('lastStop', dest);
                    window.location.href=url;
                }
                
                let div = document.createElement('div');
                div.className = 'passage-card';
                div.onclick = function() {
                    sendToNewPage(stopTimed, safeLineName, tempo, dest, url)
                };
                div.style.cursor = 'pointer';
                div.innerHTML = /*html*/`
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
                listContainer.appendChild(div);
            });
            
            // Set filter
            updateFilter();
        }
        
        
        function updateFilter() {
            let filter = sessionStorage.getItem('filter');
            
            let filterPassesThere = false;
            
            // Get trips
            let passageCards = document.querySelectorAll('.passage-card');
            let trips = [];
            passageCards.forEach(passageCard => {

                let tripName = passageCard.querySelector('.line-badge').innerText;
                let tripColor = passageCard.querySelector('.line-badge').classList[1].split('-')[1];

                if (!trips.find(trip => trip.tripName === tripName)) {
                    trips.push({
                        "tripName":tripName,
                        "color":tripColor,
                    });
                }
            })
            
            // Remove old filters
            let filterContainer = document.getElementById('filter-container');
            filterContainer.innerHTML = '';

            if (trips.length <= 1) return;
            
            
            // Add new filters
            trips.forEach(trip => {
                let filterBox = document.createElement('div');
                filterBox.className = 'filter-box box-' + trip.color;
                filterBox.id = trip.tripName;
                filterBox.innerText = trip.tripName;

                if (filter === trip.tripName) {
                    filterBox.classList.add('selected');
                    filterPassesThere = true;
                }
                filterBox.onclick = function() {
                    if (filterBox.classList.contains('selected')) {
                        sessionStorage.setItem('filter', null);
                        updateFilter();
                    } else {
                        sessionStorage.setItem('filter', trip.tripName);
                        updateFilter();
                    }
                };
                if (filter === trip.tripName) {
                    // document.getElementById('filter-container').insertBefore(filterBox, document.getElementById('filter-container').firstChild);
                    document.getElementById('filter-container').appendChild(filterBox);
                } else {
                    document.getElementById('filter-container').appendChild(filterBox);
                }
            });

            if (filterPassesThere) {
                passageCards.forEach(card => {
                    let tripName = card.querySelector('.line-badge').innerText;
                    
                    if (tripName !== filter) {
                        card.classList.add('hidden');
                    }
                    else {
                        card.classList.remove('hidden');
                    }
                });
            }
            else {
                passageCards.forEach(card => {
                    card.classList.remove('hidden');
                });
            }
        }
        
        function updateTimeMachineUI() {
            const banner = document.getElementById('tm-banner');
            const timeSpan = document.getElementById('tm-current-time');
            if (window.TimeMachine && TimeMachine.isEnabled()) {
                banner.classList.remove('d-none');
                timeSpan.innerText = TimeMachine.getSimTime();
            } else {
                banner.classList.add('d-none');
            }
        }

        async function init() {
            if (!stationId) return;
            
            // Set ID in header temporarily
            document.getElementById('station-id').innerText = stationId;
            
            // Update favorite button state
            updateFavoriteButton();

            // Update Time Machine UI
            updateTimeMachineUI();
            
            await loadPassages();
            // await updateNoticeboard();

            // Refresh every 15 seconds
            setInterval(loadPassages, 15000);

            // Set filter
            updateFilter();
        }

        window.onload = init;
    </script>

</body>
</html>
