<?php
/**
 * Passaggi PREVISTI (da GTFS in DB) per una fermata.
 * Usato come fallback quando i dati real-time ACTV mancano o sono incompleti.
 *
 * La pagina fermata usa gli ID real-time ACTV (es. "4586-4587"), mappati agli
 * stop_id GTFS tramite stops.data_url. L'output è nello stesso formato dei
 * passaggi real-time (line, destination, time, real:false, stop, lineId,
 * timingPoints) così da poter essere unito e renderizzato dalle stesse card.
 *
 * Richiede $db (databaseConnector) già connesso.
 */
if (isset($_GET["return"]) || isset($_GET["rtable"])) {
    header("Content-Type: application/json");

    try {
        $stop = $_GET['stop'] ?? '';
        if ($stop === '') {
            echo json_encode([]);
            exit;
        }

        $allowedDays = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        $day = strtolower(date('l'));
        if (!in_array($day, $allowedDays, true)) {
            echo json_encode([]);
            exit;
        }

        $now = date('H:i:s');

        // $day è whitelisted: interpolazione sicura nel nome colonna
        $sql = "SELECT r.route_short_name AS line,
                       t.trip_headsign   AS destination,
                       st.departure_time AS dep_time,
                       s.stop_name       AS stop_name,
                       s.stop_id         AS stop_id,
                       t.route_id        AS lineId
                FROM stops s
                JOIN stop_times st ON st.stop_id = s.stop_id
                JOIN trips t       ON t.trip_id = st.trip_id
                JOIN routes r      ON r.route_id = t.route_id
                JOIN calendar c    ON c.service_id = t.service_id
                WHERE s.data_url LIKE CONCAT('%', ?, '%')
                  AND c.`$day` = 1
                  AND st.departure_time >= ?
                ORDER BY st.departure_time ASC
                LIMIT 40";

        $rows = $db->query($sql, [$stop, $now]);

        $seen = [];
        $out = [];
        foreach ($rows as $row) {
            $key = $row['line'] . '|' . $row['destination'] . '|' . $row['dep_time'];
            if (isset($seen[$key])) continue;
            $seen[$key] = true;

            $out[] = [
                'line'         => $row['line'],
                'destination'  => $row['destination'],
                'time'         => substr($row['dep_time'], 0, 5),
                'real'         => false,
                'stop'         => $row['stop_name'],
                'lineId'       => $row['lineId'],
                'timingPoints' => [[
                    'stop' => $row['stop_id'],
                    'time' => $row['dep_time'],
                ]],
            ];
        }

        echo json_encode($out);
        exit;

    } catch (\Throwable $e) {
        if (class_exists('Logger')) {
            Logger::log('EXCEPTION', $e->getMessage(), $e->getFile(), $e->getLine(), $e->getTraceAsString());
        }
        echo json_encode([]);
        exit;
    }
}
