<?php

if (!defined('BASE_PATH')) define('BASE_PATH', realpath(__DIR__ . '/../../../'));
require_once BASE_PATH . '/app/services/ConnectionScanPlanner.php';

const GTFS_FIXTURE_ROOT = BASE_PATH . '/tests/fixtures';

test('connection scan finds the multimodal Mestre to Pellestrina journey', function () {
    $routes = (new ConnectionScanPlanner(GTFS_FIXTURE_ROOT))->plan('162', '4609', '2026-09-02', '16:50:00');

    expect($routes)->not->toBeEmpty();
    expect($routes[0]['service'])->toBe('mixed');
    expect(array_column($routes[0]['legs'], 'mode'))->toContain('bus', 'water');

    $previousArrival = null;
    foreach ($routes[0]['legs'] as $leg) {
        if ($previousArrival !== null) expect($leg['departure_time'])->toBeGreaterThanOrEqual($previousArrival);
        expect($leg['arrival_time'])->toBeGreaterThanOrEqual($leg['departure_time']);
        $previousArrival = $leg['arrival_time'];
    }
});

test('connection scan honors a date without active services', function () {
    $routes = (new ConnectionScanPlanner(GTFS_FIXTURE_ROOT))->plan('162', '4609', '2027-01-15', '16:50:00');
    expect($routes)->toBeEmpty();
});

test('connection cache accepts GTFS times beyond midnight', function () {
    expect(ConnectionCacheBuilder::timeToSeconds('24:15:00'))->toBe(87300);
    expect(ConnectionCacheBuilder::timeToSeconds('not-a-time'))->toBeNull();
});
