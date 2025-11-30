<?php

class Controller {
	function index() {
        ini_set('display_errors', 1);
        ini_set('log_errors', 1);
        ini_set('error_log', BASE_PATH . '/php_error.log');
		require_once BASE_PATH . '/app/views/home.php';
	}
	
	function stops() {
        $stations = $this->fetchData('https://oraritemporeale.actv.it/aut/backend/page/stops');
        require_once BASE_PATH . '/app/views/stopList.php';
    }
	
	function stop() {
		// $resp = file_get_contents(BASE_PATH."/app/views/4586-4587-web-aut.json");
		require_once BASE_PATH . '/app/views/stop.php';
	}
	
	function routeFinder() {
		require_once BASE_PATH . '/app/views/routeFinder.php';
	}
	
	function stationSelector() {
		require_once BASE_PATH . '/app/views/stationSelector.php';
	}
	
	function routeResults() {
		require_once BASE_PATH . '/app/views/routeResults.php';
	}

    function routeDetails() {
        require_once BASE_PATH . '/app/views/routeDetails.php';
    }

    private function fetchData($url) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Often needed for dev/test
        $response = curl_exec($ch);
        curl_close($ch);
        return json_decode($response, true) ?? [];
    }
	
    function planRoute() {
        require_once BASE_PATH . '/app/services/RoutePlanner.php';
        
        $origin = $_GET['from'] ?? '';
        $dest = $_GET['to'] ?? '';
        $time = $_GET['time'] ?? date('H:i:s');
        
        file_put_contents(BASE_PATH . '/debug_log.txt', date('Y-m-d H:i:s') . " - Request: from=$origin, to=$dest, time=$time\n", FILE_APPEND);
        
        // Ensure time is in HH:MM:SS format
        if (strlen($time) == 5) {
            $time .= ':00';
        }
        
        try {
            $planner = new RoutePlanner();
            $routes = $planner->findRoutes($origin, $dest, $time);
            
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'routes' => $routes]);
        } catch (Exception $e) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    function stopsJson() {
        $file = BASE_PATH . '/data/gtfs/cache/stops.json';
        if (file_exists($file)) {
            header('Content-Type: application/json');
            readfile($file);
        } else {
            header('HTTP/1.1 404 Not Found');
            echo json_encode(['error' => 'Stops cache not found']);
        }
    }

	function favorite(){
		require_once BASE_PATH . '/app/models/addToFavorites.php';
	}

    function linesMap() {
        require_once BASE_PATH . '/app/views/linesMap.php';
    }

    function linesShapes() {
        // Increase memory limit and execution time for this heavy operation
        ini_set('memory_limit', '256M');
        set_time_limit(60);

        $cacheDir = BASE_PATH . '/data/gtfs/cache';
        $routesFile = $cacheDir . '/routes.json';
        $stopsFile = $cacheDir . '/stops.json';

        if (!file_exists($routesFile) || !file_exists($stopsFile)) {
            header('HTTP/1.1 500 Internal Server Error');
            echo json_encode(['error' => 'Cache files not found']);
            return;
        }

        $routes = json_decode(file_get_contents($routesFile), true);
        $stops = json_decode(file_get_contents($stopsFile), true);
        
        $shapes = [];

        foreach ($routes as $routeId => $routeInfo) {
            // Load specific route file
            // Sanitize route ID for filename
            $safeRouteId = preg_replace('/[^a-zA-Z0-9_-]/', '_', $routeId);
            $routeFile = $cacheDir . '/routes/route_' . $safeRouteId . '.json';

            if (file_exists($routeFile)) {
                $trips = json_decode(file_get_contents($routeFile), true);
                
                // Take the first trip as representative
                // Ideally we should find the "longest" trip or merge them, but first is a good start
                $firstTrip = reset($trips);
                
                if ($firstTrip) {
                    $path = [];
                    foreach ($firstTrip as $stop) {
                        $stopId = $stop['stop_id'];
                        if (isset($stops[$stopId])) {
                            $path[] = [
                                'lat' => $stops[$stopId]['lat'],
                                'lng' => $stops[$stopId]['lon'], // Note: stops.json uses 'lon' usually, check structure if needed
                                'name' => $stops[$stopId]['name']
                            ];
                        }
                    }
                    
                    if (!empty($path)) {
                        $shapes[] = [
                            'route_id' => $routeId,
                            'route_short_name' => $routeInfo['short_name'],
                            'route_long_name' => $routeInfo['long_name'],
                            'path' => $path
                        ];
                    }
                }
            }
        }

        header('Content-Type: application/json');
        echo json_encode($shapes);
    }

    function tripDetails() {
        require_once BASE_PATH . '/app/views/tripDetails.php';
    }

    function tripStops() {
        $line = $_GET['line'] ?? '';
        $dest = $_GET['dest'] ?? '';
        
        if (!$line) {
            header('HTTP/1.1 400 Bad Request');
            echo json_encode(['error' => 'Missing line parameter']);
            return;
        }

        $cacheDir = BASE_PATH . '/data/gtfs/cache';
        $routesFile = $cacheDir . '/routes.json';
        $stopsFile = $cacheDir . '/stops.json';

        if (!file_exists($routesFile) || !file_exists($stopsFile)) {
            header('HTTP/1.1 500 Internal Server Error');
            echo json_encode(['error' => 'Cache files not found']);
            return;
        }

        $routes = json_decode(file_get_contents($routesFile), true);
        $stops = json_decode(file_get_contents($stopsFile), true);

        // Find route ID by short name
        $targetRouteId = null;
        foreach ($routes as $id => $route) {
            if ($route['short_name'] == $line) {
                $targetRouteId = $id;
                break;
            }
        }

        if (!$targetRouteId) {
            header('HTTP/1.1 404 Not Found');
            echo json_encode(['error' => 'Route not found']);
            return;
        }

        // Load trips for this route
        $safeRouteId = preg_replace('/[^a-zA-Z0-9_-]/', '_', $targetRouteId);
        $routeFile = $cacheDir . '/routes/route_' . $safeRouteId . '.json';

        if (!file_exists($routeFile)) {
            header('HTTP/1.1 404 Not Found');
            echo json_encode(['error' => 'Trips not found']);
            return;
        }

        $trips = json_decode(file_get_contents($routeFile), true);
        
        // Find a trip that goes to 'dest'
        // If dest is not provided or not found, use the first trip
        $selectedTrip = null;
        
        if ($dest) {
            foreach ($trips as $tripId => $tripStops) {
                $lastStop = end($tripStops);
                $lastStopId = $lastStop['stop_id'];
                $lastStopName = $stops[$lastStopId]['name'] ?? '';
                
                // Simple fuzzy match or exact match
                if (stripos($lastStopName, $dest) !== false || stripos($dest, $lastStopName) !== false) {
                    $selectedTrip = $tripStops;
                    break;
                }
            }
        }
        
        if (!$selectedTrip) {
            $selectedTrip = reset($trips);
        }

        // Format response
        $result = [];
        foreach ($selectedTrip as $stop) {
            $stopId = $stop['stop_id'];
            if (isset($stops[$stopId])) {
                $result[] = [
                    'id' => $stopId,
                    'name' => $stops[$stopId]['name'],
                    'lat' => $stops[$stopId]['lat'],
                    'lon' => $stops[$stopId]['lon']
                ];
            }
        }

        header('Content-Type: application/json');
        echo json_encode($result);
    }
}