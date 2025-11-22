<?php
/**
 * Script to parse GTFS data
 * Run this script to download and parse ACTV GTFS feed
 * 
 * Usage: php parse_gtfs.php
 */

// Define BASE_PATH
define('BASE_PATH', __DIR__);

// Load GTFSParser
require_once BASE_PATH . '/app/services/GTFSParser.php';

try {
    $parser = new GTFSParser();
    
    // Check if cache is valid
    if ($parser->isCacheValid()) {
        echo "GTFS cache is still valid. Use --force to re-download.\n";
        
        if (!isset($argv[1]) || $argv[1] !== '--force') {
            exit(0);
        }
    }
    
    echo "Starting GTFS parsing...\n\n";
    $parser->parseAll();
    
    echo "\n✓ GTFS data successfully parsed and cached!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
