<?php
class ApiController {

    private function getDb($joins = "") {
        if (!class_exists('databaseConnector')) {
            require_once BASE_PATH . '/app/models/databaseConnector.php';
        }
        $db = databaseConnector::getInstance();
        $db->connect(ENV['DB_USER'], ENV['DB_PASS'], ENV['DB_HOST'], ENV['DB_NAME'], $joins);
        return $db;
    }

    private function requireAdminJson(): bool {
        if (AdminAuth::check()) return true;
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Autenticazione amministratore richiesta']);
        return false;
    }

    private function adminJsonInput(): ?array {
        $data = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($data)) $data = $_POST;
        if (!AdminAuth::verifyCsrf($data['csrf'] ?? null)) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Token CSRF non valido']);
            return null;
        }
        return $data;
    }

    function adminGtfsUpdateStatus() {
        if (!$this->requireAdminJson()) return;
        header('Content-Type: application/json');
        header('Cache-Control: no-store');
        try {
            require_once BASE_PATH . '/app/services/GtfsUpdateManager.php';
            echo json_encode(['success' => true, 'data' => (new GtfsUpdateManager())->getOverview()]);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    function adminGtfsUpdateConfig() {
        if (!$this->requireAdminJson()) return;
        $data = $this->adminJsonInput();
        if ($data === null) return;
        header('Content-Type: application/json');
        header('Cache-Control: no-store');
        try {
            require_once BASE_PATH . '/app/services/GtfsUpdateManager.php';
            $config = (new GtfsUpdateManager())->saveConfig($data);
            echo json_encode(['success' => true, 'config' => $config]);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    function adminGtfsUpdateStart() {
        if (!$this->requireAdminJson()) return;
        $data = $this->adminJsonInput();
        if ($data === null) return;
        header('Content-Type: application/json');
        header('Cache-Control: no-store');
        try {
            require_once BASE_PATH . '/app/services/GtfsUpdateManager.php';
            $result = (new GtfsUpdateManager())->start('manual');
            if (!$result['started']) http_response_code(409);
            echo json_encode(array_merge(['success' => $result['started']], $result));
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
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
        $tripId = $_GET['trip_id'] ?? '';

        header("Content-Type: application/json");
        if ($tripId === '') {
            header('HTTP/1.1 400 Bad Request');
            echo json_encode(['error' => 'Missing trip_id parameter']);
            return;
        }

        $db = $this->getDb();

        $stops = $db->query(
            "SELECT 
                stops.stop_id,
                stops.stop_code,
                stops.stop_name,
                stops.stop_lat,
                stops.stop_lon,
                stops.data_url,
                stop_times.trip_id,
                stop_times.arrival_time,
                stop_times.departure_time,
                stop_times.stop_sequence,
                stop_times.stop_headsign,
                stop_times.pickup_type,
                stop_times.drop_off_type,
                stop_times.shape_dist_traveled
            FROM stops
            INNER JOIN stop_times ON stops.stop_id = stop_times.stop_id
            WHERE stop_times.trip_id = ?
            ORDER BY stop_times.stop_sequence
            ",
            [$tripId]
        );

        // Alcuni trip contengono la stessa fermata due volte consecutive.
        $uniqueStops = [];
        $previousStopId = null;
        foreach ($stops as $stop) {
            if ((string) $stop['stop_id'] === $previousStopId) continue;
            $uniqueStops[] = $stop;
            $previousStopId = (string) $stop['stop_id'];
        }

        echo json_encode($uniqueStops);
    }

    // Refactored from dbControll::gtfsStopTranslater
    function gtfsStopTranslater() {
        $tripId = $_GET['trip_id'] ?? '';

        header("Content-Type: application/json");
        if ($tripId === '') {
            header('HTTP/1.1 400 Bad Request');
            echo json_encode(['error' => 'Missing trip_id parameter']);
            return;
        }

        $db = $this->getDb();

        // Fixed typo in original query: 'stops.,' -> 'stops.*,'
        $stops = $db->query("SELECT stops.*, stop_times.* FROM stops INNER JOIN stop_times ON stops.stop_id = stop_times.stop_id WHERE trip_id = ? ORDER BY stop_times.arrival_time", [$tripId]);
        echo json_encode($stops);
    }

    // Moved from Controller::stopsJson and renamed
    function stops() {
        $db = $this->getDb();

        header("Content-Type: application/json");

        $stops = $db->query("SELECT * FROM stops");
        echo json_encode($stops);
    }

    /**
     * Parse a "lat,lon" string into validated [lat, lon] floats, or null if invalid.
     */
    private function parseLatLon(string $value): ?array {
        $parts = explode(',', $value);
        if (count($parts) !== 2) {
            return null;
        }
        $lat = trim($parts[0]);
        $lon = trim($parts[1]);
        if (!is_numeric($lat) || !is_numeric($lon)) {
            return null;
        }
        $lat = (float) $lat;
        $lon = (float) $lon;
        if ($lat < -90 || $lat > 90 || $lon < -180 || $lon > 180) {
            return null;
        }
        return [$lat, $lon];
    }

    function planRoute() {
        require_once BASE_PATH . '/app/services/RoutePlanner.php';

        $origin = $_GET['from'] ?? '';
        $dest = $_GET['to'] ?? '';
        $time = $_GET['time'] ?? date('H:i:s');
        $optimize = $_GET['optimize'] ?? 'time';
        if (!in_array($optimize, ['time', 'transfers', 'walking'], true)) {
            $optimize = 'time';
        }

        // Ensure time is in HH:MM:SS format
        if (strlen($time) == 5) {
            $time .= ':00';
        }

        try {
            $planner = new RoutePlanner();
            $startWalk = null;
            $endWalk = null;
            $planningOrigin = $origin;
            $planningDest = $dest;
            $planningTime = $time;

            // Handle Origin Address
            if (strpos($origin, ',') !== false && ($coords = $this->parseLatLon($origin)) !== null) {
                $nearest = $planner->findNearestStop($coords[0], $coords[1]);

                if ($nearest) {
                    $planningOrigin = $nearest['id'];
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
            if (strpos($dest, ',') !== false && ($coords = $this->parseLatLon($dest)) !== null) {
                $nearest = $planner->findNearestStop($coords[0], $coords[1]);

                if ($nearest) {
                    $planningDest = $nearest['id'];
                    $endWalk = [
                        'stop_info' => $nearest
                    ];
                }
            }

            // Diagnostica: /api/plan-route?debug=1 (eventualmente con from/to/time)
            if (isset($_GET['debug'])) {
                header('Content-Type: application/json');
                $out = [
                    'received' => ['from' => $origin, 'to' => $dest, 'time' => $time],
                    'resolved' => ['origin' => $planningOrigin, 'dest' => $planningDest, 'time' => $planningTime],
                    'cache' => $planner->getCacheStats(),
                ];
                if ($planningOrigin !== '') $out['origin_debug'] = $planner->debugStop($planningOrigin);
                if ($planningDest !== '') $out['dest_debug'] = $planner->debugStop($planningDest);
                if ($planningOrigin !== '' && $planningDest !== '') {
                    $out['routes_found'] = count($planner->findRoutesMulti($planningOrigin, $planningDest, $planningTime));
                }
                echo json_encode($out, JSON_PRETTY_PRINT);
                return;
            }

            $routes = $planner->findRoutesMulti($planningOrigin, $planningDest, $planningTime);

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
            unset($route);

            // Apply user-selected optimization sort
            if ($optimize !== 'time') {
                usort($routes, function($a, $b) use ($optimize) {
                    if ($optimize === 'transfers') {
                        $ta = ($a['type'] ?? '') === 'transfer' ? 1 : 0;
                        $tb = ($b['type'] ?? '') === 'transfer' ? 1 : 0;
                        if ($ta !== $tb) return $ta - $tb;
                        return ($a['duration'] ?? 0) - ($b['duration'] ?? 0);
                    }
                    if ($optimize === 'walking') {
                        $wa = 0; $wb = 0;
                        foreach ($a['legs'] ?? [] as $l) {
                            if (($l['type'] ?? '') === 'walking') $wa += (int)($l['distance'] ?? 0);
                        }
                        foreach ($b['legs'] ?? [] as $l) {
                            if (($l['type'] ?? '') === 'walking') $wb += (int)($l['distance'] ?? 0);
                        }
                        if ($wa !== $wb) return $wa - $wb;
                        return ($a['duration'] ?? 0) - ($b['duration'] ?? 0);
                    }
                    return 0;
                });
            }

            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'routes' => $routes, 'optimize' => $optimize]);

        } catch (Exception $e) {
            Logger::log('EXCEPTION', $e->getMessage(), $e->getFile(), $e->getLine(), $e->getTraceAsString());
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Errore durante la pianificazione del percorso']);
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
                WHERE t.trip_id = ?
                ORDER BY st.stop_sequence ASC
            ";
            $results = $db->query($sql, [$targetTripId]);
        }
        // Caso B: Specifica Linea
        elseif ($targetLine) {
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
                WHERE r.route_short_name = ?
                ORDER BY r.route_id, st.stop_sequence ASC
            ";
            $results = $db->query($sql, [$targetLine]);
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
            $results = $db->query($sql);
        }

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
        $routeQuery = "SELECT route_id FROM routes WHERE route_short_name = ?";
        $routes = $db->query($routeQuery, [$line]);

        if (empty($routes)) {
            header('HTTP/1.1 404 Not Found');
            echo json_encode(['error' => 'Route not found']);
            return;
        }

        $targetRouteId = $routes[0]['route_id'];

        $selectedTripId = null;

        if ($dest) {
            $tripByHeadsign = $db->query("SELECT trip_id FROM trips WHERE route_id = ? AND trip_headsign LIKE ? LIMIT 1", [$targetRouteId, "%$dest%"]);

            if (!empty($tripByHeadsign)) {
                $selectedTripId = $tripByHeadsign[0]['trip_id'];
            } else {
                $deepQuery = "
                    SELECT t.trip_id 
                    FROM trips t
                    WHERE t.route_id = ?
                    AND (
                        SELECT s.stop_name 
                        FROM stop_times st 
                        JOIN stops s ON st.stop_id = s.stop_id 
                        WHERE st.trip_id = t.trip_id 
                        ORDER BY st.stop_sequence DESC 
                        LIMIT 1
                    ) LIKE ?
                    LIMIT 1
                ";
                $trips = $db->query($deepQuery, [$targetRouteId, "%$dest%"]);
                if (!empty($trips)) {
                    $selectedTripId = $trips[0]['trip_id'];
                }
            }
        }

        if (!$selectedTripId) {
            $trips = $db->query("SELECT trip_id FROM trips WHERE route_id = ? LIMIT 1", [$targetRouteId]);
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
            WHERE st.trip_id = ?
            ORDER BY st.stop_sequence ASC
        ";

        $stops = $db->query($stopsQuery, [$selectedTripId]);

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
        $dayParam = $_GET['day'] ?? strtolower(date('l'));

        $allowedDays = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
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
            if (isset($seen[$trip['trip_id']]))
                continue;

            $tParts = explode(':', $trip['arrival_time']);
            $tripSec = ($tParts[0] * 3600) + ($tParts[1] * 60);

            $diff = abs($tripSec - $searchSec);
            if ($diff > 43200)
                $diff = 86400 - $diff;

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
            'time' => $currentTime,
            'day' => $dayParam,
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

        // 1. Get stops for the trip
        $sqlStops = "SELECT s.stop_id, s.stop_name, s.stop_lat, s.stop_lon,
                    st.arrival_time, st.departure_time, st.stop_sequence, s.data_url
                FROM stop_times st
                JOIN stops s ON st.stop_id = s.stop_id
                WHERE st.trip_id = ?
                ORDER BY st.stop_sequence ASC";

        $stops = $db->query($sqlStops, [$tripId]);

        if (empty($stops)) {
            echo json_encode(['error' => 'No stops found for this trip']);
            return;
        }

        // 2. Get the shape for this trip
        $sqlShapeId = "SELECT shape_id FROM trips WHERE trip_id = ? LIMIT 1";
        $tripInfo = $db->query($sqlShapeId, [$tripId]);

        $shapePoints = [];
        if (!empty($tripInfo) && !empty($tripInfo[0]['shape_id'])) {
            $shapeId = $tripInfo[0]['shape_id'];
            $sqlShape = "SELECT lat, lng, sequence, dist_traveled 
                         FROM shapes_refined 
                         WHERE shape_id = ? 
                         ORDER BY sequence ASC";
            $shapePoints = $db->query($sqlShape, [$shapeId]);
        }

        echo json_encode([
            'stops' => $stops,
            'shape' => $shapePoints
        ]);
    }

    function gtfsStops() {
        $db = $this->getDb();
        require_once BASE_PATH . '/app/models/gtfsStops.php';
    }

    function gtfsPassages() {
        $db = $this->getDb();
        require_once BASE_PATH . '/app/models/gtfsPassages.php';
    }

    function stopLines() {
        require_once BASE_PATH . '/app/services/RoutePlanner.php';

        $stopId = $_GET['stop'] ?? '';
        $time = $_GET['time'] ?? date('H:i:s');

        if (strlen($time) == 5) {
            $time .= ':00';
        }

        if (empty($stopId)) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Parametro stop mancante']);
            return;
        }

        try {
            $planner = new RoutePlanner();
            $lines = $planner->getLinesForStop($stopId, $time);

            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'stop_id' => $stopId,
                'lines' => $lines
            ]);
        } catch (\Exception $e) {
            Logger::log('EXCEPTION', $e->getMessage(), $e->getFile(), $e->getLine(), $e->getTraceAsString());
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Errore durante il recupero delle linee']);
        }
    }
}
