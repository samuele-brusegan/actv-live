<?php

require_once __DIR__ . '/../../../app/models/databaseConnector.php';

test('database connector seamsValidSQL returns true for SQL keywords', function () {
    $connector = new databaseConnector();

    expect($connector->seamsValidSQL('SELECT * FROM users'))->toBeTrue();
    expect($connector->seamsValidSQL('UPDATE users SET name = "test"'))->toBeTrue();
    expect($connector->seamsValidSQL('DELETE FROM users'))->toBeTrue();
    expect($connector->seamsValidSQL('INSERT INTO users (name) VALUES ("test")'))->toBeTrue();
});

test('database connector seamsValidSQL returns false for other strings', function () {
    $connector = new databaseConnector();

    expect($connector->seamsValidSQL('Some random string'))->toBeFalse();
    expect($connector->seamsValidSQL('DROP TABLE users'))->toBeFalse(); // Current implementation only checks for SELECT, UPDATE, DELETE, INSERT
});

test('database connector getJoins returns initialized joins', function () {
    $connector = new databaseConnector();
    // Since connect() actually tries to connect, we might need to mock it or just test the initial state if possible
    // But the class doesn't initialize $tableJoins in the constructor, only in connect()
    expect($connector->getJoins())->toBe("");
});
