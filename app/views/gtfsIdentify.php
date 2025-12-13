<?php
if (isset($_GET["return"])) {
    header("Content-Type: application/json");
    
    try{
        
        $time = $_GET['time'];
        $busTrack = addslashes($_GET['busTrack']);
        $busDirection = addslashes($_GET['busDirection']);
        $day = $_GET['day'];
        $stop = addslashes($_GET['stop']);
        
        $query = "SELECT t.*, st.arrival_time, st.departure_time,
            TIMEDIFF(STR_TO_DATE('{$time}', '%H:%i:%s'), st.arrival_time) AS delay
            FROM trips t
            JOIN routes r ON t.route_id = r.route_id
            JOIN stop_times st ON t.trip_id = st.trip_id
            JOIN stops s ON st.stop_id = s.stop_id
            JOIN calendar c ON t.service_id = c.service_id
            WHERE
            r.route_short_name = '{$busTrack}'
            AND s.stop_name = '{$stop}'
            AND c.{$day} = 1
            AND t.trip_headsign LIKE '%{$busDirection}%'
            AND st.pickup_type IN (0, 1)
            ORDER BY
            ABS(TIMEDIFF(STR_TO_DATE('{$time}', '%H:%i:%s'), st.arrival_time)) ASC
            LIMIT 10";

        $trips = $db->query(
            $query
        );

        if (count($trips) == 0) {
            echo json_encode(["error" => "No trips found", "query" => $query]);
            exit;
        }
        echo json_encode($trips[0]);
        exit;
    } catch (Exception $e) {
        echo json_encode(["error" => $e->getMessage(), "query" => $query]);
        exit;
    }
}

function dbquery(DatabaseConnector $db, $time, $busTrack, $busDirection, $day) {
    $busTrack = addslashes($busTrack);
    $busDirection = addslashes($busDirection);
    
    $trips = $db->query(
        "SELECT trips.*, stop_times.arrival_time,
        TIMEDIFF(STR_TO_DATE('{$time}', '%H:%i:%s'), stop_times.arrival_time) AS delay
        FROM routes 
        {$db->getJoins()}
        WHERE stops.stop_name = '{$busDirection}' AND routes.route_short_name = '{$busTrack}' AND calendar.{$day} = 1 AND stop_times.pickup_type = 1 
        order by abs(delay) asc"
    );

    if (count($trips) == 0) {
        return [];
    }

    //Rimuovi tutte le chiavi vuote che non sono valorizzate per nessun trip
    $keys = array_keys($trips[0]);

    foreach ($keys as $key) {

        $atLeastOneIsntNull = false;
        foreach ($trips as $trip) {
            if ($trip[$key] !== null && $trip[$key] !== "") {
                $atLeastOneIsntNull = true;
                break;
            }
        }

        if (!$atLeastOneIsntNull) {
            foreach ($trips as $i => $trip) {
                unset($trips[$i][$key]);
            }
        }
    }

    return $trips;
}

function associativeArrayToTable(array $array) {
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

<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GTFS Test</title>
    <!-- choices.js -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>
    <?php include_once COMMON_HTML_HEAD; ?>
</head>

<body>
    <div class="container d-flex align-items-center flex-column">
        <h1>GTFS Identifier</h1>

        <?php
        $busTracks = $db->query("SELECT DISTINCT route_short_name FROM routes");

        //Salva in cache!
        if (!file_exists(BASE_PATH . "/data/cache/busDirections.json")) {
            $busDirections = $db->query("SELECT DISTINCT stops.stop_name FROM routes {$db->getJoins()} WHERE stop_times.pickup_type = 1");
            file_put_contents(BASE_PATH . "/data/cache/busDirections.json", json_encode($busDirections));
        } else {
            $busDirections = json_decode(file_get_contents(BASE_PATH . "/data/cache/busDirections.json"), true);
        }
        ?>
        <form action="<?= $_SERVER['REQUEST_URI'] ?>" method="post" style="display: flex; flex-direction: column; width: 50%; gap: 1rem;">
            <label for="busTrack" class="form-label">Bus Track</label>

            <select name="busTrack" id="busTrack" class="form-select">
                <?php foreach ($busTracks as $busTrack) { ?>
                    <option value="<?= $busTrack['route_short_name'] ?>" <?= (isset($_POST['busTrack']) && $_POST['busTrack'] == $busTrack['route_short_name']) ? 'selected' : '' ?>><?= $busTrack['route_short_name'] ?></option>
                <?php } ?>
            </select>

            <label for="busDirection" class="form-label">Bus Direction</label>
            <select name="busDirection" id="busDirection" class="form-select">
                <?php foreach ($busDirections as $busDirection) { ?>
                    <option value="<?= $busDirection['stop_name'] ?>" <?= (isset($_POST['busDirection']) && $_POST['busDirection'] == $busDirection['stop_name']) ? 'selected' : '' ?>><?= $busDirection['stop_name'] ?></option>
                <?php } ?>
            </select>

            <label for="realTimeArrival" class="form-label">Real Time Arrival <i>(optional, used to calculate delay)</i></label>
            <input type="time" name="realTimeArrival" id="realTimeArrival" class="form-control" value="<?= $_POST['realTimeArrival'] ?? '' ?>">

            <label for="day" class="form-label">Day</label>
            <select name="day" id="day" class="form-select">
                <option <?= (isset($_POST['day']) && $_POST['day'] == 'monday')    ? 'selected' : '' ?> value="monday">Monday</option>
                <option <?= (isset($_POST['day']) && $_POST['day'] == 'tuesday')   ? 'selected' : '' ?> value="tuesday">Tuesday</option>
                <option <?= (isset($_POST['day']) && $_POST['day'] == 'wednesday') ? 'selected' : '' ?> value="wednesday">Wednesday</option>
                <option <?= (isset($_POST['day']) && $_POST['day'] == 'thursday')  ? 'selected' : '' ?> value="thursday">Thursday</option>
                <option <?= (isset($_POST['day']) && $_POST['day'] == 'friday')    ? 'selected' : '' ?> value="friday">Friday</option>
                <option <?= (isset($_POST['day']) && $_POST['day'] == 'saturday')  ? 'selected' : '' ?> value="saturday">Saturday</option>
                <option <?= (isset($_POST['day']) && $_POST['day'] == 'sunday')    ? 'selected' : '' ?> value="sunday">Sunday</option>
            </select>
            <input type="hidden" name="submit" value="submit">
            <button type="submit" style="margin-top: 1rem;" class="btn btn-primary">Submit</button>
        </form>


        <?php

        if (isset($_POST) && isset($_POST['submit'])) {
            echo "<hr width='80%'>";

            $busTrack = $_POST['busTrack'];
            $busDirection = $_POST['busDirection'];
            $realTimeArrival = $_POST['realTimeArrival'];
            $day = $_POST['day'];

            $trips = dbquery($db, $realTimeArrival, $busTrack, $busDirection, $day);

            echo associativeArrayToTable($trips);
        }

        ?>
    </div>

</body>

<script>
    const element = document.querySelector('#busDirection');
    const choices = new Choices(element);
</script>

</html>