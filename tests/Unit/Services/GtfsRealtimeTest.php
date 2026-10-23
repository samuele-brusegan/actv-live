<?php

if (!defined('BASE_PATH')) define('BASE_PATH', realpath(__DIR__ . '/../../../'));

require_once BASE_PATH . '/app/services/GtfsRealtime.php';

function protobufVarint(int $value): string
{
    $bytes = '';
    do {
        $byte = $value & 0x7f;
        $value >>= 7;
        $bytes .= chr($value ? $byte | 0x80 : $byte);
    } while ($value);
    return $bytes;
}

function protobufField(int $number, int $wire, string $value): string
{
    $field = protobufVarint(($number << 3) | $wire);
    if ($wire === 2) return $field . protobufVarint(strlen($value)) . $value;
    return $field . $value;
}

test('gtfs realtime decodes trip updates stop payloads without type errors', function () {
    $timeEvent = protobufField(1, 0, protobufVarint(120));
    $arrival = protobufField(2, 2, $timeEvent);
    $stopUpdate = protobufField(4, 2, $arrival);
    $trip = protobufField(1, 2, 'trip-test') . protobufField(5, 2, 'route-test');
    $tripUpdate = protobufField(1, 2, $trip) . $stopUpdate;
    $entity = protobufField(3, 2, $tripUpdate);
    $feed = protobufField(2, 2, $entity);

    $decoded = GtfsRealtime::decode('updates', $feed, 'automobilistico');

    expect($decoded)->toHaveCount(1)
        ->and($decoded[0]['trip_id'])->toBe('trip-test')
        ->and($decoded[0]['route_id'])->toBe('route-test')
        ->and($decoded[0]['delay_seconds'])->toBe(120)
        ->and($decoded[0]['status'])->toBe('delayed');
});

test('vehicle positions preserve physical identity, observation time and stop sequence', function () {
    $descriptor = protobufField(1, 2, 'bus-42');
    $trip = protobufField(1, 2, 'trip-test') . protobufField(5, 2, 'route-test');
    $position = protobufField(1, 5, pack('g', 45.4)) . protobufField(2, 5, pack('g', 12.3));
    $vehicle = protobufField(1, 2, $trip) . protobufField(2, 2, $position)
        . protobufField(3, 0, protobufVarint(12)) . protobufField(4, 0, protobufVarint(2))
        . protobufField(5, 0, protobufVarint(1000)) . protobufField(8, 2, $descriptor);
    $header = protobufField(3, 0, protobufVarint(1010));
    $feed = protobufField(1, 2, $header) . protobufField(2, 2, protobufField(1, 2, 'entity-42') . protobufField(4, 2, $vehicle));
    $decoded = GtfsRealtime::decode('vehicles', $feed, 'automobilistico');
    expect($decoded)->toHaveCount(1)
        ->and($decoded[0]['vehicle_id'])->toBe('bus-42')
        ->and($decoded[0]['entity_id'])->toBe('entity-42')
        ->and($decoded[0]['position_timestamp'])->toBe(1000)
        ->and($decoded[0]['current_stop_sequence'])->toBe(12)
        ->and($decoded[0]['current_status'])->toBe(2);
});

test('vehicle observation time falls back to the feed header', function () {
    $header = protobufField(3, 0, protobufVarint(1000));
    $vehicle = protobufField(1, 2, protobufField(1, 2, 'trip-test'));
    $feed = protobufField(1, 2, $header) . protobufField(2, 2, protobufField(4, 2, $vehicle));
    expect(GtfsRealtime::decode('vehicles', $feed)[0]['position_timestamp'])->toBe(1000);
});

test('old or implausibly future positions are excluded without inventing observation times', function () {
    $vehicles = [
        ['vehicle_id'=>'fresh', 'position_timestamp'=>1000],
        ['vehicle_id'=>'old', 'position_timestamp'=>879],
        ['vehicle_id'=>'future', 'position_timestamp'=>1061],
        ['vehicle_id'=>'unknown', 'position_timestamp'=>null],
    ];
    expect(array_column(GtfsRealtime::freshVehicles($vehicles, 1000), 'vehicle_id'))->toBe(['fresh', 'unknown']);
});
