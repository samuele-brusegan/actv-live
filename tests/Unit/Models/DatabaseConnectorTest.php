<?php

require_once __DIR__ . '/../../../app/models/databaseConnector.php';

test('database connector seamsValidSQL returns false for SQL keywords', function () {
    $connector = new databaseConnector();

    expect($connector->seamsValidSQL('SELECT * FROM users'))->toBeFalse();
    expect($connector->seamsValidSQL('UPDATE users SET name = "test"'))->toBeFalse();
    expect($connector->seamsValidSQL('DELETE FROM users'))->toBeFalse();
    expect($connector->seamsValidSQL('INSERT INTO users (name) VALUES ("test")'))->toBeFalse();
});

test('database connector seamsValidSQL returns true for other strings', function () {
    $connector = new databaseConnector();

    expect($connector->seamsValidSQL('Some random string'))->toBeTrue();
    expect($connector->seamsValidSQL('DROP TABLE users'))->toBeTrue(); // Current implementation only checks for SELECT, UPDATE, DELETE, INSERT
});

test('database connector getJoins returns initialized joins', function () {
    $connector = new databaseConnector();
    // Since connect() actually tries to connect, we might need to mock it or just test the initial state if possible
    // But the class doesn't initialize $tableJoins in the constructor, only in connect()
    expect($connector->getJoins())->toBe("");
});
