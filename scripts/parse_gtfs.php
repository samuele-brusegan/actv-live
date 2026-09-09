<?php
/**
 * Script to parse GTFS data
 * Run this script to download and parse ACTV GTFS feed
 * 
 * Usage: php parse_gtfs.php
 */

// Define BASE_PATH
define('BASE_PATH', dirname(__DIR__));

// Load GTFSParser
require_once BASE_PATH . '/app/services/GTFSParser.php';
require_once BASE_PATH . '/app/services/ConnectionCacheBuilder.php';

try {
    $profile = in_array('--navigation', $argv ?? [], true) ? 'navigation' : 'automobilistico';
    $parser = new GTFSParser(null, null, $profile);
    
    // Check if cache is valid
    if ($parser->isCacheValid()) {
        echo "GTFS cache is still valid. Use --force to re-download.\n";
        
        if (!in_array('--force', $argv ?? [], true)) {
            exit(0);
        }
    }
    
    echo "Starting GTFS parsing...\n\n";
    $parser->parseAll();
    $plannerCache = new ConnectionCacheBuilder();
    $today = new DateTimeImmutable('today');
    $plannerCache->ensure($today->format('Y-m-d'));
    $plannerCache->ensure($today->modify('+1 day')->format('Y-m-d'));
    
    echo "\n✓ GTFS data successfully parsed and cached!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
