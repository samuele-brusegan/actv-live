<?php
/**
 * Rebuild `shapes_refined` from `shapes` without exposing an empty table if
 * rebuilding fails or the process is interrupted.
 */

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

require_once BASE_PATH . '/app/bootstrap.php';

try {
    $pdo = new PDO(
        'mysql:host=' . ENV['DB_HOST'] . ';dbname=' . ENV['DB_NAME'] . ';charset=utf8mb4',
        ENV['DB_USER'],
        ENV['DB_PASS'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $pdo->exec("CREATE TABLE IF NOT EXISTS `shapes_refined` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `shape_id` VARCHAR(50) NOT NULL,
      `lat` DECIMAL(10, 8) NOT NULL,
      `lng` DECIMAL(11, 8) NOT NULL,
      `sequence` INT NOT NULL,
      `dist_traveled` DOUBLE,
      INDEX `idx_shape_id` (`shape_id`),
      INDEX `idx_sequence` (`sequence`)
    )");

    $suffix = date('YmdHis') . '_' . getmypid();
    $staging = 'shapes_refined_stage_' . $suffix;
    $backup = 'shapes_refined_backup_' . $suffix;
    $pdo->exec("CREATE TABLE `$staging` LIKE `shapes_refined`");

    try {
        $pdo->exec("INSERT INTO `$staging` (shape_id, lat, lng, sequence, dist_traveled)
                    SELECT shape_id, shape_pt_lat, shape_pt_lon, shape_pt_sequence, shape_dist_traveled
                    FROM `shapes`
                    ORDER BY shape_id, shape_pt_sequence");
        $count = (int) $pdo->query("SELECT COUNT(*) FROM `$staging`")->fetchColumn();
        if ($count === 0) {
            throw new RuntimeException('La tabella staging è vuota; shapes_refined corrente conservata.');
        }

        $pdo->exec("RENAME TABLE `shapes_refined` TO `$backup`, `$staging` TO `shapes_refined`");
        $pdo->exec("DROP TABLE `$backup`");
        echo "shapes_refined aggiornata: $count punti.\n";
    } catch (Throwable $e) {
        $pdo->exec("DROP TABLE IF EXISTS `$staging`");
        throw $e;
    }
} catch (Throwable $e) {
    fwrite(STDERR, 'Errore aggiornamento shapes_refined: ' . $e->getMessage() . "\n");
    exit(1);
}
