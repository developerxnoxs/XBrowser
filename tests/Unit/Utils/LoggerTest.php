<?php

declare(strict_types=1);

namespace Xbrowser\Tests\Unit\Utils;

use PHPUnit\Framework\TestCase;
use Xbrowser\Utils\Logger;

class LoggerTest extends TestCase
{
    private Logger $logger;

    protected function setUp(): void
    {
        $this->logger = new Logger(verbose: false);
    }

    public function testInfoAddsToHistory(): void
    {
        $this->logger->info('Test info message');
        $history = $this->logger->getHistory();
        $this->assertNotEmpty($history);
        $last = end($history);
        $this->assertSame('INFO', $last['level']);
        $this->assertSame('Test info message', $last['message']);
    }

    public function testDebugNotStoredWhenNotVerbose(): void
    {
        $this->logger->debug('Debug message');
        $history = $this->logger->getHistory();
        $this->assertEmpty($history);
    }

    public function testDebugStoredWhenVerbose(): void
    {
        $logger = new Logger(verbose: true);
        $logger->debug('Verbose debug');
        $history = $logger->getHistory();
        $this->assertNotEmpty($history);
        $this->assertSame('DEBUG', $history[0]['level']);
    }

    public function testErrorAddsToHistory(): void
    {
        $this->logger->error('Something went wrong');
        $history = $this->logger->getHistory();
        $last    = end($history);
        $this->assertSame('ERROR', $last['level']);
        $this->assertSame('Something went wrong', $last['message']);
    }

    public function testWarnAddsToHistory(): void
    {
        $this->logger->warn('A warning');
        $history = $this->logger->getHistory();
        $last    = end($history);
        $this->assertSame('WARN', $last['level']);
    }

    public function testHistoryHasTimestamp(): void
    {
        $this->logger->info('Timestamped message');
        $history = $this->logger->getHistory();
        $last    = end($history);
        $this->assertIsInt($last['timestamp']);
        $this->assertGreaterThan(0, $last['timestamp']);
    }

    public function testMultipleMessagesAreStored(): void
    {
        $this->logger->info('First');
        $this->logger->info('Second');
        $this->logger->warn('Third');
        $this->assertCount(3, $this->logger->getHistory());
    }

    public function testSetVerbose(): void
    {
        $this->logger->setVerbose(true);
        $this->logger->debug('Now visible');
        $this->assertNotEmpty($this->logger->getHistory());
    }

    public function testSilentModeDoesNotAffectHistory(): void
    {
        $this->logger->setSilent(true);
        $this->logger->info('Silent info');
        $this->assertNotEmpty($this->logger->getHistory());
    }

    public function testLogFileWritten(): void
    {
        $file   = sys_get_temp_dir() . '/xbrowser_test_' . uniqid() . '.log';
        $logger = new Logger(logFile: $file);
        $logger->info('File log test');

        $this->assertFileExists($file);
        $this->assertStringContainsString('File log test', file_get_contents($file));

        unlink($file);
    }
}
