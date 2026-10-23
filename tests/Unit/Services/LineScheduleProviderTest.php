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

test('unfiltered navigation includes every direction even when no service is active today', function () {
    $provider = new LineScheduleProvider(NAVIGATION_GTFS_FIXTURE);
    expect($provider->catalog('2027-01-15'))->toBeEmpty();
    expect($provider->catalog(null))->not->toBeEmpty();
    $variants = $provider->variants('1', null);
    expect(array_column($variants, 'trip_id'))->toContain('water_trip', 'water_trip_alt');
    foreach ($variants as $variant) {
        $schedule = $provider->schedule('1', [$variant['trip_id']], null);
        expect($schedule['runs'])->not->toBeEmpty();
        expect($schedule['headsign'])->toBe($variant['headsign']);
        expect($schedule['runs'][0]['calendar'])->toContain('Tutti i giorni', '01/01/2026', '31/12/2026');
    }
});

test('complete timetable labels retain weekdays and calendar exceptions', function () {
    $label = LineScheduleProvider::describeCalendar([
        'monday'=>'1', 'friday'=>'1', 'start_date'=>'20261001', 'end_date'=>'20261231'
    ], [
        ['date'=>'20261025', 'exception_type'=>'1'],
        ['date'=>'20261026', 'exception_type'=>'2'],
    ]);
    expect($label)->toContain('Lun · Ven', 'Anche il 25/10/2026', 'Escluso il 26/10/2026');
    expect($label)->not->toContain('Tutti i giorni');
    expect(LineScheduleProvider::describeCalendar([], [['date'=>'20261025', 'exception_type'=>'1']]))->toBe('Anche il 25/10/2026');
});

test('merged navigation timetable preserves stops belonging to each selected route', function () {
    $provider = new LineScheduleProvider(NAVIGATION_GTFS_FIXTURE);
    $schedule = $provider->schedule('1', ['water_trip', 'water_trip_alt'], null);
    $variants = $provider->variants('1', null);
    foreach ($variants as $variant) {
        foreach ($variant['stops'] as $stop) expect(array_column($schedule['stops'], 'id'))->toContain($stop['id']);
    }
    expect($schedule['runs'])->toHaveCount(2);
    foreach ($schedule['runs'] as $run) expect($run['times'])->toHaveCount(count($schedule['stops']));
});
