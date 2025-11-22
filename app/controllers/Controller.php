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
}