<?php
if (isset($_GET["return"]) || isset($_GET["rtable"])) {
    
    try{
        /* $time = $_GET['time']; //07:07
        $busTrack = addslashes($_GET['busTrack']); //21
        $busDirection = addslashes($_GET['busDirection']); //Camporese Gritti
        $day = $_GET['day']; //monday
        $stop = addslashes($_GET['stop']); //Martellago delle Motte
        $lineId = $_GET['lineId']; //29387
        $limit = $_GET['limit'] ?? 1; */

        $time = $_GET['time'] ?? null; //07:07
        $busTrack = $_GET['busTrack'] ?? null; //21
        $busDirection = $_GET['busDirection'] ?? null; //Camporese Gritti
        $day = $_GET['day'] ?? null; //monday
        $stop = $_GET['stop'] ?? null; //Martellago delle Motte
        $lineId = $_GET['lineId'] ?? null; //29387
        $stopId = $_GET['stopId'] ?? null; //337-web-aut
        $limit = $_GET['limit'] ?? 1;
        
        $pdo = getPDOConnection();
        $trips = dbquery($pdo, $time, $busTrack, $busDirection, $day, $lineId, $stop, $stopId);

        if (count($trips) == 0) {
            header("Content-Type: application/json");
            echo json_encode([
                "error" => "No trips found", 
                "query" => queryBuilder($time, $busTrack, $busDirection, $day, $lineId, $stop),
                "params" => [
                    "time" => $time, 
                    "busTrack" => $busTrack, 
                    "busDirection" => $busDirection, 
                    "day" => $day, 
                    "lineId" => $lineId, 
                    "stop" => $stop,
                    "stopId" => $stopId
                ],
                "link" => $_SERVER["REQUEST_SCHEME"] . "://" . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"]
            ]);
            exit;
        }
        $trips = array_slice($trips, 0, $limit);
        if (isset($_GET["return"])) {
            header("Content-Type: application/json");

            if ($limit == 1) {
                echo json_encode($trips[0]);
            } else {
                echo json_encode($trips);
            }
            exit;
        }
        if (isset($_GET["rtable"])) {
            header("Content-Type: text/html");
            echo "<style>table { border-collapse: collapse; } table, th, td { border: 1px solid black; } th, td { padding: 5px; text-align: left; } </style>";
            echo associativeArrayToTable($trips);
            exit;
        }

    } catch (Exception $e) {
        header("Content-Type: application/json");
        echo json_encode([
            "error" => $e->getMessage(), 
            "query" => queryBuilder($time, $busTrack, $busDirection, $day, $lineId, $stop),
            "params" => [
                "time" => $time, 
                "busTrack" => $busTrack, 
                "busDirection" => $busDirection, 
                "day" => $day, 
                "lineId" => $lineId, 
                "stop" => $stop,
                "stopId" => $stopId
            ],
            "link" => $_SERVER["REQUEST_SCHEME"] . "://" . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"]
        ]);
        exit;
    }
}

function getPDOConnection() {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . ENV['DB_HOST'] . ";dbname=" . ENV['DB_NAME'] . ";charset=utf8mb4";
        $pdo = new PDO($dsn, ENV['DB_USER'], ENV['DB_PASS'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
    return $pdo;
}

function queryBuilder($time, $busTrack, $busDirection, $day, $lineId, $stop, $stopId = null) {
    if ($stopId && $stopId !== 'null' && $stopId !== 'undefined') {
        $stopQuery = "s.data_url LIKE CONCAT('%', ?, '%')";
    } else {
        $stopQuery = "s.stop_name LIKE CONCAT('%', ?, '%')";
    }

    $q = "SELECT t.*, st.arrival_time, st.departure_time, r.route_short_name,
            -- Calcoliamo la differenza circolare in secondi (distanza minima su 24h)
            SEC_TO_TIME(
                LEAST(
                    ABS((TIME_TO_SEC(st.arrival_time) % 86400) - (TIME_TO_SEC(?) % 86400)),
                    86400 - ABS((TIME_TO_SEC(st.arrival_time) % 86400) - (TIME_TO_SEC(?) % 86400))
                )
            ) AS delay
        FROM trips t
        JOIN routes r ON t.route_id = r.route_id
        JOIN stop_times st ON t.trip_id = st.trip_id
        JOIN stops s ON st.stop_id = s.stop_id
        JOIN calendar c ON t.service_id = c.service_id
        WHERE
            r.route_short_name = ?
            AND $stopQuery
            AND c.{$day} = 1
            AND st.pickup_type IN (0, 1)
        ORDER BY TIME_TO_SEC(delay) ASC
        LIMIT 30";
    return $q;
}

function get_actv_match_score($search, $target) {
    $normalize = function($s) {
        $s = mb_strtolower($s, 'UTF-8');
        // Mappatura abbreviazioni ACTV
        $replacements = [
            ' v.' => ' villaggio ',
            ' v ' => ' villaggio ',
            'p.le' => ' piazzale ',
            'f.s.' => ' ferroviaria ',
            ' p.' => ' porto ',
            ' s.' => ' san ', // es. S. Giuliano
            '.' => ' ',
            ',' => ' ',
            '-' => ' '
        ];
        $s = strtr($s, $replacements);
        
        // Esplode in parole e tiene solo quelle significative (>1 caratt.)
        return array_filter(explode(' ', $s), function($word) {
            return strlen(trim($word)) > 1;
        });
    };

    $tokensSearch = array_unique($normalize($search));
    $tokensTarget = array_unique($normalize($target));

    if (empty($tokensSearch)) return 0;

    // Conta quanti token della ricerca sono presenti nel target
    $matches = 0;
    foreach ($tokensSearch as $token) {
        foreach ($tokensTarget as $tTarget) {
            // Usiamo str_contains per gestire anche match parziali tra parole
            if (str_contains($tTarget, $token) || str_contains($token, $tTarget)) {
                $matches++;
                break; 
            }
        }
    }

    // Overlap Coefficient: rapporto tra match e numero di parole cercate
    return $matches / count($tokensSearch);
}

function addSimilarityScores(array &$trips, string $busDirection) {
    foreach ($trips as $i => $trip) {
        $overlap = get_actv_match_score($busDirection, $trip['trip_headsign']);
        $jaro = jaro_winkler(strtolower($busDirection), strtolower($trip['trip_headsign']));

        // Il punteggio principale è l'overlap (0-1)
        // Aggiungiamo il 10% del Jaro-Winkler per ordinare i risultati simili
        $trips[$i]['match_score'] = $overlap + ($jaro * 0.1);
    }

    // Ordina per il nuovo match_score
    usort($trips, function ($a, $b) {
        return $b['match_score'] <=> $a['match_score'];
    });
}

function addSimilarityScores_jaro(array &$trips, string $busDirection) {
    //Per ogni trip, calcola la distanza di jaro_winkler tra busDirection e trip_headsign
    foreach ($trips as $i => $trip) {
        $trips[$i]['jaro_winkler'] = jaro_winkler(strtolower($busDirection), strtolower($trip['trip_headsign']));
    }

    //Ordina per jaro_winkler
    usort($trips, function ($a, $b) {
        return $b['jaro_winkler'] <=> $a['jaro_winkler'];
    });
}

function dbquery(PDO $pdo, $time, $busTrack, $busDirection, $day, $lineId, $stop, $stopId = null) {
    $allowedDays = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
    if (!in_array(strtolower($day), $allowedDays)) {
        throw new Exception("Invalid day: $day");
    }

    $query = queryBuilder($time, $busTrack, $busDirection, $day, $lineId, $stop, $stopId);
    $stmt = $pdo->prepare($query);
    $paramStop = ($stopId && $stopId !== 'null' && $stopId !== 'undefined') ? $stopId : $stop;
    $stmt->execute([$time, $busTrack, $paramStop]);
    $trips = $stmt->fetchAll();

    addSimilarityScores($trips, $busDirection);

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
        $pdo = getPDOConnection();
        $busTracks = $pdo->query("SELECT DISTINCT route_short_name FROM routes")->fetchAll();

        //Salva in cache!
        if (!file_exists(BASE_PATH . "/data/cache/busDirections.json")) {
            $tableJoins = "
                INNER JOIN trips ON routes.route_id = trips.route_id 
                INNER JOIN stop_times ON trips.trip_id = stop_times.trip_id 
                INNER JOIN stops ON stop_times.stop_id = stops.stop_id 
                INNER JOIN calendar ON trips.service_id = calendar.service_id
                ";
            $busDirections = $pdo->query("SELECT DISTINCT stops.stop_name FROM routes {$tableJoins} WHERE stop_times.pickup_type = 1")->fetchAll();
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
            <input type="text" name="busDirection" id="busDirection" class="form-control" value="<?= $_POST['busDirection'] ?? '' ?>">
            <!-- <select name="busDirection" id="busDirection" class="form-select">
                <?php foreach ($busDirections as $busDirection) { ?>
                    <option value="<?= $busDirection['stop_name'] ?>" <?= (isset($_POST['busDirection']) && $_POST['busDirection'] == $busDirection['stop_name']) ? 'selected' : '' ?>><?= $busDirection['stop_name'] ?></option>
                <?php } ?>
            </select> -->

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
            <!-- Line ID -->
            <label for="lineId" class="form-label">Line ID</label>
            <input type="text" name="lineId" id="lineId" class="form-control" value="<?= $_POST['lineId'] ?? '' ?>">
            <!-- Stop -->
            <label for="stop" class="form-label">Stop</label>
            <input type="text" name="stop" id="stop" class="form-control" value="<?= $_POST['stop'] ?? '' ?>">

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
            $lineId = $_POST['lineId'];
            $stop = $_POST['stop'];

            $pdo = getPDOConnection();
            $trips = dbquery($pdo, $realTimeArrival, $busTrack, $busDirection, $day, $lineId, $stop);

            echo associativeArrayToTable($trips);
        }

        ?>
    </div>

</body>

<script>
    // const element = document.querySelector('#busDirection');
    // const choices = new Choices(element);
</script>

</html>
<?php
function jaro_winkler($str1, $str2) {
    // 1. Normalizzazione (Minuscolo e rimozione punti)
    $str1 = strtolower(str_replace('.', '', $str1));
    $str2 = strtolower(str_replace('.', '', $str2));

    $len1 = strlen($str1);
    $len2 = strlen($str2);

    if ($len1 == 0 || $len2 == 0) return 0.0;

    $max_dist = floor(max($len1, $len2) / 2) - 1;

    $match1 = array_fill(0, $len1, false);
    $match2 = array_fill(0, $len2, false);

    $matches = 0;
    $transpositions = 0;

    // Conteggio dei match
    for ($i = 0; $i < $len1; $i++) {
        $start = max(0, $i - $max_dist);
        $end = min($i + $max_dist + 1, $len2);

        for ($j = $start; $j < $end; $j++) {
            if ($match2[$j]) continue;
            if ($str1[(int)$i] != $str2[(int)$j]) continue;
            $match1[$i] = true;
            $match2[$j] = true;
            $matches++;
            break;
        }
    }

    if ($matches == 0) return 0.0;

    // Conteggio trasposizioni
    $k = 0;
    for ($i = 0; $i < $len1; $i++) {
        if (!$match1[$i]) continue;
        while (!$match2[$k]) $k++;
        if ($str1[$i] != $str2[$k]) $transpositions++;
        $k++;
    }

    $jaro = (($matches / $len1) + ($matches / $len2) + (($matches - $transpositions / 2) / $matches)) / 3;

    // Calcolo Winkler (Bonus per il prefisso comune)
    $prefix_len = 0;
    $max_prefix = 4; // Massimo 4 caratteri per il bonus
    for ($i = 0; $i < min($len1, $len2, $max_prefix); $i++) {
        if ($str1[$i] == $str2[$i]) $prefix_len++;
        else break;
    }

    return $jaro + ($prefix_len * 0.1 * (1 - $jaro));
}
?>