<?php

function getBusesSmartMidnight(PDO $pdo, $currentTime, $day) {
    $allowedDays = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
    $dayIndex = array_search(strtolower($day), $allowedDays);
    
    // Giorno precedente per catturare le corse "24:00+" iniziate ieri
    $yesterday = $allowedDays[($dayIndex + 6) % 7];

    // 1. Prendiamo i bus di oggi E i bus di "ieri" (per le ore piccole)
    // Usiamo una UNION per essere sicuri di non mancare i notturni
    $sql = "SELECT r.route_short_name, t.trip_headsign, st.arrival_time, t.trip_id, 'today' as source
            FROM stops s
            JOIN stop_times st ON s.stop_id = st.stop_id
            JOIN trips t ON st.trip_id = t.trip_id
            JOIN routes r ON t.route_id = r.route_id
            JOIN calendar c ON t.service_id = c.service_id
            WHERE c.{$day} = 1
            UNION ALL
            SELECT r.route_short_name, t.trip_headsign, st.arrival_time, t.trip_id, 'yesterday' as source
            FROM stops s
            JOIN stop_times st ON s.stop_id = st.stop_id
            JOIN trips t ON t.trip_id = st.trip_id
            JOIN routes r ON t.route_id = r.route_id
            JOIN calendar c ON t.service_id = c.service_id
            WHERE c.{$yesterday} = 1 AND st.arrival_time >= '24:00:00'
            ORDER BY arrival_time ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $allTrips = $stmt->fetchAll();

    $results = [];
    
    // Convertiamo l'orario di ricerca in secondi dall'inizio del giorno (0-86400)
    $timeParts = explode(':', $currentTime);
    $searchSec = ($timeParts[0] * 3600) + ($timeParts[1] * 60);
    
    $range = 1800; // 30 minuti

    foreach ($allTrips as $trip) {
        // Convertiamo l'orario del DB (che può essere > 24:00:00)
        $tParts = explode(':', $trip['arrival_time']);
        $tripSec = ($tParts[0] * 3600) + ($tParts[1] * 60);

        // Calcoliamo la differenza assoluta minima su un cerchio di 24 ore (86400 sec)
        $diff = abs($tripSec - $searchSec);
        
        // Gestione del "giro della morte": la differenza può essere piccola 
        // anche se un tempo è 23:55 e l'altro è 00:05 (86400 - 85800 + 300)
        if ($diff > 43200) { 
            $diff = 86400 - $diff;
        }

        if ($diff <= $range) {
            // Aggiungiamo un campo per l'utente per capire se è tra poco o è già passato
            $trip['diff_min'] = round(($tripSec - $searchSec) / 60);
            
            // Correzione per i passaggi che arrivano dal giorno prima (es. 24:10 cercato alle 00:10)
            if ($trip['source'] == 'yesterday' && $searchSec < 3600) {
                 $trip['diff_min'] = round(($tripSec - 86400 - $searchSec) / 60);
            }

            $results[] = $trip;
        }
    }
    
    // Ri-ordiniamo per differenza temporale reale
    usort($results, function($a, $b) {
        return $a['diff_min'] <=> $b['diff_min'];
    });

    return $results;
}

header('Content-Type: application/json');

$currentTime = "00:13";
$day = "monday";

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
$arr = getBusesSmartMidnight(getPDOConnection(), $currentTime, $day);
echo json_encode([count($arr), $arr]);

?>