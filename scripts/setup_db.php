<?php
require_once __DIR__ . '/../public/index.php'; // This will initialize BASE_PATH, ENV, etc.

if (!class_exists('databaseConnector')) {
    require_once BASE_PATH . '/app/models/databaseConnector.php';
}

$db = new databaseConnector();
$db->connect(ENV['DB_USERNAME'], ENV['DB_PASSWORD'], ENV['DB_HOST'], ENV['DB_NAME']);

// Since databaseConnector::query expects a result set, we'll use the raw mysqli object
$mysqli = Closure::bind(function($db) { return $db->db; }, null, 'databaseConnector')($db);

$queries = [
    "CREATE TABLE IF NOT EXISTS `logs` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `type` ENUM('PHP_ERROR', 'JS_ERROR', 'EXCEPTION') NOT NULL,
      `message` TEXT NOT NULL,
      `file` VARCHAR(255),
      `line` INT,
      `stack_trace` TEXT,
      `context` JSON,
      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    "CREATE TABLE IF NOT EXISTS `tm_sessions` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `name` VARCHAR(255) NOT NULL,
      `start_time` DATETIME NOT NULL,
      `end_time` DATETIME NOT NULL,
      `status` ENUM('SCHEDULED', 'RECORDING', 'COMPLETED') DEFAULT 'SCHEDULED',
      `stops` JSON NOT NULL,
      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    "CREATE TABLE IF NOT EXISTS `tm_data` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `session_id` INT NOT NULL,
      `stop_id` VARCHAR(50) NOT NULL,
      `fetched_at` DATETIME NOT NULL,
      `data_json` LONGTEXT NOT NULL,
      FOREIGN KEY (`session_id`) REFERENCES `tm_sessions`(`id`) ON DELETE CASCADE
    )"
];

foreach ($queries as $sql) {
    if ($mysqli->query($sql)) {
        echo "Table created/verified successfully.\n";
    } else {
        echo "Error: " . $mysqli->error . "\n";
    }
}
?>
