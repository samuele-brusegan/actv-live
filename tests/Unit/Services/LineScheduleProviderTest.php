<?php

if (!defined('BASE_PATH')) define('BASE_PATH', realpath(__DIR__ . '/../../../'));
require_once BASE_PATH . '/app/services/LineScheduleProvider.php';

const NAVIGATION_GTFS_FIXTURE = BASE_PATH . '/tests/fixtures/gtfs/navigation';

test('navigation catalog and line one stay isolated in the navigation feed', function () {
    $provider = new LineScheduleProvider(NAVIGATION_GTFS_FIXTURE);
    $catalog = $provider->catalog('2026-09-02');
    $line = array_values(array_filter($catalog, fn ($item) => $item['line'] === '1'))[0] ?? null;
    expect($line)->not->toBeNull();
    expect($line['variants_count'])->toBeGreaterThan(1);
    expect($provider->variants('1', '2026-09-02'))->not->toBeEmpty();
});

test('navigation schedule returns active runs and ordered stops', function () {
    $provider = new LineScheduleProvider(NAVIGATION_GTFS_FIXTURE);
    $variants = $provider->variants('1', '2026-09-02');
    $schedule = $provider->schedule('1', [$variants[0]['trip_id']], '2026-09-02');
    expect($schedule['runs'])->not->toBeEmpty();
    expect($schedule['stops'])->not->toBeEmpty();
    expect($schedule['runs'][0]['times'][0])->not->toBeNull();
});

test('navigation calendar rejects a date outside the feed', function () {
    $provider = new LineScheduleProvider(NAVIGATION_GTFS_FIXTURE);
    expect($provider->catalog('2027-01-15'))->toBeEmpty();
});
