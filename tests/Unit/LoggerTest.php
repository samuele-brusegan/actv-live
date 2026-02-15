<?php

use PHPUnit\Framework\TestCase;

class LoggerTest extends TestCase
{
    protected function setUp(): void
    {
        // Setup any necessary state
    }

    public function testLogMethodExists()
    {
        $this->assertTrue(method_exists('Logger', 'log'));
    }

    public function testPhpErrorHandlerMethodExists()
    {
        $this->assertTrue(method_exists('Logger', 'phpErrorHandler'));
    }

    public function testExceptionHandlerMethodExists()
    {
        $this->assertTrue(method_exists('Logger', 'exceptionHandler'));
    }
}
