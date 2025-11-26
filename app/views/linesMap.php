<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mappa Linee - ACTV</title>
    <!-- CSS di Leaflet -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
    <?php require COMMON_HTML_HEAD; ?>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #F5F5F5;
            margin: 0;
            padding: 0;
            height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Header Verde */
        .header-green {
            background: #009E61;
            padding: 1rem 1.5rem;
            color: white;
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .header-title {
            font-family: 'Inter', sans-serif;
            font-weight: 700;
            font-size: 20px;
        }

        .back-button {
            color: white;
            text-decoration: none;
            font-size: 24px;
            display: flex;
            align-items: center;
        }

        #map {
            flex-grow: 1;
            width: 100%;
            height: 100%;
            z-index: 1;
        }

        .loading-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.8);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 2000;
        }

        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #009E61;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin-bottom: 1rem;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .line-popup {
            text-align: center;
        }
        
        .line-popup-title {
            font-weight: bold;
            font-size: 16px;
            margin-bottom: 5px;
            color: #009E61;
        }
        
        .line-popup-desc {
            font-size: 14px;
            color: #666;
        }
    </style>
</head>
<body>

    <!-- Header -->
    <div class="header-green">
        <a href="/" class="back-button">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-arrow-left" viewBox="0 0 16 16">
                <path fill-rule="evenodd" d="M15 8a.5.5 0 0 0-.5-.5H2.707l3.147-3.146a.5.5 0 1 0-.708-.708l-4 4a.5.5 0 0 0 0 .708l4 4a.5.5 0 0 0 .708-.708L2.707 8.5H14.5A.5.5 0 0 0 15 8z"/>
            </svg>
        </a>
        <div class="header-title">Mappa Linee</div>
        <div style="width: 24px;"></div> <!-- Spacer for centering -->
    </div>

    <div id="loading" class="loading-overlay">
        <div class="spinner"></div>
        <div>Caricamento linee...</div>
    </div>

    <div id="map"></div>

    <!-- JavaScript di Leaflet -->
    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
    
    <script>
        // Initialize Map
        var map = L.map('map', {attributionControl: false}).setView([45.4384, 12.3359], 12); // Venezia centro

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);

        // Function to generate a random color
        function getRandomColor() {
            const letters = '0123456789ABCDEF';
            let color = '#';
            for (let i = 0; i < 6; i++) {
                color += letters[Math.floor(Math.random() * 16)];
            }
            return color;
        }
        
        // Function to get a color based on string (consistent hashing)
        function stringToColor(str) {
            let hash = 0;
            for (let i = 0; i < str.length; i++) {
                hash = str.charCodeAt(i) + ((hash << 5) - hash);
            }
            let color = '#';
            for (let i = 0; i < 3; i++) {
                let value = (hash >> (i * 8)) & 0xFF;
                color += ('00' + value.toString(16)).substr(-2);
            }
            return color;
        }

        async function loadLines() {
            try {
                const response = await fetch('/api/lines-shapes');
                if (!response.ok) throw new Error('Network response was not ok');
                
                const shapes = await response.json();
                
                shapes.forEach(shape => {
                    if (shape.path && shape.path.length > 0) {
                        const latlngs = shape.path.map(p => [p.lat, p.lng]);
                        const color = stringToColor(shape.route_short_name);
                        
                        const polyline = L.polyline(latlngs, {
                            color: color,
                            weight: 4,
                            opacity: 0.7
                        }).addTo(map);
                        
                        polyline.bindPopup(`
                            <div class="line-popup">
                                <div class="line-popup-title">Linea ${shape.route_short_name}</div>
                                <div class="line-popup-desc">${shape.route_long_name}</div>
                            </div>
                        `);
                        
                        // Highlight on hover
                        polyline.on('mouseover', function(e) {
                            var layer = e.target;
                            layer.setStyle({
                                weight: 7,
                                opacity: 1
                            });
                            layer.bringToFront();
                        });

                        polyline.on('mouseout', function(e) {
                            var layer = e.target;
                            layer.setStyle({
                                weight: 4,
                                opacity: 0.7
                            });
                        });
                    }
                });
                
                document.getElementById('loading').style.display = 'none';
                
            } catch (error) {
                console.error('Error loading lines:', error);
                document.getElementById('loading').innerHTML = '<div style="color: red;">Errore caricamento linee</div>';
            }
        }

        loadLines();
    </script>
</body>
</html>
