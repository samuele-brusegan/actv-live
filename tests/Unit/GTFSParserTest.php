<?php

use PHPUnit\Framework\TestCase;

class GTFSParserTest extends TestCase
{
    private $parser;
    private $testDir;

    protected function setUp(): void
    {
        $this->testDir = BASE_PATH . '/data/gtfs_test';
        if (!file_exists($this->testDir)) {
            mkdir($this->testDir, 0777, true);
        }
        
        $this->parser = new GTFSParser();
        
        // Use reflection to set private properties for testing if needed
        // or ensure constructor uses BASE_PATH correctly.
    }

    public function testIsCacheValidReturnsFalseIfNoCache()
    {
        $this->assertFalse($this->parser->isCacheValid());
    }

    public function testConstructorCreatesCacheDir()
    {
        $cacheDir = BASE_PATH . '/data/gtfs/cache';
        $this->assertDirectoryExists($cacheDir);
    }
}
