<?php
/**
 * Script to create and populate the `shapes_refined` table.
 * This table is used to store high-fidelity road-snapped paths for bus lines.
 */

// Define BASE_PATH
if (!defined('BASE_PATH')) {
    define('BASE_PATH', __DIR__);
}

require_once BASE_PATH . '/app/bootstrap.php';
require_once BASE_PATH . '/app/models/databaseConnector.php';

echo "Refining shapes...\n";

$db = new databaseConnector();
$db->connect(ENV['DB_USER'], ENV['DB_PASS'], ENV['DB_HOST'], ENV['DB_NAME']);

// Since databaseConnector::query expects a result set, we'll use the raw mysqli object
$mysqli = Closure::bind(function($db) { return $db->db; }, null, 'databaseConnector')($db);

$queries = [
    "CREATE TABLE IF NOT EXISTS `shapes_refined` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `shape_id` VARCHAR(50) NOT NULL,
      `lat` DECIMAL(10, 8) NOT NULL,
      `lng` DECIMAL(11, 8) NOT NULL,
      `sequence` INT NOT NULL,
      `dist_traveled` DOUBLE,
      INDEX `idx_shape_id` (`shape_id`),
      INDEX `idx_sequence` (`sequence`)
    )",
    "TRUNCATE TABLE `shapes_refined`"
];

foreach ($queries as $sql) {
    if ($mysqli->query($sql)) {
        echo "Query executed: " . substr($sql, 0, 50) . "...\n";
    } else {
        echo "Error: " . $mysqli->error . "\n";
        exit(1);
    }
}

// Populate from existing shapes
// We use the existing shapes because they are actually road-snapped in the ACTV GTFS
echo "Copying data from `shapes` to `shapes_refined`...\n";
$insertSql = "INSERT INTO `shapes_refined` (shape_id, lat, lng, sequence, dist_traveled)
              SELECT shape_id, shape_pt_lat, shape_pt_lon, shape_pt_sequence, shape_dist_traveled
              FROM `shapes`
              ORDER BY shape_id, shape_pt_sequence";

if ($mysqli->query($insertSql)) {
    echo "Successfully populated `shapes_refined`!\n";
    $count = $db->query("SELECT COUNT(*) as cnt FROM shapes_refined")[0]['cnt'];
    echo "Total points: $count\n";
} else {
    echo "Error populating table: " . $mysqli->error . "\n";
}

echo "\n✓ Shapes refined!\n";
?>
