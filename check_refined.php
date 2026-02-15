<?php
require_once __DIR__ . '/app/bootstrap.php';
require_once BASE_PATH . '/app/models/databaseConnector.php';

$db = new databaseConnector();
$db->connect(ENV['DB_USER'], ENV['DB_PASS'], ENV['DB_HOST'], ENV['DB_NAME']);

$mysqli = Closure::bind(function($db) { return $db->db; }, null, 'databaseConnector')($db);

$res = $mysqli->query("SHOW TABLES LIKE 'shapes_refined'");
if ($res->num_rows > 0) {
    echo "Table 'shapes_refined' exists.\n";
    $count = $db->query("SELECT COUNT(*) as cnt FROM shapes_refined")[0]['cnt'];
    echo "Total points in shapes_refined: $count\n";
} else {
    echo "Table 'shapes_refined' does not exist.\n";
}
?>
