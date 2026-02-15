<?php
require 'app/bootstrap.php';
try {
    $pdo = new PDO('mysql:host='.ENV['DB_HOST'].';dbname='.ENV['DB_NAME'], ENV['DB_USER'], ENV['DB_PASS']);
    $s = $pdo->query('SELECT stop_id, stop_name, data_url FROM stops WHERE data_url IS NOT NULL LIMIT 10');
    $results = $s->fetchAll(PDO::FETCH_ASSOC);
    print_r($results);
} catch (Exception $e) {
    echo $e->getMessage();
}
