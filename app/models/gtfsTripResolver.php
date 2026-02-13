<?php
if (isset($_GET["return"]) || isset($_GET["rtable"])) {

    try {

        $tripId = $_GET['tripId'];

        $dataUnpacked = dbquery($db, $tripId);

        if (count($dataUnpacked) == 0) {
            echo json_encode(["error" => "No trips found", "query" => queryBuilder($tripId), "params" => [$tripId]]);
            exit;
        }

        // $dataUnpacked = $dataUnpacked;
        if (isset($_GET["return"])) {
            header("Content-Type: application/json");

            /* if ($limit == 1) {
                echo json_encode($trips[0]);
            }
            else {
                echo json_encode($trips);
            } */
            echo json_encode($dataUnpacked[0]);
            exit;
        }
        if (isset($_GET["rtable"])) {
            header("Content-Type: text/html");
            echo "<style>table { border-collapse: collapse; } table, th, td { border: 1px solid black; } th, td { padding: 5px; text-align: left; } </style>";
            echo associativeArrayToTable($trips);
            exit;
        }

    }
    catch (Exception $e) {
        echo json_encode(["error" => $e->getMessage(), "query" => queryBuilder($tripId), "params" => [$tripId]]);
        exit;
    }
}

function queryBuilder($tripId) {
    //busTrack, busDirection, day, time, stop, lineId
    $q = "SELECT SUBSTRING_INDEX(t.shape_id, '_', 1) AS line_id, 
        r.route_short_name AS bus_track, 
        t.trip_headsign AS bus_direction,
        r.route_desc AS line_tag
        -- Calcoliamo la differenza normalizzata in secondi
        -- time busTrack busDirection day stop lineId limit
        FROM trips t
        JOIN routes r ON t.route_id = r.route_id
        JOIN stop_times st ON t.trip_id = st.trip_id
        JOIN stops s ON st.stop_id = s.stop_id
        JOIN calendar c ON t.service_id = c.service_id
        WHERE t.trip_id = '{$tripId}'
        limit 1";
    return $q;
}

function dbquery(DatabaseConnector $db, $tripId)
{
    $tripId = addslashes($tripId);

    $query = queryBuilder($tripId);

    $dataUnpacked = $db->query($query);

    return $dataUnpacked;
}

function associativeArrayToTable(array $array)
{
    if (count($array) == 0) {
        return "<p>Nessun risultato</p>";
    }

    $tableStr = "<p>" . count($array) . " risultati</p>";

    $tableStr .= "<table class='table'>";

    $tripKeys = array_keys($array[0]);

    $tableStr .= "<tr>";
    foreach ($tripKeys as $key) {
        $tableStr .= "<th>{$key}</th>";
    }
    $tableStr .= "</tr>";

    foreach ($array as $trip) {
        $tableStr .= "<tr>";
        for ($i = 0; $i < count($trip); $i++) {
            $tableStr .= "<td>{$trip[$tripKeys[$i]]}</td>";
        }
        $tableStr .= "</tr>";
    }

    $tableStr .= "</table>";
    return $tableStr;
}

?>