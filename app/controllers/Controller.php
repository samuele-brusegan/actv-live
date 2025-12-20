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



    
}
