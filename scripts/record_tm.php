<?php
/**
 * Time Machine Recording Script
 * Should be run regularly (e.g. via cron every minute)
 */

require_once __DIR__ . '/../public/index.php';

if (!class_exists('databaseConnector')) {
    require_once BASE_PATH . '/app/models/databaseConnector.php';
}

$db = new databaseConnector();
$db->connect(ENV['DB_USERNAME'], ENV['DB_PASSWORD'], ENV['DB_HOST'], ENV['DB_NAME']);
$mysqli = Closure::bind(function($db) { return $db->db; }, null, 'databaseConnector')($db);

// 1. Update session statuses
$now = date('Y-m-d H:i:s');
$mysqli->query("UPDATE tm_sessions SET status = 'RECORDING' WHERE status = 'SCHEDULED' AND start_time <= '$now' AND end_time > '$now'");
$mysqli->query("UPDATE tm_sessions SET status = 'COMPLETED' WHERE status = 'RECORDING' AND end_time <= '$now'");

// 2. Fetch data for active sessions
$sessions = $db->query("SELECT * FROM tm_sessions WHERE status = 'RECORDING'");

foreach ($sessions as $s) {
    $stops = json_decode($s['stops'], true);
    if (!$stops) continue;

    foreach ($stops as $stopId) {
        $url = "https://oraritemporeale.actv.it/aut/backend/passages/{$stopId}-web-aut";
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        curl_close($ch);

        if ($response) {
            $stmt = $mysqli->prepare("INSERT INTO tm_data (session_id, stop_id, fetched_at, data_json) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("isss", $s['id'], $stopId, $now, $response);
            $stmt->execute();
            $stmt->close();
            echo "Recorded stop $stopId for session {$s['id']}\n";
        }
    }
}
?>
