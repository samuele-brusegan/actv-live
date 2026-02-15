<?php
require_once __DIR__ . '/app/bootstrap.php';
require_once BASE_PATH . '/app/models/databaseConnector.php';

$db = new databaseConnector();
$db->connect(ENV['DB_USER'], ENV['DB_PASS'], ENV['DB_HOST'], ENV['DB_NAME']);

// Get some shape_ids
$shapes = $db->query("SELECT DISTINCT shape_id FROM shapes LIMIT 5");

foreach ($shapes as $s) {
    $shapeId = $s['shape_id'];
    $points = $db->query("SELECT * FROM shapes WHERE shape_id = '$shapeId' ORDER BY shape_pt_sequence LIMIT 10");
    echo "Shape ID: $shapeId\n";
    foreach ($points as $p) {
        echo "  - Seq: {$p['shape_pt_sequence']}, Lat: {$p['shape_pt_lat']}, Lon: {$p['shape_pt_lon']}, Dist: {$p['shape_dist_traveled']}\n";
    }
    
    // Check total points for this shape
    $count = $db->query("SELECT COUNT(*) as cnt FROM shapes WHERE shape_id = '$shapeId'")[0]['cnt'];
    echo "  Total points: $count\n\n";
}
?>
