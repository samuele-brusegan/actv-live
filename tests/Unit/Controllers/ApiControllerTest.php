<?php

if (!defined('BASE_PATH'))
    define('BASE_PATH', realpath(__DIR__ . '/../../../'));
if (!defined('ENV'))
    define('ENV', [
        'DB_USER' => 'test_user',
        'DB_PASS' => 'test_pass',
        'DB_HOST' => 'localhost',
        'DB_NAME' => 'test_db'
    ]);

require_once __DIR__ . '/../../../app/controllers/ApiController.php';

test('api controller returns 400 for missing tripId in busPosition', function () {
    $controller = new ApiController();

    $_GET = []; // Clear GET params

    ob_start();
    $controller->busPosition();
    $output = ob_get_clean();

    $response = json_decode($output, true);
    expect($response)->toHaveKey('error', 'Missing tripId parameter');
});

test('api controller returns 400 for missing line in tripStops', function () {
    $controller = new ApiController();

    $_GET = [];

    ob_start();
    $controller->tripStops();
    $output = ob_get_clean();

    $response = json_decode($output, true);
    expect($response)->toHaveKey('error', 'Missing line parameter');
});
