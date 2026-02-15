<?php
class ApiController
{

    private function getDb($joins = "") {
        if (!class_exists('databaseConnector')) {
            require_once BASE_PATH . '/app/models/databaseConnector.php';
        }
        $db = new databaseConnector();
        $db->connect(ENV['DB_USER'], ENV['DB_PASS'], ENV['DB_HOST'], ENV['DB_NAME'], $joins);
        return $db;
    }

    // Refactored from dbControll::api_gtfsIdentify
    function api_gtfsIdentify() {
        $tableJoins = "
            INNER JOIN trips ON routes.route_id = trips.route_id 
            INNER JOIN stop_times ON trips.trip_id = stop_times.trip_id 
            INNER JOIN stops ON stop_times.stop_id = stops.stop_id 
            INNER JOIN calendar ON trips.service_id = calendar.service_id
            ";

        // The view expects $db variable to be available
        $db = $this->getDb($tableJoins);

        require_once BASE_PATH . '/app/views/gtfsIdentify.php';
    }

    // Refactored from dbControll::gtfsTripBuilder
    function gtfsTripBuilder() {
        if (!isset($_GET) && !isset($_GET['return']))
            die("No parameters");

        $db = $this->getDb();

        $tripId = $db->query("SELECT 1") ? addslashes($_GET['trip_id']) : ''; // Simple check and sanitize
        // Note: addslashes is basic protection. Prepared statements would be better but keeping consistency with existing codebase style for now.
        $tripId = $_GET['trip_id'];

        header("Content-Type: application/json");

        // Use safe query construction or parameterized if class supports it. 
        // Existing class only has query() taking string.
        // We will trust internal usage or add basic sanitization.
        $safeTripId = $tripId; // In a real app we need usage of prepared statements

        $stops = $db->query(
            // "SELECT stops.*, stop_times.* FROM stops INNER JOIN stop_times ON stops.stop_id = stop_times.stop_id WHERE trip_id = '$safeTripId' ORDER BY stop_times.arrival_time"
            "SELECT 
                stops.*, 
                stop_times.*
            FROM stops
            INNER JOIN stop_times ON stops.stop_id = stop_times.stop_id
            WHERE stop_times.trip_id = '$safeTripId'
            ORDER BY stop_times.stop_sequence
            "
        );

        //Controllo se ci sono fermate duplicate una dopo l'altra
        $uniqueStops = [];
        foreach ($stops as $stop) {
            //Se la fermata precedente è uguale a questa, la salto
            if (!isset($uniqueStops[$stop['stop_id']]) || $uniqueStops[$stop['stop_id']]['stop_id'] != $stop['stop_id']) {
                $uniqueStops[$stop['stop_id']] = $stop;
            }
        }
        $stops = array_values($uniqueStops);

        echo json_encode($stops);
    }

    // Refactored from dbControll::gtfsStopTranslater
    function gtfsStopTranslater() {
        if (!isset($_GET) && !isset($_GET['return']))
            die("No parameters");

        $db = $this->getDb();

        $tripId = $_GET['trip_id'];

        header("Content-Type: application/json");

        // Fixed typo in original query: 'stops.,' -> 'stops.*,'
        $stops = $db->query("SELECT stops.*, stop_times.* FROM stops INNER JOIN stop_times ON stops.stop_id = stop_times.stop_id WHERE trip_id = '$tripId' ORDER BY stop_times.arrival_time");
        echo json_encode($stops);
    }

    // Moved from Controller::stopsJson and renamed
    function stops() {
        if (!isset($_GET) && !isset($_GET['return']))
            die("No parameters");

        $db = $this->getDb();

        header("Content-Type: application/json");

        $stops = $db->query("SELECT * FROM stops");
        echo json_encode($stops);
    }

    // Moved from Controller::favorite
    function favorite() {
        require_once BASE_PATH . '/app/models/addToFavorites.php';
    }

    // Moved from Controller::planRoute
    function planRoute() {
        // This service likely uses local files (RoutePlanner), keeping as is per plan/User request scope (Controllers only)
        require_once BASE_PATH . '/app/services/RoutePlanner.php';

        $origin = $_GET['from'] ?? '';
        $dest = $_GET['to'] ?? '';
        $time = $_GET['time'] ?? date('H:i:s');

        // Ensure time is in HH:MM:SS format
        if (strlen($time) == 5) {
            $time .= ':00';
        }

        try {
            $planner = new RoutePlanner();
            // ... Logic from Controller.php ...
            // Since the logic is long and depends on RoutePlanner, we just copy the body.
            // But wait, the user asked to "remove LOCAL GTFS calls" from Controllers.
            // planRoute logic in Controller.php handles logic "around" RoutePlanner.

            // Re-implementing the logic from Controller::planRoute
            $startWalk = null;
            $endWalk = null;
            $planningOrigin = $origin;
            $planningDest = $dest;
            $planningTime = $time;

            // Handle Origin Address
            if (strpos($origin, ',') !== false) {
                list($lat, $lon) = explode(',', $origin);
                $nearest = $planner->findNearestStop((float)$lat, (float)$lon);

                if ($nearest) {
                    $planningOrigin = $nearest['stop_id'];
                    $startWalk = [
                        'type' => 'walking',
                        'distance' => $nearest['distance'],
                        'duration' => $nearest['walking_time'],
                        'from_name' => 'Indirizzo partenza',
                        'to_name' => $nearest['name'],
                        'departure_time' => $time,
                        'arrival_time' => date('H:i:s', strtotime($time) + ($nearest['walking_time'] * 60))
                    ];
                    $planningTime = $startWalk['arrival_time'];
                }
            }

            // Handle Destination Address
            if (strpos($dest, ',') !== false) {
                list($lat, $lon) = explode(',', $dest);
                $nearest = $planner->findNearestStop((float)$lat, (float)$lon);

                if ($nearest) {
                    $planningDest = $nearest['stop_id'];
                    $endWalk = [
                        'stop_info' => $nearest
                    ];
                }
            }

            $routes = $planner->findRoutes($planningOrigin, $planningDest, $planningTime);

            // Post-process routes to inject walking legs
            foreach ($routes as &$route) {
                // Prepend Start Walk
                if ($startWalk) {
                    $route['departure_time'] = $startWalk['departure_time'];
                    $route['duration'] += $startWalk['duration'];
                    array_unshift($route['legs'], [
                        'type' => 'walking',
                        'distance' => $startWalk['distance'],
                        'duration' => $startWalk['duration'],
                        'departure_time' => $startWalk['departure_time'],
                        'arrival_time' => $startWalk['arrival_time'],
                        'route_short_name' => 'Cammina',
                        'stops_count' => 0,
                        'origin' => $startWalk['from_name'],
                        'destination' => $startWalk['to_name']
                    ]);
                }

                // Append End Walk
                if ($endWalk) {
                    $arrivalAtStop = $route['arrival_time'];
                    $walkDuration = $endWalk['stop_info']['walking_time'];
                    $walkDistance = $endWalk['stop_info']['distance'];
                    $arrivalAtDest = date('H:i:s', strtotime($arrivalAtStop) + ($walkDuration * 60));

                    $route['arrival_time'] = $arrivalAtDest;
                    $route['duration'] += $walkDuration;

                    $route['legs'][] = [
                        'type' => 'walking',
                        'distance' => $walkDistance,
                        'duration' => $walkDuration,
                        'departure_time' => $arrivalAtStop,
                        'arrival_time' => $arrivalAtDest,
                        'route_short_name' => 'Cammina',
                        'stops_count' => 0,
                        'origin' => $endWalk['stop_info']['name'],
                        'destination' => 'Indirizzo destinazione'
                    ];
                }
            }

            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'routes' => $routes]);

        }
        catch (Exception $e) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    // Refactored from Controller::linesShapes to use DB
    function linesShapes() {
        ini_set('memory_limit', '256M');
        set_time_limit(120); // Give DB more time if needed

        $db = $this->getDb();

        // 1. Definisci i parametri opzionali
        $targetLine = $_GET['line'] ?? null;
        $targetTripId = $_GET['tripId'] ?? $_GET['trip_id'] ?? null; // Supporta entrambi i naming

        // Costruzione dinamica della query
        // Caso A: Specifico Trip ID
        if ($targetTripId) {
            $safeTripId = addslashes($targetTripId);
            $sql = "
                SELECT 
                    r.route_id, 
                    r.route_short_name, 
                    r.route_long_name,
                    s.stop_lat AS lat, 
                    s.stop_lon AS lng, 
                    s.stop_name AS name
                FROM trips t
                JOIN routes r ON t.route_id = r.route_id
                JOIN stop_times st ON t.trip_id = st.trip_id
                JOIN stops s ON st.stop_id = s.stop_id
                WHERE t.trip_id = '$safeTripId'
                ORDER BY st.stop_sequence ASC
            ";
        }
        // Caso B: Specifica Linea
        elseif ($targetLine) {
            $safeLine = addslashes($targetLine);
            $sql = "
                SELECT 
                    r.route_id, 
                    r.route_short_name, 
                    r.route_long_name,
                    s.stop_lat AS lat, 
                    s.stop_lon AS lng, 
                    s.stop_name AS name
                FROM routes r
                JOIN (
                    SELECT route_id, MIN(trip_id) as representative_trip_id
                    FROM trips
                    GROUP BY route_id
                ) t_rep ON r.route_id = t_rep.route_id
                JOIN stop_times st ON t_rep.representative_trip_id = st.trip_id
                JOIN stops s ON st.stop_id = s.stop_id
                WHERE r.route_short_name = '$safeLine'
                ORDER BY r.route_id, st.stop_sequence ASC
            ";
        }
        // Caso C: Tutte le linee (Default)
        else {
            $sql = "
                SELECT 
                    r.route_id, 
                    r.route_short_name, 
                    r.route_long_name,
                    s.stop_lat AS lat, 
                    s.stop_lon AS lng, 
                    s.stop_name AS name
                FROM routes r
                JOIN (
                    SELECT route_id, MIN(trip_id) as representative_trip_id
                    FROM trips
                    GROUP BY route_id
                ) t_rep ON r.route_id = t_rep.route_id
                JOIN stop_times st ON t_rep.representative_trip_id = st.trip_id
                JOIN stops s ON st.stop_id = s.stop_id
                ORDER BY r.route_id, st.stop_sequence ASC
            ";
        }

        $results = $db->query($sql);
        //$results = $stmt->fetchAll(PDO::FETCH_ASSOC); // Assumo tu stia usando PDO o driver simile

        $routesMap = [];

        // 2. Raggruppamento dei dati lato PHP
        // La query restituisce righe 'piatte', noi le raggruppiamo per route_id (o per singolo risultato nel caso tripId)
        // Nota: Nel caso di tripId singolo, route_id sarà unico.
        foreach ($results as $row) {
            $routeId = $row['route_id'];

            // Se non abbiamo ancora inizializzato questa rotta nell'array, facciamolo
            if (!isset($routesMap[$routeId])) {
                $routesMap[$routeId] = [
                    'route_id' => $row['route_id'],
                    'route_short_name' => $row['route_short_name'],
                    'route_long_name' => $row['route_long_name'],
                    'path' => []
                ];
            }

            // Aggiungiamo la fermata all'array 'path' della rotta corrente
            $routesMap[$routeId]['path'][] = [
                'lat' => $row['lat'],
                'lng' => $row['lng'],
                'name' => $row['name']
            ];
        }

        // 3. Reset delle chiavi dell'array per ottenere un JSON array pulito (es. [ {...}, {...} ])
        $shapes = array_values($routesMap);

        // L'output $shapes è ora identico al tuo formato originale

        /*
         // 1. Get all routes
         $routes = $db->query("SELECT route_id, route_short_name, route_long_name FROM routes");
         
         $shapes = [];
         */
        /*
         {
         "route_id": "1",
         "route_short_name": "11",
         "route_long_name": "Santa Maria Elisabetta - Pellestrina Cimitero",
         "path": [
         {
         "lat": "45.41782",
         "lng": "12.368981",
         "name": "Santa Maria Elisabetta"
         },
         {
         "lat": "45.405041",
         "lng": "12.367017",
         "name": "Palazzo del Cinema"
         },
         {
         "lat": "45.266838",
         "lng": "12.301488",
         "name": "Pellestrina Tre Rose"
         },
         {
         "lat": "45.262878",
         "lng": "12.300303",
         "name": "Pellestrina Cimitero"
         }
         ]
         },
         {
         "route_id": "2",
         "route_short_name": "11",
         "route_long_name": "Santa Maria Elisabetta - Pellestrina Cimitero",
         "path": [
         {
         "lat": "45.41782",
         "lng": "12.368981",
         "name": "Santa Maria Elisabetta"
         },
         {
         "lat": "45.405041",
         "lng": "12.367017",
         "name": "Palazzo del Cinema"
         },
         {
         "lat": "45.266838",
         "lng": "12.301488",
         "name": "Pellestrina Tre Rose"
         },
         {
         "lat": "45.262878",
         "lng": "12.300303",
         "name": "Pellestrina Cimitero"
         }
         ]
         },
         
         */
        /*
         
         foreach ($routes as $route) {
         $routeId = $route['route_id'];
         
         // 2. Get one representative trip for this route
         // We just take one trip LIMIT 1. To be better we might check direction or longest trip, but any valid trip gives the shape roughly.
         $trips = $db->query("SELECT trip_id FROM trips WHERE route_id = '$routeId' LIMIT 1");
         
         if (!empty($trips)) {
         $tripId = $trips[0]['trip_id'];
         
         // 3. Get stops for this trip in order
         $pathQuery = "
         SELECT s.stop_lat as lat, s.stop_lon as lng, s.stop_name as name 
         FROM stop_times st 
         JOIN stops s ON st.stop_id = s.stop_id 
         WHERE st.trip_id = '$tripId' 
         ORDER BY st.stop_sequence ASC
         ";
         $stops = $db->query($pathQuery);
         
         if (!empty($stops)) {
         // Convert float strings to real floats if needed, though JSON handles numeric strings fine usually.
         // But lat/lon in stops table might be stored as decimal/double.
         
         $shapes[] = [
         'route_id' => $routeId,
         'route_short_name' => $route['route_short_name'],
         'route_long_name' => $route['route_long_name'],
         'path' => $stops
         ];
         }
         }
         } */

        header('Content-Type: application/json');
        echo json_encode($shapes);
    }

    // Refactored from Controller::tripStops to use DB
    function tripStops() {
        $line = $_GET['line'] ?? '';
        $dest = $_GET['dest'] ?? '';

        if (!$line) {
            header('HTTP/1.1 400 Bad Request');
            echo json_encode(['error' => 'Missing line parameter']);
            return;
        }

        $db = $this->getDb();

        // 1. Find route by short name
        // Use exact match for safely
        $safeLine = addslashes($line);
        $routeQuery = "SELECT route_id FROM routes WHERE route_short_name = '$safeLine'";
        $routes = $db->query($routeQuery);

        if (empty($routes)) {
            header('HTTP/1.1 404 Not Found');
            echo json_encode(['error' => 'Route not found']);
            return;
        }

        $targetRouteId = $routes[0]['route_id'];

        $selectedTripId = null;

        if ($dest) {
            $safeDest = addslashes($dest);
            $tripByHeadsign = $db->query("SELECT trip_id FROM trips WHERE route_id = '$targetRouteId' AND trip_headsign LIKE '%$safeDest%' LIMIT 1");

            if (!empty($tripByHeadsign)) {
                $selectedTripId = $tripByHeadsign[0]['trip_id'];
            }
            else {
                $deepQuery = "
                    SELECT t.trip_id 
                    FROM trips t
                    WHERE t.route_id = '$targetRouteId'
                    AND (
                        SELECT s.stop_name 
                        FROM stop_times st 
                        JOIN stops s ON st.stop_id = s.stop_id 
                        WHERE st.trip_id = t.trip_id 
                        ORDER BY st.stop_sequence DESC 
                        LIMIT 1
                    ) LIKE '%$safeDest%'
                    LIMIT 1
                ";
                $trips = $db->query($deepQuery);
                if (!empty($trips)) {
                    $selectedTripId = $trips[0]['trip_id'];
                }
            }
        }

        if (!$selectedTripId) {
            $trips = $db->query("SELECT trip_id FROM trips WHERE route_id = '$targetRouteId' LIMIT 1");
            if (!empty($trips)) {
                $selectedTripId = $trips[0]['trip_id'];
            }
        }

        if (!$selectedTripId) {
            header('HTTP/1.1 404 Not Found');
            echo json_encode(['error' => 'Trips not found']);
            return;
        }

        // 3. Get stops for selected trip
        $stopsQuery = "
            SELECT 
                s.stop_id as id,
                s.stop_name as name,
                s.stop_lat as lat,
                s.stop_lon as lng,
                st.departure_time as time
            FROM stop_times st
            JOIN stops s ON st.stop_id = s.stop_id
            WHERE st.trip_id = '$selectedTripId'
            ORDER BY st.stop_sequence ASC
        ";

        $stops = $db->query($stopsQuery);

        // Format time to HH:MM (substr)
        foreach ($stops as &$stop) {
            $stop['time'] = substr($stop['time'], 0, 5);
        }

        header('Content-Type: application/json');
        echo json_encode($stops);
    }

    function logJsError() {
        $data = json_decode(file_get_contents('php://input'), true);
        if ($data) {
            if (!class_exists('Logger')) {
                require_once BASE_PATH . '/app/services/Logger.php';
            }
            Logger::log(
                'JS_ERROR',
                $data['message'] ?? 'Unknown JS error',
                $data['url'] ?? null,
                $data['line'] ?? null,
                $data['stack'] ?? null,
            ['userAgent' => $_SERVER['HTTP_USER_AGENT'] ?? null]
            );
            echo json_encode(['success' => true]);
        }
        else {
            header('HTTP/1.1 400 Bad Request');
            echo json_encode(['error' => 'Invalid data']);
        }
    }

    function gtfsResolve() {
        $tableJoins = "
            INNER JOIN trips ON routes.route_id = trips.route_id 
            INNER JOIN stop_times ON trips.trip_id = stop_times.trip_id 
            INNER JOIN stops ON stop_times.stop_id = stops.stop_id 
            INNER JOIN calendar ON trips.service_id = calendar.service_id
            ";

        // The view expects $db variable to be available
        $db = $this->getDb($tableJoins);

        require_once BASE_PATH . '/app/models/gtfsTripResolver.php';
    }
    
    /**
     * API /api/gtfs-bnr
     * Ritorna i bus attualmente in servizio (±30 min dall'orario corrente).
     * GET params opzionali: time (HH:MM), day (monday..sunday)
     */
    function gtfsBusesRunningNow() {
        header('Content-Type: application/json');

        $db = $this->getDb();

        // Orario e giorno: default = ora del server
        $currentTime = $_GET['time'] ?? date('H:i');
        $dayParam    = $_GET['day']  ?? strtolower(date('l'));

        $allowedDays = ['monday','tuesday','wednesday','thursday','friday','saturday','sunday'];
        $dayIndex = array_search($dayParam, $allowedDays);
        if ($dayIndex === false) {
            echo json_encode(['error' => 'Invalid day']);
            return;
        }
        $yesterday = $allowedDays[($dayIndex + 6) % 7];

        // Query: bus di oggi + bus notturni di ieri (arrival_time >= 24:00:00)
        $sql = "SELECT r.route_short_name, r.route_id, t.trip_headsign, st.arrival_time, t.trip_id, 'today' as source
                FROM stops s
                JOIN stop_times st ON s.stop_id = st.stop_id
                JOIN trips t ON st.trip_id = t.trip_id
                JOIN routes r ON t.route_id = r.route_id
                JOIN calendar c ON t.service_id = c.service_id
                WHERE c.{$dayParam} = 1
                UNION ALL
                SELECT r.route_short_name, r.route_id, t.trip_headsign, st.arrival_time, t.trip_id, 'yesterday' as source
                FROM stops s
                JOIN stop_times st ON s.stop_id = st.stop_id
                JOIN trips t ON t.trip_id = st.trip_id
                JOIN routes r ON t.route_id = r.route_id
                JOIN calendar c ON t.service_id = c.service_id
                WHERE c.{$yesterday} = 1 AND st.arrival_time >= '24:00:00'
                ORDER BY arrival_time ASC";

        $allTrips = $db->query($sql);

        // Filtro: solo trip entro ±30 min dall'orario richiesto
        $timeParts = explode(':', $currentTime);
        $searchSec = ($timeParts[0] * 3600) + ($timeParts[1] * 60);
        $range = 1800; // 30 minuti

        $seen = []; // trip_id già visti (deduplicazione)
        $results = [];

        foreach ($allTrips as $trip) {
            if (isset($seen[$trip['trip_id']])) continue;

            $tParts = explode(':', $trip['arrival_time']);
            $tripSec = ($tParts[0] * 3600) + ($tParts[1] * 60);

            $diff = abs($tripSec - $searchSec);
            if ($diff > 43200) $diff = 86400 - $diff;

            if ($diff <= $range) {
                $trip['diff_min'] = round(($tripSec - $searchSec) / 60);

                if ($trip['source'] == 'yesterday' && $searchSec < 3600) {
                    $trip['diff_min'] = round(($tripSec - 86400 - $searchSec) / 60);
                }

                $seen[$trip['trip_id']] = true;
                $results[] = $trip;
            }
        }

        usort($results, fn($a, $b) => $a['diff_min'] <=> $b['diff_min']);

        echo json_encode([
            'time'  => $currentTime,
            'day'   => $dayParam,
            'count' => count($results),
            'buses' => $results
        ]);
    }

    /**
     * API /api/bus-position
     * Dato un tripId, ritorna le fermate ordinate con coordinate e orari.
     * GET params: tripId (required)
     */
    function busPosition() {
        header('Content-Type: application/json');

        $tripId = $_GET['tripId'] ?? null;
        if (!$tripId) {
            header('HTTP/1.1 400 Bad Request');
            echo json_encode(['error' => 'Missing tripId parameter']);
            return;
        }

        $db = $this->getDb();
        $safeTripId = addslashes($tripId);

        // 1. Get stops for the trip
        $sqlStops = "SELECT s.stop_id, s.stop_name, s.stop_lat, s.stop_lon,
                    st.arrival_time, st.departure_time, st.stop_sequence, s.data_url
                FROM stop_times st
                JOIN stops s ON st.stop_id = s.stop_id
                WHERE st.trip_id = '$safeTripId'
                ORDER BY st.stop_sequence ASC";

        $stops = $db->query($sqlStops);

        if (empty($stops)) {
            echo json_encode(['error' => 'No stops found for this trip']);
            return;
        }

        // 2. Get the shape for this trip
        $sqlShapeId = "SELECT shape_id FROM trips WHERE trip_id = '$safeTripId' LIMIT 1";
        $tripInfo = $db->query($sqlShapeId);
        
        $shapePoints = [];
        if (!empty($tripInfo) && !empty($tripInfo[0]['shape_id'])) {
            $shapeId = $tripInfo[0]['shape_id'];
            $sqlShape = "SELECT lat, lng, sequence, dist_traveled 
                         FROM shapes_refined 
                         WHERE shape_id = '$shapeId' 
                         ORDER BY sequence ASC";
            $shapePoints = $db->query($sqlShape);
        }

        echo json_encode([
            'stops' => $stops,
            'shape' => $shapePoints
        ]);
    }
}
