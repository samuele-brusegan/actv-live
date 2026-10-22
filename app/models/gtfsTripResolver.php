<?php
if (isset($_GET["return"]) || isset($_GET["rtable"])) {

    try {

        $tripId = $_GET['tripId'];

        $dataUnpacked = dbquery($db, $tripId);

        // I feed real-time possono usare prefissi diversi dal GTFS statico
        // (es. AUT_LL_793_* invece di AUT_ACTV_794_*). Il suffisso numerico
        // identifica comunque la corsa: riprova con match sul suffisso.
        if (count($dataUnpacked) == 0 && preg_match('/_(\d+)$/', $tripId, $m)) {
            $dataUnpacked = dbquery($db, '%_' . $m[1], true);
        }

        if (count($dataUnpacked) == 0) {
            echo json_encode(["error" => "No trips found", "query" => queryBuilder(), "params" => [$tripId]]);
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
            echo associativeArrayToTable($dataUnpacked);
            exit;
        }

    }
    catch (Exception $e) {
        echo json_encode(["error" => $e->getMessage(), "query" => queryBuilder(), "params" => [$tripId]]);
        exit;
    }
}

function queryBuilder() {
    $q = "SELECT SUBSTRING_INDEX(t.shape_id, '_', 1) AS line_id,
        r.route_short_name AS bus_track,
        t.trip_headsign AS bus_direction,
        r.route_desc AS line_tag
        FROM trips t
        JOIN routes r ON t.route_id = r.route_id
        WHERE t.trip_id = ?
        LIMIT 1";
    return $q;
}

function dbquery(DatabaseConnector $db, $tripId, $bySuffix = false)
{
    $query = queryBuilder();
    if ($bySuffix) {
        $query = str_replace('WHERE t.trip_id = ?', 'WHERE t.trip_id LIKE ?', $query);
    }

    $dataUnpacked = $db->query($query, [$tripId]);

    return $dataUnpacked;
}

?>