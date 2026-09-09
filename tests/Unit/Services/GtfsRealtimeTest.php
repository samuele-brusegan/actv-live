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
