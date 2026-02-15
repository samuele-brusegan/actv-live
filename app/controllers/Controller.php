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
        //curl_close($ch);
        return json_decode($response, true) ?? [];
    }







    function linesMap() {
        require_once BASE_PATH . '/app/views/linesMap.php';
    }



    function tripDetails() {
        require_once BASE_PATH . '/app/views/tripDetails.php';
    }

    function logs() {
        if (!class_exists('databaseConnector')) {
            require_once BASE_PATH . '/app/models/databaseConnector.php';
        }
        $db = new databaseConnector();
        $db->connect(ENV['DB_USERNAME'], ENV['DB_PASSWORD'], ENV['DB_HOST'], ENV['DB_NAME']);
        
        $type = $_GET['type'] ?? null;
        $query = "SELECT * FROM logs";
        if ($type) {
            $query .= " WHERE type = '" . addslashes($type) . "'";
        }
        $query .= " ORDER BY created_at DESC LIMIT 100";
        $logs = $db->query($query);
        
        require_once BASE_PATH . '/app/views/admin/logs.php';
    }

    function adminDashboard() {
        require_once BASE_PATH . '/app/views/admin/dashboard.php';
    }



    function liveMap() {
        require_once BASE_PATH . '/app/views/liveBusMap.php';
    }
}
