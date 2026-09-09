<?php

define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/app/services/ConnectionCacheBuilder.php';

$dates = array_values(array_filter(array_slice($argv ?? [], 1), fn($value) => !str_starts_with($value, '--')));
if (!$dates) {
    $today = new DateTimeImmutable('today');
    $dates = [$today->format('Y-m-d'), $today->modify('+1 day')->format('Y-m-d')];
}

try {
    $builder = new ConnectionCacheBuilder();
    foreach ($dates as $date) {
        $started = microtime(true);
        $path = $builder->ensure($date);
        echo $date . ': ' . $path . ' (' . round(microtime(true) - $started, 2) . "s)\n";
    }
} catch (Throwable $error) {
    fwrite(STDERR, 'Errore cache planner: ' . $error->getMessage() . "\n");
    exit(1);
}
