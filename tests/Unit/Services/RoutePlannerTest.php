<?php

if (!defined('BASE_PATH'))
    define('BASE_PATH', realpath(__DIR__ . '/../../../'));

require_once __DIR__ . '/../../../app/services/RoutePlanner.php';

test('route planner calculates duration correctly', function () {
    $planner = new RoutePlanner();

    // Using reflection to test private methods if needed, or just public ones
    $reflection = new ReflectionClass($planner);
    $method = $reflection->getMethod('calculateDuration');
    $method->setAccessible(true);

    expect($method->invoke($planner, '10:00:00', '10:30:00'))->toBe(30.0);
    expect($method->invoke($planner, '23:50:00', '00:10:00'))->toBe(-1420.0); // GTFS 24h+ handling might be needed
});

test('route planner converts gtfs to seconds', function () {
    $planner = new RoutePlanner();
    $reflection = new ReflectionClass($planner);
    $method = $reflection->getMethod('gtfsToSeconds');
    $method->setAccessible(true);

    expect($method->invoke($planner, '01:00:00'))->toBe(3600);
    expect($method->invoke($planner, '00:01:30'))->toBe(90);
});

test('route planner adds minutes correctly', function () {
    $planner = new RoutePlanner();
    $reflection = new ReflectionClass($planner);
    $method = $reflection->getMethod('addMinutes');
    $method->setAccessible(true);

    expect($method->invoke($planner, '10:00:00', 5))->toBe('10:05:00');
    expect($method->invoke($planner, '10:55:00', 10))->toBe('11:05:00');
});

test('route planner calculates geo distance', function () {
    $planner = new RoutePlanner();
    $reflection = new ReflectionClass($planner);
    $method = $reflection->getMethod('calculateGeoDistance');
    $method->setAccessible(true);

    // San Marco to Piazzale Roma (approx 1.5km)
    $dist = $method->invoke($planner, 45.4337, 12.3381, 45.4411, 12.3185);
    expect($dist)->toBeGreaterThan(1500);
    expect($dist)->toBeLessThan(2000);
});
