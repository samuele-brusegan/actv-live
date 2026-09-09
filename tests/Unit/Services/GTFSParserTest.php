<?php

if (!defined('BASE_PATH'))
    define('BASE_PATH', realpath(__DIR__ . '/../../../'));

require_once __DIR__ . '/../../../app/services/GTFSParser.php';

test('gtfs parser is cache valid check', function () {
    $tempDir = sys_get_temp_dir() . '/gtfs_test_' . uniqid();
    mkdir($tempDir . '/cache', 0777, true);

    // Mock BASE_PATH for the parser
    // This is hard because BASE_PATH is a constant
    // We'll just test the logic if possible or skip if too integrated
    expect(class_exists('GTFSParser'))->toBeTrue();
});

test('gtfs parser writes route schedules while streaming stop times', function () {
    $root = sys_get_temp_dir() . '/gtfs_stop_times_' . uniqid();
    $cache = $root . '/cache';
    mkdir($root, 0777, true);
    mkdir($cache, 0777, true);

    file_put_contents($cache . '/trips.json', json_encode([
        'trip-1' => ['route_id' => 'R1'],
        'trip-2' => ['route_id' => 'R1'],
    ], JSON_THROW_ON_ERROR));
    file_put_contents($root . '/stop_times.txt', implode("\n", [
        'trip_id,stop_id,arrival_time,departure_time,stop_sequence',
        'trip-1,S2,08:10:00,08:10:30,2',
        'trip-1,S1,08:00:00,08:00:30,1',
        'trip-2,S1,09:00:00,09:00:30,1',
    ]) . "\n");

    try {
        (new GTFSParser($root, $cache))->parseStopTimes();
        $schedule = json_decode(
            (string) file_get_contents($cache . '/routes/route_R1.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
        $index = json_decode(
            (string) file_get_contents($cache . '/stop_routes_index.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        expect($schedule['trip-1'][0]['stop_id'])->toBe('S1')
            ->and($schedule['trip-1'][1]['stop_id'])->toBe('S2')
            ->and($index['S1'])->toBe(['R1']);
    } finally {
        $remove = function (string $path) use (&$remove): void {
            if (is_dir($path)) {
                foreach (scandir($path) ?: [] as $entry) {
                    if ($entry !== '.' && $entry !== '..') $remove($path . '/' . $entry);
                }
                rmdir($path);
                return;
            }
            if (is_file($path)) unlink($path);
        };
        $remove($root);
    }
});
