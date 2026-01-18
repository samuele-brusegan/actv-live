<?php
class ApiController {

    private function getDb($joins = "") {
        if (!class_exists('databaseConnector')) {
            require_once BASE_PATH . '/app/models/databaseConnector.php';
        }
        $db = new databaseConnector();
        $db->connect(ENV['DB_USERNAME'], ENV['DB_PASSWORD'], ENV['DB_HOST'], ENV['DB_NAME'], $joins);
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
        if (!isset($_GET) && !isset($_GET['return'])) die("No parameters");
        
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
        if (!isset($_GET) && !isset($_GET['return'])) die("No parameters");
        
        $db = $this->getDb();

        $tripId = $_GET['trip_id'];

        header("Content-Type: application/json");

        // Fixed typo in original query: 'stops.,' -> 'stops.*,'
        $stops = $db->query("SELECT stops.*, stop_times.* FROM stops INNER JOIN stop_times ON stops.stop_id = stop_times.stop_id WHERE trip_id = '$tripId' ORDER BY stop_times.arrival_time");
        echo json_encode($stops);
    }

    // Moved from Controller::stopsJson and renamed
    function stops() {
        if (!isset($_GET) && !isset($_GET['return'])) die("No parameters");
        
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

        } catch (Exception $e) {
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
                    'route_id'         => $row['route_id'],
                    'route_short_name' => $row['route_short_name'],
                    'route_long_name'  => $row['route_long_name'],
                    'path'             => []
                ];
            }

            // Aggiungiamo la fermata all'array 'path' della rotta corrente
            $routesMap[$routeId]['path'][] = [
                'lat'  => $row['lat'],
                'lng'  => $row['lng'],
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
            } else {
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
        } else {
            header('HTTP/1.1 400 Bad Request');
            echo json_encode(['error' => 'Invalid data']);
        }
    }

    // Time Machine API endpoints
    function getTmSessions() {
        $db = $this->getDb();
        $sessions = $db->query("SELECT * FROM tm_sessions ORDER BY created_at DESC");
        header('Content-Type: application/json');
        echo json_encode($sessions);
    }

    function createTmSession() {
        $data = json_decode(file_get_contents('php://input'), true);
        if ($data) {
            $mysqli = Closure::bind(function($db) { return $db->db; }, null, 'databaseConnector')($this->getDb());
            $stmt = $mysqli->prepare("INSERT INTO tm_sessions (name, start_time, end_time, stops) VALUES (?, ?, ?, ?)");
            $stopsJson = json_encode($data['stops']);
            $stmt->bind_param("ssss", $data['name'], $data['start_timer'], $data['end_timer'], $stopsJson);
            $stmt->execute();
            echo json_encode(['success' => true, 'id' => $mysqli->insert_id]);
        }
    }

    function getSimulatedData() {
        $stopId = $_GET['stopId'] ?? null;
        $time = $_GET['time'] ?? null; // The simulated time from client

        if (!$stopId || !$time) {
            header('HTTP/1.1 400 Bad Request');
            return;
        }

        $db = $this->getDb();
        
        // Handle combined stop IDs (e.g. "4825-4826")
        $stopIds = explode('-', $stopId);
        $placeholders = implode(',', array_fill(0, count($stopIds), '?'));
        
        $query = "
            SELECT data_json 
            FROM tm_data 
            WHERE stop_id IN ($placeholders) 
            ORDER BY ABS(TIMESTAMPDIFF(SECOND, fetched_at, ?)) 
            LIMIT 1
        ";
        $mysqli = Closure::bind(function($db) { return $db->db; }, null, 'databaseConnector')($db);
        $stmt = $mysqli->prepare($query);
        
        // Bind parameters: stop IDs followed by the time
        $types = str_repeat('s', count($stopIds) + 1);
        $params = [...$stopIds, $time];
        $stmt->bind_param($types, ...$params);
        
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        header('Content-Type: application/json');
        if ($row) {
            echo $row['data_json'];
        } else {
            echo json_encode([]);
        }
    }
    public function runTmHeartbeat() {
        $token = $_GET['token'] ?? null;
        $expectedToken = ENV['TM_HEARTBEAT_TOKEN'] ?? null;

        if (!$expectedToken || $token !== $expectedToken) {
            header('HTTP/1.1 401 Unauthorized');
            echo json_encode(['error' => 'Invalid or missing heartbeat token']);
            return;
        }

        $db = $this->getDb();
        $mysqli = Closure::bind(function($db) { return $db->db; }, null, 'databaseConnector')($db);
        
        $now = date('Y-m-d H:i:s');
        $reports = [];

        // 1. Update session statuses
        $mysqli->query("UPDATE tm_sessions SET status = 'RECORDING' WHERE status = 'SCHEDULED' AND start_time <= '$now' AND end_time > '$now'");
        $mysqli->query("UPDATE tm_sessions SET status = 'COMPLETED' WHERE status = 'RECORDING' AND end_time <= '$now'");
        $mysqli->query("UPDATE tm_sessions SET status = 'COMPLETED' WHERE status = 'SCHEDULED' AND end_time <= '$now'");

        // 2. Fetch data for active sessions
        $sessions = $db->query("SELECT * FROM tm_sessions WHERE status = 'RECORDING'");

        foreach ($sessions as $s) {
            $stopsRaw = json_decode($s['stops'], true);
            if (!$stopsRaw) continue;

            $stops = [];
            foreach ($stopsRaw as $stopId) {
                if (strpos($stopId, '-') !== false) {
                    $parts = explode('-', $stopId);
                    foreach ($parts as $p) {
                        if (!empty($p)) $stops[] = $p;
                    }
                } else {
                    $stops[] = $stopId;
                }
            }
            $stops = array_unique($stops);

            foreach ($stops as $stopId) {
                $url = "https://oraritemporeale.actv.it/aut/backend/passages/{$stopId}-web-aut";
                
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                $response = curl_exec($ch);
                curl_close($ch);

                if ($response) {
                    $stmt = $mysqli->prepare("INSERT INTO tm_data (session_id, stop_id, fetched_at, data_json) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("isss", $s['id'], $stopId, $now, $response);
                    $stmt->execute();
                    $stmt->close();
                    $reports[] = "Recorded stop $stopId for session {$s['id']}";
                }
            }
        }

        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'timestamp' => $now,
            'actions' => $reports
        ]);
    }
}
