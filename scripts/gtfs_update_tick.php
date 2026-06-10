<?php

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once BASE_PATH . '/app/services/GtfsUpdateManager.php';

$started = (new GtfsUpdateManager())->runScheduledIfDue();
echo $started ? "GTFS update started.\n" : "No GTFS update due.\n";
