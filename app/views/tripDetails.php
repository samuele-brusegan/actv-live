<!DOCTYPE html>
<html lang="it">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dettaglio Corsa - ACTV</title>
    <?php require COMMON_HTML_HEAD; ?>
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="/css/tripDetails.css">
</head>

<body>

    <!-- Header -->
    <div class="header-green">
        <div style="height: 20px;">
            <a href="javascript:history.back()" style="color: white; text-decoration: none; font-size: 24px;">&larr;</a>
        </div>

        <div class="trip-header-info">
            <div class="line-info">
                <div class="line-box line-badge" id="line-number">--</div>
            </div>
            <div class="direction-info">
                <div class="direction-label">DIREZIONE</div>
                <div class="direction-name" id="direction-name">Caricamento...</div>
            </div>
        </div>

        <div class="time-remaining" id="time-container" style="display: none;">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                <path d="M8 3.5a.5.5 0 0 0-1 0V9a.5.5 0 0 0 .252.434l3.5 2a.5.5 0 0 0 .496-.868L8 8.71V3.5z" />
                <path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm7-8A7 7 0 1 1 1 8a7 7 0 0 1 14 0z" />
            </svg>
            <span id="time-text">-- min</span>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-card">
        <div id="stops-container">
            <div class="loading-state">
                Caricamento fermate...
            </div>
        </div>
    </div>

    <button class="map-fab" onclick="openMap()" title="Vedi sulla mappa">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 16 16">
            <path d="M8 16s6-5.686 6-10A6 6 0 0 0 2 6c0 4.314 6 10 6 10zm0-7a3 3 0 1 1 0-6 3 3 0 0 1 0 6z" />
        </svg>
    </button>

    <script>
        const urlParams = new URLSearchParams(window.location.search);
        const lineFull = urlParams.get('line');
        const line = lineFull.split('_')[0];
        const tag = lineFull.split('_')[1];

        const dest = urlParams.get('dest');
        const stopId = urlParams.get('stopId');
        let   time = urlParams.get('time');
        const delay = urlParams.get('delay'); // New param
        let today;
        let tripId;
        
        window.onload = init();

        async function init() {

            //Get today day of week
            let array = ["sunday", "monday", "tuesday", "wednesday", "thursday", "friday", "saturday"]; today = array[new Date().getDay() % 7];

            //Retrieve info from sessionStorage
            let track_name = sessionStorage.getItem('busTrack');
            let destination = sessionStorage.getItem('lastStop');
            let stop = sessionStorage.getItem('timedStop');
            let time = sessionStorage.getItem('realTime');

            //Get tripId
            tripId = await getTripId(track_name, destination, today, time, stop);
            console.log("tripId", tripId);

            // Init Header
            initHeader();

            //Get GTFS, ACTV JSON and render stops
            
            let stopsGTFS = await loadStops(tripId);
            refresh(stopsGTFS);
            //Update time
            // setInterval(refresh, 25000, stopsGTFS);            
        }

        async function refresh(stopsGTFS) {
            let stopsJSON = await getActvJson();
            
            //find name of stopId
            
            let stopName = stopsGTFS.find(stop => (stop.stop_id === stopId.split('-')[0] || stop.stop_id === stopId.split('-')[1])).stop_name;

            let stop = stopsJSON.find(stop => stop.stop === stopName);
            
            time = stop ? stop.time : -1;
            renderStops(stopsGTFS, stopsJSON);
        }

        function initHeader() {
            document.getElementById('line-number').innerText = line || '--';
            document.getElementById('direction-name').innerText = dest.replace(/\\/g, '') || 'Sconosciuta';
            if (time) {
                document.getElementById('time-container').style.display = 'flex';
                document.getElementById('time-text').innerText = time.replace(/\\/g, '');
            }
            // Helper for badge color (same as stop.php)
            if (line.includes('N')) lineBadgeClass = 'badge-night';
            else if (tag === "US" || tag === "UN" || tag === "EN") lineBadgeClass = 'badge-blue';
            else lineBadgeClass = 'badge-red';
            document.getElementById('line-number').className += ' ' + lineBadgeClass;
        }

        async function loadStops(tripId) {
            if (!line) return;

            try {
                let response = await fetch(`/api/gtfs-builder?trip_id=${encodeURIComponent(tripId)}`);
                if (!response.ok) throw new Error('Network error');

                const stops = await response.json();
                return stops;

            } catch (error) {
                console.error(error);
                document.getElementById('stops-container').innerHTML = '<div class="text-center text-danger">Errore caricamento percorso</div>';
            }
        }

        async function getActvJson(actvJson=[]) {
            if (!line) return;

            try {
                // const response = await fetch(`/api/trip-stops?line=${encodeURIComponent(line)}&dest=${encodeURIComponent(dest)}`);
                let response = await fetch(`https://oraritemporeale.actv.it/aut/backend/passages/${stopId}-web-aut`, {cache: 'no-cache'});
                if (!response.ok) throw new Error('Network error');

                const tripsJSON = await response.json();

                //Calculate trip id of each trip
                await Promise.all(tripsJSON.map(async trip => {
                    let local_busTrack = trip.line.split('_')[0];
                    let local_busDirection = trip.destination;
                    
                    let local_stopName = trip.timingPoints[trip.timingPoints.length - 1].stop;
                    let local_stopTime = trip.timingPoints[trip.timingPoints.length - 1].time;

                    try {
                        let response = await fetch(`/api/gtfs-identify?return=true&time=${local_stopTime}&busTrack=${local_busTrack}&busDirection=${encodeURIComponent(local_busDirection)}&day=${today}&stop=${encodeURIComponent(local_stopName)}`, {cache: 'no-cache'});
                        if (!response.ok) throw new Error('Network error');
                        let data = await response.json();
                        trip.tripId = data.trip_id;
                        if (tripId === data.trip_id) {
                            return trip;
                        }
                    } catch (e) {
                        console.error(e);
                    }
                }));

                // return trips;
                let theInterestingTrip = tripsJSON.filter(trip => trip.tripId === tripId);                
                return theInterestingTrip[0].timingPoints;

            } catch (error) {
                console.error(error);
                document.getElementById('stops-container').innerHTML = '<div class="text-center text-danger">Errore caricamento percorso</div>';
            }
        }


        function findCurrentStopIndex(stopsGTFS, stopId) {
            let currentStopIndex = -1;

            // Debug logs
            console.log('Target stopId:', stopId);

            // console.log(stopsGTFS, stopId);
            

            // Try to find by ID first
            if (stopId) {
                currentStopIndex = stopsGTFS.findIndex(s => s.stop_id == stopId || stopId.includes(s.stop_id));
            }

            // If not found, try loose match or name match
            if (currentStopIndex === -1 && stopId) {
                console.warn('Stop ID match failed. Trying loose match or name match...');
                currentStopIndex = stopsGTFS.findIndex(
                    s => String(s.stop_id).includes(String(stopId)) || 
                         String(stopId).includes(String(s.stop_id))
                );
            }

            console.log('Found current stop index:', currentStopIndex);
            return currentStopIndex;
        }

        function renderStops(stopsGTFS, stopsJSON) {
            const container = document.getElementById('stops-container');
            let html = '<div class="timeline">';

            // 1. Find current stop index
            let currentStopIndex = findCurrentStopIndex(stopsGTFS, stopId);

            // 1.5. Merge GTFS and ACTV JSON
            let stops = stopsGTFS;
            console.log("GTFS", stopsGTFS);
            console.log("JSON", stopsJSON);

            stops.forEach((stop, index) => {
                let stopJSON = stopsJSON.find(s => s.stop == stop.stop_name);
                if (stopJSON) {
                    stop.time = stopJSON.time;
                }
            });
            console.log("Merged", stops);

            // 2. Render stops
            stops.forEach((stop, index) => {
                let isCurrent = false;
                let isPassed = false;

                // Check if this stop is the current one
                if (currentStopIndex !== -1) {
                    if (index < currentStopIndex) {
                        isPassed = true;
                    } else if (index === currentStopIndex) {
                        isCurrent = true;
                    }
                }

                // Visual states
                let markerClass = 'stop-marker';
                let lineClass = 'stop-line';
                let itemClass = 'stop-item';

                if (isPassed) {
                    markerClass += ' passed';
                    lineClass += ' passed';
                    itemClass += ' passed';
                } else if (isCurrent) {
                    markerClass += ' current';
                    itemClass += ' current-stop-item'; // For scrolling
                }

                // Time Logic
                let timeDisplay = calculateTimeDisplay(stop, isCurrent, isPassed);
                
                function calculateTimeDisplay(stop, isCurrent, isPassed) {
                    if (isPassed) {
                        return (stop.time) ? "PASSATO - " + stop.time : "PASSATO - " + stop.arrival_time.split(":").splice(0, 2).join(":");
                    }
                    
                    if (isCurrent) {

                        // Show the time passed in URL if available
                        if (!(time && time != undefined && time != null)) { return "Generic error"; }
                        
                        if(time == 'departure') {
                            return "ORA";
                        } else {
                            // Remove backslashes
                            return (time+"").replace(/\\\'/g, '');
                        }
                        
                    } 

                    // Future stops
                    
                    return (stop.time) ? stop.time : "IN ARRIVO";
                }
                

                let stopUrl = '/aut/stops/stop?id=' + stop.stop_id + '&name=' + encodeURIComponent(stop.stop_name);
                html += /*html*/ `
                <div class="${itemClass}" onclick="window.location.href='${stopUrl}'" style="cursor: pointer;">
                    <div class="${lineClass}"></div>
                    <div class="${markerClass}"></div>
                    <div class="stop-content">
                        <div class="stop-name">${stop.stop_name}</div>
                        <div class="stop-time">${timeDisplay}</div>
                    </div>
                </div>
                `;
            });

            html += '</div>';
            container.innerHTML = html;

            // Auto-scroll to current
            setTimeout(() => {
                const currentEl = document.querySelector('.current-stop-item');
                if (currentEl) {
                    currentEl.scrollIntoView({
                        behavior: 'smooth',
                        block: 'center'
                    });
                }
            }, 500);
        }

        function openMap() {
            // Open lines map, maybe passing the line to highlight
            window.location.href = `/lines-map?line=${encodeURIComponent(line)}`;
        }

        async function getTripId(busTrack, busDirection, day, time, stop) {
            try {
                let url = `/api/gtfs-identify?return=true&time=${time}&busTrack=${busTrack}&busDirection=${encodeURIComponent(busDirection)}&day=${day}&stop=${encodeURIComponent(stop)}`;
                console.log(url);

                const response = await fetch(url);

                if (!response.ok) throw new Error('Network error');

                const data = await response.json();
                console.log(data);
                return data.trip_id;

            } catch (error) {
                console.error(error);
                return null;
            }
        }
    </script>
</body>

</html>