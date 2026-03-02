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
    //busTrack, busDirection, day, time, stop, lineId
    $q = "SELECT * from stops";
    return $q;
}

function dbquery(DatabaseConnector $databaseConnector) {
    $query = queryBuilder();

    $dataUnpacked = $databaseConnector->query($query);

    return $dataUnpacked;
}

?>