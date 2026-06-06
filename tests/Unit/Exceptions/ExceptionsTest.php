<?php

declare(strict_types=1);

namespace Xbrowser\Tests\Unit\Exceptions;

use PHPUnit\Framework\TestCase;
use Xbrowser\Exceptions\BrowserCrashException;
use Xbrowser\Exceptions\BrowserDisconnectException;
use Xbrowser\Exceptions\InvalidUrlException;
use Xbrowser\Exceptions\JavaScriptException;
use Xbrowser\Exceptions\PluginException;
use Xbrowser\Exceptions\SelectorNotFoundException;
use Xbrowser\Exceptions\TimeoutException;
use Xbrowser\Exceptions\XbrowserException;

class ExceptionsTest extends TestCase
{
    public function testXbrowserExceptionIsRuntimeException(): void
    {
        $e = new XbrowserException('base');
        $this->assertInstanceOf(\RuntimeException::class, $e);
    }

    public function testTimeoutExceptionMessage(): void
    {
        $e = new TimeoutException('page load', 5000);
        $this->assertStringContainsString('page load', $e->getMessage());
        $this->assertStringContainsString('5000', $e->getMessage());
    }

    public function testSelectorNotFoundExceptionMessage(): void
    {
        $e = new SelectorNotFoundException('#login-button');
        $this->assertStringContainsString('#login-button', $e->getMessage());
    }

    public function testBrowserCrashExceptionMessage(): void
    {
        $e = new BrowserCrashException('OOM');
        $this->assertStringContainsString('OOM', $e->getMessage());
        $this->assertStringContainsString('crashed', $e->getMessage());
    }

    public function testBrowserDisconnectExceptionMessage(): void
    {
        $e = new BrowserDisconnectException('pipe closed');
        $this->assertStringContainsString('pipe closed', $e->getMessage());
        $this->assertStringContainsString('disconnected', $e->getMessage());
    }

    public function testJavaScriptExceptionMessage(): void
    {
        $e = new JavaScriptException('Eval failed', 'TypeError: x is not a function');
        $this->assertStringContainsString('TypeError', $e->getMessage());
    }

    public function testInvalidUrlExceptionMessage(): void
    {
        $e = new InvalidUrlException('not-a-url');
        $this->assertStringContainsString('not-a-url', $e->getMessage());
    }

    public function testPluginExceptionMessage(): void
    {
        $e = new PluginException('my-plugin', 'Missing dependency');
        $this->assertStringContainsString('my-plugin', $e->getMessage());
        $this->assertStringContainsString('Missing dependency', $e->getMessage());
    }

    public function testAllExceptionsExtendXbrowserException(): void
    {
        $exceptions = [
            new TimeoutException(),
            new SelectorNotFoundException('#foo'),
            new BrowserCrashException(),
            new BrowserDisconnectException(),
            new JavaScriptException('x'),
            new InvalidUrlException('y'),
            new PluginException('p', 'r'),
        ];

        foreach ($exceptions as $e) {
            $this->assertInstanceOf(XbrowserException::class, $e, get_class($e));
        }
    }

    public function testExceptionChaining(): void
    {
        $cause    = new \RuntimeException('Root cause');
        $timeout  = new TimeoutException('navigation', 10000, $cause);
        $this->assertSame($cause, $timeout->getPrevious());
    }
}
