<?php
if (isset($_GET["return"]) || isset($_GET["rtable"])) {

    try {

        $dataUnpacked = dbquery($db);

        if (count($dataUnpacked) == 0) {
            echo json_encode(["error" => "No trips found", "query" => queryBuilder(), "params" => []]);
            exit;
        }

        // $dataUnpacked = $dataUnpacked;
        if (isset($_GET["return"])) {
            header("Content-Type: application/json");
            echo json_encode($dataUnpacked);
            exit;
        }
        if (isset($_GET["rtable"])) {
            header("Content-Type: text/html");
            echo "<style>table { border-collapse: collapse; } table, th, td { border: 1px solid black; } th, td { padding: 5px; text-align: left; } </style>";
            echo associativeArrayToTable($dataUnpacked);
            exit;
        }

    } catch (Exception $e) {
        echo json_encode(["error" => $e->getMessage(), "query" => queryBuilder(), "params" => []]);
        exit;
    }
}

function queryBuilder() {
    //from stop_times where stop_id = $_GET['stop'] and timeDifference positive and 30 minutes, timeDifference is the difference between the current time and arrival_time
    $q = "SELECT * from stop_times where stop_id = ? and (arrival_time - CURRENT_TIMESTAMP) > 0 and (arrival_time - CURRENT_TIMESTAMP) < 30";
    return $q;
}

function dbquery(DatabaseConnector $databaseConnector) {
    $query = queryBuilder();

    $dataUnpacked = $databaseConnector->query($query, [$_GET['stop']]);

    return $dataUnpacked;
}

?>