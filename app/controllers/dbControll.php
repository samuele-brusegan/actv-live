<?php
class dbControll {
    function api_gtfsIdentify() {

        include_once BASE_PATH . '/app/models/databaseConnector.php';

        $tableJoins = /*mysql*/ "
            INNER JOIN trips ON routes.route_id = trips.route_id 
            INNER JOIN stop_times ON trips.trip_id = stop_times.trip_id 
            INNER JOIN stops ON stop_times.stop_id = stops.stop_id 
            INNER JOIN calendar ON trips.service_id = calendar.service_id
            ";

        $db = new databaseConnector();
        $db->connect(ENV['DB_USERNAME'], ENV['DB_PASSWORD'], ENV['DB_HOST'], ENV['DB_NAME'], $tableJoins);

        require_once BASE_PATH . '/app/views/gtfsIdentify.php';
    }

    function gtfsTripBuilder() {
        if (!isset($_GET) && !isset($_GET['return'])) die("No parameters");
        
        require_once BASE_PATH . '/app/models/databaseConnector.php';
        
        $db = new databaseConnector();
        $db->connect(ENV['DB_USERNAME'], ENV['DB_PASSWORD'], ENV['DB_HOST'], ENV['DB_NAME']);

        $tripId = $_GET['trip_id'];

        header("Content-Type: application/json");

        $stops = $db->query("SELECT stops.*, stop_times.* FROM stops INNER JOIN stop_times ON stops.stop_id = stop_times.stop_id WHERE trip_id = '$tripId' ORDER BY stop_times.arrival_time");
        echo json_encode($stops);
    }

    function gtfsStopTranslater() {
        if (!isset($_GET) && !isset($_GET['return'])) die("No parameters");
        
        require_once BASE_PATH . '/app/models/databaseConnector.php';
        
        $db = new databaseConnector();
        $db->connect(ENV['DB_USERNAME'], ENV['DB_PASSWORD'], ENV['DB_HOST'], ENV['DB_NAME']);

        $tripId = $_GET['trip_id'];

        header("Content-Type: application/json");

        $stops = $db->query("SELECT stops., stop_times.* FROM stops INNER JOIN stop_times ON stops.stop_id = stop_times.stop_id WHERE trip_id = '$tripId' ORDER BY stop_times.arrival_time");
        echo json_encode($stops);
    }
}
?>