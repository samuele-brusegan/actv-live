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
            return;
        }

        $allowedDays = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        $day = strtolower(date('l'));
        if (!in_array($day, $allowedDays, true)) {
            echo json_encode([]);
            return;
        }

        $now = date('H:i:s');
        $date = date('Ymd');

        // Risolvi prima la piccola tabella fermate. Il precedente LIKE era
        // applicato dentro il join principale e costringeva MySQL a esaminare
        // una porzione enorme di stop_times.
        $matchedStops = $db->query(
            "SELECT stop_id, stop_name, stop_lat, stop_lon
             FROM stops WHERE data_url LIKE CONCAT('%', ?, '%')",
            [$stop]
        );
        if (!$matchedStops) {
            echo json_encode([]);
            return;
        }
        $stopIds = array_values(array_unique(array_map(fn($row) => (string)$row['stop_id'], $matchedStops)));

        // Calcola una volta i servizi attivi, evitando due EXISTS correlati
        // per ogni riga candidata di stop_times.
        $activeServices = [];
        foreach ($db->query(
            "SELECT service_id FROM calendar
             WHERE `$day` = 1 AND ? BETWEEN start_date AND end_date",
            [$date]
        ) as $row) {
            $activeServices[(string)$row['service_id']] = true;
        }
        foreach ($db->query(
            "SELECT service_id, exception_type FROM calendar_dates WHERE date = ?",
            [$date]
        ) as $exception) {
            $serviceId = (string)$exception['service_id'];
            if ((int)$exception['exception_type'] === 1) $activeServices[$serviceId] = true;
            elseif ((int)$exception['exception_type'] === 2) unset($activeServices[$serviceId]);
        }
        $serviceIds = array_keys($activeServices);
        if (!$serviceIds) {
            echo json_encode([]);
            return;
        }

        $stopPlaceholders = implode(',', array_fill(0, count($stopIds), '?'));
        $servicePlaceholders = implode(',', array_fill(0, count($serviceIds), '?'));
        $sql = "SELECT SUBSTRING_INDEX(SUBSTRING_INDEX(t.shape_id, '_', 3), '_', -1) AS lineId,
                    t.trip_headsign   AS destination,
                    t.trip_id,
                    st.departure_time AS dep_time,
                    s.stop_lat,
                    s.stop_lon,
                    s.stop_id       AS stop_name,
                    s.stop_name         AS stop_id,
                    t.route_id        AS line,
                    r.route_color,
                    r.route_text_color
                FROM stops s
                JOIN stop_times st ON st.stop_id = s.stop_id
                JOIN trips t       ON t.trip_id = st.trip_id
                JOIN routes r      ON r.route_id = t.route_id
                WHERE st.stop_id IN ($stopPlaceholders)
                  AND t.service_id IN ($servicePlaceholders)
                  AND st.departure_time >= ?
                ORDER BY st.departure_time ASC
                LIMIT 40";

        $rows = $db->query($sql, array_merge($stopIds, $serviceIds, [$now]));

        $seen = [];
        $out = [];
        foreach ($rows as $row) {
            $key = $row['line'] . '|' . $row['destination'] . '|' . $row['dep_time'];
            if (isset($seen[$key])) continue;
            $seen[$key] = true;

            $out[] = [
                'line'         => $row['line'],
                'destination'  => $row['destination'],
                'trip_id'      => $row['trip_id'],
                'gtfs_stop_id' => $row['stop_name'],
                'time'         => substr($row['dep_time'], 0, 5),
                'real'         => false,
                'stop'         => $row['stop_name'],
                'lineId'       => $row['lineId'],
                'route_color'  => $row['route_color'],
                'route_text_color' => $row['route_text_color'],
                'stop_lat'     => (float) $row['stop_lat'],
                'stop_lon'     => (float) $row['stop_lon'],
                'timingPoints' => [[
                    'stop' => $row['stop_id'],
                    'time' => $row['dep_time'],
                ]],
            ];
        }

        echo json_encode($out);
        return;

    } catch (\Throwable $e) {
        if (class_exists('Logger')) {
            Logger::log('EXCEPTION', $e->getMessage(), $e->getFile(), $e->getLine(), $e->getTraceAsString());
        }
        echo json_encode([]);
        return;
    }
}
