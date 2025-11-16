<!DOCTYPE html>
<html lang="it">
	<head>
		<meta charset="UTF-8">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<title>Mappa Stazioni ACTV</title>
		<!-- CSS di Leaflet -->
		<link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
		<script src="https://cdn.tailwindcss.com"></script>
		<?php
		require COMMON_HTML_HEAD;
		?>
		<style>
            /* Stile per il contenitore della mappa */
            #map {
                height: 30vh; /* Cruciale: assicura che il contenitore abbia un'altezza definita */
                width: 100%;
                border-radius: 0.5rem;
                box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1);
            }
            body {
                font-family: 'Inter', sans-serif;
                background-color: #f3f4f6;
                padding: 1rem;
                margin: 0;
            }
            .container {
                max-width: 1200px;
                margin: auto;
            }
            /* Assicurati che il contenitore della mappa rispetti le dimensioni */
            .leaflet-container {
                border-radius: 0.5rem;
            }
		</style>
	</head>
	<body>
		
		<div class="container-desktop">
			<div data-v-93878d43="" class="desktop-topbar" style="--radius: 0;">
				<a data-v-93878d43="" href="/aut" rel="noopener noreferrer" target="_parent" class="logo">
					<div data-v-e59c4665="" data-v-93878d43="" class="icon-wrapper" style="--3ba3f6f5: auto; --1f0bedd8: 100%; height: 100%; width: auto;">
						<img data-v-e59c4665="" src="<?=URL_PATH?>/svg/logo-icon.svg" alt="Logo">
					</div>
				</a>
			</div>
			<div class="page">
				
				<div class="d-flex flex-column justify-center m-4" style="">
					<button class="btn btn-primary" style="font-size: 2rem; border-radius: 1rem; padding: 1rem;" onclick="window.location.href='/stopList'">
						Vai alla lista delle stazioni ACTV
					</button>
					<hr style="margin: 10px;">
					<div id="closest-stations"></div>
					<hr style="margin: 10px;">
				</div>
				
				
				<div id="status" class="bg-blue-100 text-blue-800 p-3 rounded-md mb-4 flex items-center" style="font-size: 1.2rem;">
					<svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
					<span>In attesa di geolocalizzazione...</span>
				</div>
				<div id="map"></div>
			</div>
		</div>
		
		<!-- JavaScript di Leaflet -->
		<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
		
		<script>
			// --- WRAPPING TUTTO IL CODICE IN window.onload ---
			async function getStations() {
				try {
					let response = await fetch('https://oraritemporeale.actv.it/aut/backend/page/stops');
					if (!response.ok) {
						throw new Error(`Response status: ${response.status}`);
					}

					let rs = await response.json();
					if (rs === null) return [];
					let returnArray = [];
					rs.forEach(element => {
						let name = element.name
						let descr = element.description
						let lat = element.latitude
						let lng = element.longitude
						returnArray.push({name: name, description: descr, lat: lat, lng: lng})
					})
					return returnArray

				} catch (error) {
					console.error(error.message);
				}
			}

			function printAsBtnClosestStations(closestStationsArray) {
				let closestStations = document.getElementById('closest-stations');
				closestStations.innerHTML = "";
				
				closestStationsArray.forEach(station => {
					closestStations.innerHTML += `
					
					<a data-v-02845fd5="" data-v-996d2d02="" href="/aut/stops/stop?id=${station.name}" class="stop_single_result glass pointer" style="--index: 1;">
                        <div data-v-02845fd5="" class="stop_head">
                            <div data-v-02845fd5="" class="name_wrap">
                                <span data-v-02845fd5="" class="stop_name" style="view-transition-name: stopname-${station.name};">${station.description}</span>
                            </div>
                        </div>
                        <div data-v-02845fd5="" class="stop_lines"></div>
                    </a>
					
					`;
					
					if (station.lines !== undefined && station.lines !== null) {
						let linesDOM = closestStations.querySelector(".stop_lines");
						let urlPath = "<?=URL_PATH?>";
						closestStations.lines.forEach(line => {
							linesDOM.innerHTML += `
							<span data-v-02845fd5="" class="stop_line alternate small">
	                             <span data-v-02845fd5="" class="material-symbols-rounded text-xregular">
	                                 <img src="${urlPath}/svg/directions_bus.svg" alt="">
	                             </span>
	                             <span data-v-02845fd5="" class="text-regular bold">${line.alias}</span>
	                        </span>
							`
						})
					}
					
				 
					
				})
				
			}

			// Questo garantisce che il DOM sia completamente caricato e le dimensioni stabili
			window.onload = async function () {
				// Inizializzazione della mappa e impostazione della vista iniziale (es. Venezia)
				var map = L.map('map').setView([45.4384, 12.3359], 10); // Lat/Lng approssimative di Venezia e zoom
				const statusElement = document.getElementById('status');

				// Aggiungi un layer di piastrelle (tile layer) di OpenStreetMap
				L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
					attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
				}).addTo(map);

				// Dati delle Stazioni ACTV (SOSTITUISCI QUESTO CON I TUOI DATI)
				/*var actvStations = [
					{ name: "P.le Roma", lat: 45.4402, lng: 12.3235 },
					{ name: "Tronchetto", lat: 45.4387, lng: 12.3117 },
					{ name: "Fondamente Nove", lat: 45.4452, lng: 12.3411 },
					{ name: "Lido S.M. Elisabetta", lat: 45.4206, lng: 12.3789 },
					{ name: "Burano", lat: 45.4851, lng: 12.4172 },
					{ name: "Chioggia", lat: 45.2255, lng: 12.2858 },
					{ name: "Mestre Centro", lat: 45.5005, lng: 12.2355 },
					{ name: "Treviso Autostazione", lat: 45.6666, lng: 12.2471 },
					{ name: "Padova Autostazione", lat: 45.4187, lng: 11.8797 },
					{ name: "Venezia Marco Polo Aeroporto", lat: 45.5054, lng: 12.3424 }
				];*/
				let actvStations = await getStations();

				// Variabile per il marcatore della posizione utente
				var userMarker = null;

				// Imposta il numero di stazioni più vicine da evidenziare
				const N = 5;

				// Stili per i marcatori a cerchio (poco invasivi)
				const defaultCircleStyle = {
					color: '#0078A8',    // Blu ACTV
					fillColor: '#0078A8',
					fillOpacity: 0.3,    // Poco invasivo
					radius: 6            // Dimensione piccola (in pixel per L.circleMarker)
				};

				const highlightedCircleStyle = {
					color: '#E60000',    // Rosso Evidenziato
					fillColor: '#E60000',
					fillOpacity: 0.7,    // Più visibile
					radius: 10           // Dimensione maggiore
				};

				// --- FUNZIONE DI DISTANZA (Formula dell'Aversine per la distanza sulla sfera) ---
				function getDistance(lat1, lon1, lat2, lon2) {
					const R = 6371; // Raggio della Terra in km
					const dLat = (lat2 - lat1) * Math.PI / 180;
					const dLon = (lon2 - lon1) * Math.PI / 180;
					const a =
						Math.sin(dLat / 2) * Math.sin(dLat / 2) +
						Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) * Math.sin(dLon / 2) * Math.sin(dLon / 2);
					const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
					return R * c; // Distanza in km
				}

				// Aggiungi tutte le stazioni inizialmente con lo stile predefinito
				actvStations.forEach(station => {
					station.marker = L.circleMarker([station.lat, station.lng], defaultCircleStyle)
						.bindPopup(`**Stazione:** ${station.name}`)
						.addTo(map);
				});

				// --- GESTIONE DELLA POSIZIONE UTENTE ---
				function onLocationFound(e) {
					const userLat = e.latlng.lat;
					const userLng = e.latlng.lng;

					statusElement.className = "bg-green-100 text-green-800 p-3 rounded-md mb-4 flex items-center";
					statusElement.innerHTML = `<svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.27a11.97 11.97 0 013.298 2.378m-3.298-2.378A8.001 8.001 0 004.382 17.27a11.97 11.97 0 01-3.298-2.378m3.298 2.378L12 21l8.3-4.73a11.97 11.97 0 01-3.298-2.378zM12 12a3 3 0 100-6 3 3 0 000 6z"></path></svg>
                                    <span>Posizione trovata. Evidenziate le ${N} stazioni più vicine.</span>`;

					// 1. Rimuovi il vecchio marcatore utente se esiste
					if (userMarker) {
						map.removeLayer(userMarker);
					}

					// 2. Aggiungi il nuovo marcatore dell'utente (Cerchio azzurro più evidente)
					userMarker = L.circleMarker(e.latlng, {
						color: '#3399FF',
						fillColor: '#3399FF',
						fillOpacity: 0.9,
						radius: 12
					}).addTo(map)
						.bindPopup("Sei Qui").openPopup();

					// 3. Ricalcola le distanze
					actvStations.forEach(station => {
						station.distance = getDistance(userLat, userLng, station.lat, station.lng);
						// Rimuovi il vecchio marcatore per poterlo ridisegnare con lo stile corretto
						if (station.marker) {
							map.removeLayer(station.marker);
						}
					});

					// 4. Ordina e seleziona le N stazioni più vicine
					const closestStations = actvStations
						.sort((a, b) => a.distance - b.distance)
						.slice(0, N);
					
					printAsBtnClosestStations(closestStations)

					// 5. Ridisegna TUTTE le stazioni con lo stile appropriato
					actvStations.forEach(station => {
						const isClosest = closestStations.includes(station);
						const style = isClosest ? highlightedCircleStyle : defaultCircleStyle;

						// Aggiungi un popup che include la distanza se è tra le più vicine
						const popupText = isClosest
							? `**Stazione:** ${station.name}<br>Distanza: ${station.distance.toFixed(2)} km`
							: `**Stazione:** ${station.name}`;

						// Usa L.circleMarker per controllare il raggio in pixel (più adatto per punti poco invasivi)
						station.marker = L.circleMarker([station.lat, station.lng], style)
							.bindPopup(popupText)
							.addTo(map);
					});

					// 6. Centra la mappa per includere sia l'utente che le stazioni più vicine
					const bounds = L.latLngBounds([e.latlng]);
					closestStations.forEach(s => bounds.extend([s.lat, s.lng]));
					map.fitBounds(bounds, {padding: [50, 50]});
				}

				// --- GESTIONE ERRORE DI POSIZIONE ---
				function onLocationError(e) {
					console.error("Errore di geolocalizzazione:", e.message);
					statusElement.className = "bg-red-100 text-red-800 p-3 rounded-md mb-4 flex items-center";
					statusElement.innerHTML = `<svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    <span>Errore: ${e.message}. Non è stato possibile trovare le stazioni vicine.</span>`;

					// Centra comunque sulla zona di Venezia
					map.setView([45.4384, 12.3359], 10);
				}

				// Avvia la geolocalizzazione
				map.on('locationfound', onLocationFound);
				map.on('locationerror', onLocationError);

				// Chiedi la posizione con alta accuratezza
				map.locate({setView: false, maxZoom: 16, enableHighAccuracy: true});

				// FIX RICALCOLO: Garantisce che, anche dopo l'inizializzazione, la mappa si ridisegni correttamente.
				map.invalidateSize();
			}; // Fine window.onload
		</script>
	
	</body>
</html>