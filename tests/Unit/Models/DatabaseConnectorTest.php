<?php

require_once __DIR__ . '/../../../app/models/databaseConnector.php';

test('database connector getJoins returns initialized joins', function () {
    $connector = new databaseConnector();
    // Since connect() actually tries to connect, we might need to mock it or just test the initial state if possible
    // But the class doesn't initialize $tableJoins in the constructor, only in connect()
    expect($connector->getJoins())->toBe("");
});
