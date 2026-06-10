<?php

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once BASE_PATH . '/app/services/GtfsUpdateManager.php';

try {
    $result = (new GtfsUpdateManager())->syncCron();
    if ($result['enabled']) {
        echo "GTFS cron installed: {$result['expression']}\n";
    } else {
        echo "GTFS cron disabled.\n";
    }
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "GTFS cron error: {$e->getMessage()}\n");
    exit(1);
}
