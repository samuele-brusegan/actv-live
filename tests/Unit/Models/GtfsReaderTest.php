<?php

require_once __DIR__ . '/../../../app/models/gtfsReader.php';

test('readGTFS returns false for non-existent file', function () {
    $result = readGTFS('non_existent.txt', 'SELECT * FROM test');
    expect($result)->toBeFalse();
});

test('readGTFS parses CSV and executes query', function () {
    $tempFile = tempnam(sys_get_temp_dir(), 'gtfs');
    $csvContent = "stop_id,stop_name,stop_lat,stop_lon\n1,San Marco,45.4337,12.3381\n2,Piazzale Roma,45.4411,12.3185\n";
    file_put_contents($tempFile, $csvContent);

    $result = readGTFS($tempFile, 'SELECT stop_name FROM ' . pathinfo($tempFile, PATHINFO_FILENAME) . ' WHERE stop_id = "1"');

    expect($result)->toBeArray();
    expect($result)->toHaveCount(1);
    expect($result[0]['stop_name'])->toBe('San Marco');

    unlink($tempFile);
});

test('readGTFS handles empty CSV (only header)', function () {
    $tempFile = tempnam(sys_get_temp_dir(), 'gtfs');
    $csvContent = "stop_id,stop_name\n";
    file_put_contents($tempFile, $csvContent);

    $result = readGTFS($tempFile, 'SELECT * FROM ' . pathinfo($tempFile, PATHINFO_FILENAME));

    expect($result)->toBeArray();
    expect($result)->toHaveCount(0);

    unlink($tempFile);
});
