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
        $rawTripGroups = array_values(array_filter(explode('|', (string) ($_GET['tripGroups'] ?? ''))));
        $tripGroupById = [];
        foreach ($rawTripGroups as $groupIndex => $rawGroup) {
            $groupNumber = $groupIndex + 1;
            if (preg_match('/^(\d+):(.*)$/', $rawGroup, $matches)) {
                $groupNumber = (int) $matches[1];
                $rawGroup = $matches[2];
            }
            foreach (array_filter(array_map('trim', explode(',', $rawGroup))) as $id) {
                $tripGroupById[(string) $id] = $groupNumber;
            }
        }
        $targetTripIds = array_values(array_unique(array_filter(array_map(
            'trim',
            explode(',', (string) ($_GET['tripIds'] ?? ''))
        ), fn($id) => $id !== '')));
        $targetTripIds = array_values(array_unique(array_merge($targetTripIds, array_keys($tripGroupById))));
        if ($targetTripId) {
            array_unshift($targetTripIds, $targetTripId);
            $targetTripIds = array_values(array_unique($targetTripIds));
        }

        // Indica se stiamo filtrando su una corsa/linea specifica: in tal caso
        // includiamo anche la "shape" snappata sulle strade (shapes_refined),
        // come fa la live-map. Per la vista "tutte le linee" restiamo sui
        // segmenti fermata-fermata per non appesantire la risposta.
        $isFiltered = !empty($targetTripIds) || $targetLine;

        // Costruzione dinamica della query
        // Caso A: uno o più Trip ID
        if (!empty($targetTripIds)) {
            $tripPlaceholders = implode(',', array_fill(0, count($targetTripIds), '?'));
            $sql = "
                SELECT
                    t.trip_id,
                    r.route_id,
                    r.route_short_name,
                    r.route_long_name,
                    t.shape_id AS shape_id,
                    s.stop_lat AS lat,
                    s.stop_lon AS lng,
                    s.stop_name AS name
                FROM trips t
                JOIN routes r ON t.route_id = r.route_id
                JOIN stop_times st ON t.trip_id = st.trip_id
                JOIN stops s ON st.stop_id = s.stop_id
                WHERE t.trip_id IN ($tripPlaceholders)
                ORDER BY t.trip_id, st.stop_sequence ASC
            ";
            $results = $db->query($sql, $targetTripIds);
        }
        // Caso B: Specifica Linea
        elseif ($targetLine) {
            $sql = "
                SELECT
                    r.route_id,
                    r.route_short_name,
                    r.route_long_name,
                    tr.shape_id AS shape_id,
                    s.stop_lat AS lat,
                    s.stop_lon AS lng,
                    s.stop_name AS name
                FROM routes r
                JOIN (
                    SELECT route_id, MIN(trip_id) as representative_trip_id
                    FROM trips
                    GROUP BY route_id
                ) t_rep ON r.route_id = t_rep.route_id
                JOIN trips tr ON tr.trip_id = t_rep.representative_trip_id
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
        // Con più trip della stessa linea, il trip_id deve restare parte della
        // chiave per evitare di fondere percorsi distinti in un'unica polilinea.
        foreach ($results as $row) {
            $routeKey = isset($row['trip_id'])
                ? $row['route_id'] . '|' . $row['trip_id']
                : $row['route_id'];

            // Se non abbiamo ancora inizializzato questa rotta nell'array, facciamolo
            if (!isset($routesMap[$routeKey])) {
                $routesMap[$routeKey] = [
                    'route_id' => $row['route_id'],
                    'trip_id' => $row['trip_id'] ?? null,
                    'group_number' => isset($row['trip_id']) ? ($tripGroupById[(string) $row['trip_id']] ?? null) : null,
                    'route_short_name' => $row['route_short_name'],
                    'route_long_name' => $row['route_long_name'],
                    'shape_id' => $row['shape_id'] ?? null,
                    'path' => []
                ];
            }

            // Aggiungiamo la fermata all'array 'path' della rotta corrente
            $routesMap[$routeKey]['path'][] = [
                'lat' => $row['lat'],
                'lng' => $row['lng'],
                'name' => $row['name']
            ];
        }

        // 2b. Se filtrato, carica le shape snappate sulle strade da shapes_refined
        // e allegale come 'shape' (lista di punti lat/lng) ad ogni rotta.
        if ($isFiltered) {
            $shapeIds = [];
            foreach ($routesMap as $r) {
                if (!empty($r['shape_id'])) {
                    $shapeIds[$r['shape_id']] = true;
                }
            }
            $shapeIds = array_keys($shapeIds);

            $pointsByShape = [];
            if (!empty($shapeIds)) {
                $ph = implode(',', array_fill(0, count($shapeIds), '?'));
                $shapeRows = $db->query(
                    "SELECT shape_id, lat, lng
                     FROM shapes_refined
                     WHERE shape_id IN ($ph)
                     ORDER BY shape_id, sequence ASC",
                    $shapeIds
                );
                foreach ($shapeRows as $sr) {
                    $pointsByShape[$sr['shape_id']][] = [
                        'lat' => (float) $sr['lat'],
                        'lng' => (float) $sr['lng'],
                    ];
                }
            }

            foreach ($routesMap as $rid => $r) {
                $sid = $r['shape_id'] ?? null;
                $routesMap[$rid]['shape'] = ($sid && isset($pointsByShape[$sid])) ? $pointsByShape[$sid] : [];
            }
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

        try {
            $todayDate = (new DateTime("this $dayParam", new DateTimeZone('Europe/Rome')))->format('Ymd');
            $yesterdayDate = (new DateTime("this $yesterday", new DateTimeZone('Europe/Rome')))->format('Ymd');
        } catch (Exception $e) {
            $todayDate = date('Ymd');
            $yesterdayDate = date('Ymd', strtotime('yesterday'));
        }

        // Query: bus di oggi + bus notturni di ieri (arrival_time >= 24:00:00)
        $sql = "SELECT r.route_short_name, r.route_id, t.trip_headsign, st.arrival_time, t.trip_id, 'today' as source
                FROM stops s
                JOIN stop_times st ON s.stop_id = st.stop_id
                JOIN trips t ON st.trip_id = t.trip_id
                JOIN routes r ON t.route_id = r.route_id
                LEFT JOIN calendar c ON t.service_id = c.service_id
                WHERE (
                    (
                        c.service_id IS NOT NULL
                        AND c.{$dayParam} = 1
                        AND ? BETWEEN c.start_date AND c.end_date
                        AND NOT EXISTS (
                            SELECT 1 FROM calendar_dates cd_ex
                            WHERE cd_ex.service_id = t.service_id
                            AND cd_ex.date = ?
                            AND cd_ex.exception_type = 2
                        )
                    )
                    OR EXISTS (
                        SELECT 1 FROM calendar_dates cd_in
                        WHERE cd_in.service_id = t.service_id
                        AND cd_in.date = ?
                        AND cd_in.exception_type = 1
                    )
                )
                UNION ALL
                SELECT r.route_short_name, r.route_id, t.trip_headsign, st.arrival_time, t.trip_id, 'yesterday' as source
                FROM stops s
                JOIN stop_times st ON s.stop_id = st.stop_id
                JOIN trips t ON t.trip_id = st.trip_id
                JOIN routes r ON t.route_id = r.route_id
                LEFT JOIN calendar c ON t.service_id = c.service_id
                WHERE (
                    (
                        c.service_id IS NOT NULL
                        AND c.{$yesterday} = 1
                        AND ? BETWEEN c.start_date AND c.end_date
                        AND NOT EXISTS (
                            SELECT 1 FROM calendar_dates cd_ex
                            WHERE cd_ex.service_id = t.service_id
                            AND cd_ex.date = ?
                            AND cd_ex.exception_type = 2
                        )
                    )
                    OR EXISTS (
                        SELECT 1 FROM calendar_dates cd_in
                        WHERE cd_in.service_id = t.service_id
                        AND cd_in.date = ?
                        AND cd_in.exception_type = 1
                    )
                ) AND st.arrival_time >= '24:00:00'
                ORDER BY arrival_time ASC";

        $allTrips = $db->query($sql, [
            $todayDate, $todayDate, $todayDate,
            $yesterdayDate, $yesterdayDate, $yesterdayDate
        ]);

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

    /**
     * Whitelist del nome colonna giorno (per evitare SQL injection nel nome
     * colonna, dato che non può essere parametrizzato).
     * @return string|null Il nome colonna valido oppure null.
     */
    private function dayColumn($day) {
        $allowedDays = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        $day = strtolower((string) $day);
        return in_array($day, $allowedDays, true) ? $day : null;
    }

    /**
     * Restituisce gli id (route_id) di tutte le rotte con quel route_short_name.
     */
    private function routeIdsForShortName(DatabaseConnector $db, $shortName) {
        $rows = $db->query("SELECT route_id FROM routes WHERE route_short_name = ?", [$shortName]);
        return array_map(fn($r) => $r['route_id'], $rows);
    }

    /**
     * API /api/line-variants
     * Dato un route_short_name (param: line) ritorna le varianti di percorso
     * raggruppate per (shape_id, trip_headsign). Per ogni variante: un trip
     * rappresentativo (quello con più fermate), numero di corse e l'elenco
     * ordinato delle fermate.
     */
    function lineVariants() {
        header('Content-Type: application/json');

        $line = trim($_GET['line'] ?? '');
        $day = $this->dayColumn($_GET['day'] ?? date('l'));
        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date'] ?? '')
            ? str_replace('-', '', $_GET['date'])
            : date('Ymd');
        if ($line === '') {
            echo json_encode(['success' => false, 'error' => 'Parametro line mancante']);
            return;
        }
        if ($day === null) {
            echo json_encode(['success' => false, 'error' => 'Giorno non valido']);
            return;
        }

        $db = $this->getDb();
        $routeIds = $this->routeIdsForShortName($db, $line);
        if (empty($routeIds)) {
            echo json_encode(['success' => false, 'error' => 'Linea non trovata']);
            return;
        }

        $placeholders = implode(',', array_fill(0, count($routeIds), '?'));

        // Un record per trip con shape, capolinea e numero di fermate.
        $sql = "SELECT t.trip_id, t.shape_id, t.trip_headsign, t.direction_id,
                       COUNT(st.stop_id) AS nstops
                FROM trips t
                JOIN stop_times st ON st.trip_id = t.trip_id
                WHERE t.route_id IN ($placeholders)
                  AND (
                      t.service_id IN (
                          SELECT c.service_id FROM calendar c
                          WHERE c.{$day} = 1 AND c.start_date <= ? AND c.end_date >= ?
                      )
                      OR t.service_id IN (
                          SELECT cd.service_id FROM calendar_dates cd
                          WHERE cd.date = ? AND cd.exception_type = 1
                      )
                  )
                  AND t.service_id NOT IN (
                      SELECT cd.service_id FROM calendar_dates cd
                      WHERE cd.date = ? AND cd.exception_type = 2
                  )
                GROUP BY t.trip_id, t.shape_id, t.trip_headsign, t.direction_id";
        $trips = $db->query($sql, array_merge($routeIds, [$date, $date, $date, $date]));

        // Raggruppa per (shape_id + headsign): scegli il trip con più fermate
        // come rappresentativo e conta quante corse appartengono alla variante.
        $variants = [];
        foreach ($trips as $t) {
            $key = ($t['shape_id'] ?? '') . '|' . ($t['trip_headsign'] ?? '');
            if (!isset($variants[$key])) {
                $variants[$key] = [
                    'shape_id'      => $t['shape_id'],
                    'headsign'      => $t['trip_headsign'],
                    'direction_id'  => $t['direction_id'],
                    'trip_id'       => $t['trip_id'],
                    'stops_count'   => (int) $t['nstops'],
                    'trips_count'   => 0,
                ];
            }
            $variants[$key]['trips_count']++;
            if ((int) $t['nstops'] > $variants[$key]['stops_count']) {
                $variants[$key]['stops_count'] = (int) $t['nstops'];
                $variants[$key]['trip_id'] = $t['trip_id'];
            }
        }

        // Aggiungi l'elenco fermate del trip rappresentativo per ogni variante.
        $result = [];
        foreach ($variants as $v) {
            $stops = $db->query(
                "SELECT s.stop_id AS id, s.stop_name AS name, s.stop_lat AS lat, s.stop_lon AS lng,
                        st.stop_sequence AS seq, st.departure_time AS time
                 FROM stop_times st
                 JOIN stops s ON st.stop_id = s.stop_id
                 WHERE st.trip_id = ?
                 ORDER BY st.stop_sequence ASC",
                [$v['trip_id']]
            );
            $v['stops'] = $stops;
            $v['origin'] = $stops[0]['name'] ?? null;
            $v['terminus'] = end($stops)['name'] ?? null;
            $result[] = $v;
        }

        // Ordina per numero di corse (le varianti più frequenti per prime).
        usort($result, fn($a, $b) => $b['trips_count'] <=> $a['trips_count']);

        echo json_encode([
            'success'  => true,
            'line'     => $line,
            'variants' => $result,
        ]);
    }

    /**
     * API /api/line-schedule
     * Per le varianti indicate (param: trips = trip_id rappresentativi) ritorna,
     * per ogni fermata, TUTTI gli orari del giorno (param: day) della linea
     * (param: line) a quella fermata.
     */
    function lineSchedule() {
        header('Content-Type: application/json');

        $line   = trim($_GET['line'] ?? '');
        $representativeIds = array_values(array_unique(array_filter(array_map(
            'trim',
            explode(',', (string) ($_GET['trips'] ?? ($_GET['trip'] ?? '')))
        ), fn($id) => $id !== '')));
        $day    = $this->dayColumn($_GET['day'] ?? date('l'));
        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date'] ?? '')
            ? str_replace('-', '', $_GET['date'])
            : date('Ymd');

        if ($line === '' || empty($representativeIds)) {
            echo json_encode(['success' => false, 'error' => 'Parametri line/trips mancanti']);
            return;
        }
        if ($day === null) {
            echo json_encode(['success' => false, 'error' => 'Giorno non valido']);
            return;
        }

        $db = $this->getDb();
        $routeIds = $this->routeIdsForShortName($db, $line);
        if (empty($routeIds)) {
            echo json_encode(['success' => false, 'error' => 'Linea non trovata']);
            return;
        }

        // Varianti = coppie (shape_id, trip_headsign) dei trip rappresentativi.
        $repPh = implode(',', array_fill(0, count($representativeIds), '?'));
        $repInfo = $db->query(
            "SELECT trip_id, shape_id, trip_headsign FROM trips WHERE trip_id IN ($repPh)",
            $representativeIds
        );
        if (empty($repInfo)) {
            echo json_encode(['success' => false, 'error' => 'Variante non trovata']);
            return;
        }
        $headsign = $repInfo[0]['trip_headsign'];

        // Costruisce una sequenza unificata partendo dal percorso più lungo.
        $stopsByRepresentative = [];
        foreach ($representativeIds as $representativeId) {
            $variantStops = $db->query(
                "SELECT st.stop_sequence AS seq, s.stop_id AS id, s.stop_name AS name
             FROM stop_times st
             JOIN stops s ON st.stop_id = s.stop_id
             WHERE st.trip_id = ?
             ORDER BY st.stop_sequence ASC",
                [$representativeId]
            );
            if (!empty($variantStops)) $stopsByRepresentative[] = $variantStops;
        }
        usort($stopsByRepresentative, fn($a, $b) => count($b) <=> count($a));
        $stops = $stopsByRepresentative[0] ?? [];
        if (empty($stops)) {
            echo json_encode(['success' => false, 'error' => 'Variante senza fermate']);
            return;
        }

        $normalizeStopName = function ($name) {
            $name = trim((string) $name);
            return function_exists('mb_strtolower')
                ? mb_strtolower($name, 'UTF-8')
                : strtolower($name);
        };
        $stopKey = fn($s) => $normalizeStopName($s['name']);

        // Alcuni trip GTFS contengono la stessa fermata due volte consecutive
        // con stop_id diversi (es. Maerne FS). In tabella deve comparire una
        // sola riga, dato che nome e orario di passaggio coincidono.
        $dedupeConsecutiveStops = function ($items) use ($stopKey) {
            $result = [];
            $previousKey = null;
            foreach ($items as $item) {
                $key = $stopKey($item);
                if ($key === $previousKey) continue;
                $result[] = $item;
                $previousKey = $key;
            }
            return $result;
        };
        $stops = $dedupeConsecutiveStops($stops);

        foreach (array_slice($stopsByRepresentative, 1) as $variantStops) {
            $variantStops = $dedupeConsecutiveStops($variantStops);
            $pending = [];
            foreach ($variantStops as $stop) {
                $keys = array_map($stopKey, $stops);
                $matchIndex = array_search($stopKey($stop), $keys, true);
                if ($matchIndex === false) {
                    $pending[] = $stop;
                    continue;
                }
                if (!empty($pending)) {
                    array_splice($stops, $matchIndex, 0, $pending);
                    $pending = [];
                }
            }
            if (!empty($pending)) $stops = array_merge($stops, $pending);
        }

        $routePh = implode(',', array_fill(0, count($routeIds), '?'));

        // Tutte le corse delle varianti del giro nel giorno.
        $variantClauses = [];
        $variantParams = [];
        foreach ($repInfo as $representative) {
            $variantClauses[] = '(t.shape_id = ? AND t.trip_headsign = ?)';
            $variantParams[] = $representative['shape_id'];
            $variantParams[] = $representative['trip_headsign'];
        }
        $tripsSql = "SELECT DISTINCT t.trip_id
                     FROM trips t
                     WHERE t.route_id IN ($routePh)
                       AND (" . implode(' OR ', $variantClauses) . ")
                       AND (
                           t.service_id IN (
                               SELECT c.service_id FROM calendar c
                               WHERE c.{$day} = 1 AND c.start_date <= ? AND c.end_date >= ?
                           )
                           OR t.service_id IN (
                               SELECT cd.service_id FROM calendar_dates cd
                               WHERE cd.date = ? AND cd.exception_type = 1
                           )
                       )
                       AND t.service_id NOT IN (
                           SELECT cd.service_id FROM calendar_dates cd
                           WHERE cd.date = ? AND cd.exception_type = 2
                       )";
        $params = array_merge($routeIds, $variantParams, [$date, $date, $date, $date]);
        $tripRows = $db->query($tripsSql, $params);
        $tripIds = array_map(fn($r) => $r['trip_id'], $tripRows);

        $runs = [];
        if (!empty($tripIds)) {
            $tripPh = implode(',', array_fill(0, count($tripIds), '?'));
            // Orari di ogni corsa a ogni fermata (allineati per stop_sequence).
            $stRows = $db->query(
                "SELECT st.trip_id, s.stop_name, st.departure_time
                 FROM stop_times st
                 JOIN stops s ON s.stop_id = st.stop_id
                 WHERE st.trip_id IN ($tripPh)
                 ORDER BY st.trip_id, st.stop_sequence ASC",
                $tripIds
            );

            $rowIndexByName = [];
            foreach ($stops as $i => $stop) $rowIndexByName[$stopKey($stop)] = $i;

            // Allinea le corse alle righe tramite il nome fermata: le estensioni
            // corte lasciano vuote le righe Venezia e usano MESTRE CENTRO B3.
            $byTrip = [];
            foreach ($stRows as $r) {
                $key = $normalizeStopName($r['stop_name']);
                if (!isset($rowIndexByName[$key])) continue;
                $byTrip[$r['trip_id']][$rowIndexByName[$key]] = substr($r['departure_time'], 0, 5);
            }

            foreach ($byTrip as $tid => $timesByRow) {
                $times = [];
                for ($i = 0; $i < count($stops); $i++) {
                    $times[] = $timesByRow[$i] ?? null;
                }
                // Orario di partenza = primo tempo non nullo (per ordinare le colonne).
                $start = '99:99';
                foreach ($times as $t) {
                    if ($t !== null) { $start = $t; break; }
                }
                $runs[] = ['trip_id' => $tid, 'start' => $start, 'times' => $times];
            }

            // Ordina le colonne per orario di partenza.
            usort($runs, fn($a, $b) => strcmp($a['start'], $b['start']));
        }

        // Le varianti merged possono non essere attive nel giorno selezionato.
        // Non mostrare fermate appartenenti solo a varianti senza alcuna corsa,
        // altrimenti la tabella contiene intere righe di punti.
        if (!empty($runs)) {
            $activeRows = [];
            for ($i = 0; $i < count($stops); $i++) {
                foreach ($runs as $run) {
                    if (($run['times'][$i] ?? null) !== null) {
                        $activeRows[] = $i;
                        break;
                    }
                }
            }

            $stops = array_values(array_map(fn($i) => $stops[$i], $activeRows));
            foreach ($runs as &$run) {
                $run['times'] = array_values(array_map(fn($i) => $run['times'][$i] ?? null, $activeRows));
            }
            unset($run);
        }

        $stopsOut = array_map(fn($s) => ['id' => $s['id'], 'name' => $s['name']], $stops);

        echo json_encode([
            'success'  => true,
            'line'     => $line,
            'day'      => $day,
            'headsign' => $headsign,
            'stops'    => $stopsOut,
            'runs'     => $runs,
        ]);
    }

    /**
     * API /api/stop-upcoming
     * Dato uno stop_id (param: stop), un giorno (param: day) e un'ora
     * (param: time HH:MM), ritorna tutti i bus (qualsiasi linea) previsti a
     * quella fermata entro la prossima ora.
     */
    function stopUpcoming() {
        header('Content-Type: application/json');

        $stopId = $_GET['stop'] ?? '';
        $time   = $_GET['time'] ?? date('H:i');
        $day    = $this->dayColumn($_GET['day'] ?? date('l'));

        if ($stopId === '') {
            echo json_encode(['success' => false, 'error' => 'Parametro stop mancante']);
            return;
        }
        if ($day === null) {
            echo json_encode(['success' => false, 'error' => 'Giorno non valido']);
            return;
        }

        $db = $this->getDb();

        $allowedDays = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        $yesterday = $allowedDays[(array_search($day, $allowedDays) + 6) % 7];

        // Bus di oggi a questa fermata + notturni di ieri (arrival >= 24:00:00).
        $sql = "SELECT r.route_short_name, r.route_id, t.trip_headsign, st.departure_time, t.trip_id, 'today' AS source
                FROM stop_times st
                JOIN trips t ON st.trip_id = t.trip_id
                JOIN routes r ON t.route_id = r.route_id
                JOIN calendar c ON t.service_id = c.service_id
                WHERE st.stop_id = ? AND c.{$day} = 1
                UNION ALL
                SELECT r.route_short_name, r.route_id, t.trip_headsign, st.departure_time, t.trip_id, 'yesterday' AS source
                FROM stop_times st
                JOIN trips t ON st.trip_id = t.trip_id
                JOIN routes r ON t.route_id = r.route_id
                JOIN calendar c ON t.service_id = c.service_id
                WHERE st.stop_id = ? AND c.{$yesterday} = 1 AND st.departure_time >= '24:00:00'";
        $rows = $db->query($sql, [$stopId, $stopId]);

        $timeParts = explode(':', $time);
        $searchSec = ((int) $timeParts[0] * 3600) + ((int) ($timeParts[1] ?? 0) * 60);
        $window = 3600; // prossima ora

        $seen = [];
        $results = [];
        foreach ($rows as $row) {
            if (isset($seen[$row['trip_id']])) continue;

            $tp = explode(':', $row['departure_time']);
            $tripSec = ((int) $tp[0] * 3600) + ((int) ($tp[1] ?? 0) * 60);

            $delta = $tripSec - $searchSec;
            // Notturni di ieri richiesti nelle ore piccole (es. 24:10 cercato alle 00:10).
            if ($row['source'] === 'yesterday' && $searchSec < 3600) {
                $delta = $tripSec - 86400 - $searchSec;
            }

            if ($delta >= 0 && $delta <= $window) {
                $seen[$row['trip_id']] = true;
                $results[] = [
                    'route_short_name' => $row['route_short_name'],
                    'route_id'         => $row['route_id'],
                    'trip_headsign'    => $row['trip_headsign'],
                    'trip_id'          => $row['trip_id'],
                    'time'             => substr($row['departure_time'], 0, 5),
                    'in_min'           => (int) round($delta / 60),
                ];
            }
        }

        usort($results, fn($a, $b) => $a['in_min'] <=> $b['in_min']);

        echo json_encode([
            'success' => true,
            'stop'    => $stopId,
            'day'     => $day,
            'time'    => $time,
            'count'   => count($results),
            'buses'   => $results,
        ]);
    }
}
