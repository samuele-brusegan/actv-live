<?php

use PHPUnit\Framework\TestCase;

class GtfsIdentifyTest extends TestCase
{
    private $pdo;

    protected function setUp(): void
    {
        if (!defined('BASE_PATH')) {
            define('BASE_PATH', realpath(__DIR__ . '/../..'));
        }
        
        // Ensure constants like ENV are loaded from the real app
        require_once BASE_PATH . '/app/bootstrap.php';
        
        // If bootstrap didn't load ENV (unlikely), fallback
        $host = ENV['DB_HOST'] ?? 'localhost';
        $db   = ENV['DB_NAME'] ?? 'actv-live';
        $user = ENV['DB_USER'] ?? 'root';
        $pass = ENV['DB_PASS'] ?? '';

        $this->pdo = new PDO(
            "mysql:host=$host;dbname=$db;charset=utf8mb4",
            $user,
            $pass,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );

        require_once BASE_PATH . '/app/views/gtfsIdentify.php';
    }

    public function testDbQueryWithStopName()
    {
        // Test legacy matching by name
        // Use realistic parameters (Line 80 goes through Rizzardi Carrer towards Mestre Centro)
        $trips = dbquery($this->pdo, '09:00', '80', 'Mestre Centro', 'monday', '1', 'Rizzardi Carrer');
        $this->assertIsArray($trips);
    }

    public function testDbQueryWithStopId()
    {
        // Test new matching by data_url (stopId)
        // Rizzardi Carrer has data_url '334-web-aut'
        $trips = dbquery($this->pdo, '09:00', '80', 'Mestre Centro', 'monday', '1', 'Rizzardi Carrer', '334');
        $this->assertIsArray($trips);
        $this->assertNotEmpty($trips, "Failed to find trips for data_url '334'");
        
        foreach ($trips as $trip) {
            // Check if matches the line
            $this->assertEquals('80', $trip['route_short_name']);
        }
    }

    public function testQueryBuilderLogic()
    {
        $sqlWithName = queryBuilder('08:00', '5E', 'Noale', 'monday', '123', 'Mestre Centro');
        $this->assertStringContainsString('s.stop_name LIKE', $sqlWithName);

        $sqlWithId = queryBuilder('08:00', '5E', 'Noale', 'monday', '123', 'Mestre Centro', '337-web-aut');
        $this->assertStringContainsString('s.data_url LIKE', $sqlWithId);
        $this->assertStringNotContainsString('s.stop_name LIKE', $sqlWithId);
    }
}
