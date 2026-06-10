<?php

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once BASE_PATH . '/app/services/GtfsUpdateManager.php';

$trigger = 'manual';
foreach ($argv ?? [] as $argument) {
    if (str_starts_with($argument, '--trigger=')) {
        $trigger = substr($argument, strlen('--trigger='));
    }
}

exit((new GtfsUpdateManager())->run($trigger));
