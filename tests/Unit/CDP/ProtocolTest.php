<?php

declare(strict_types=1);

namespace Xbrowser\Tests\Unit\CDP;

use PHPUnit\Framework\TestCase;
use Xbrowser\CDP\Protocol;

class ProtocolTest extends TestCase
{
    public function testNavigateCommand(): void
    {
        $cmd = Protocol::navigate('https://example.com');
        $this->assertSame('Page.navigate', $cmd['method']);
        $this->assertSame('https://example.com', $cmd['params']['url']);
    }

    public function testEnableCommand(): void
    {
        $cmd = Protocol::enable('Network');
        $this->assertSame('Network.enable', $cmd['method']);
        $this->assertSame([], $cmd['params']);
    }

    public function testGetDocumentCommand(): void
    {
        $cmd = Protocol::getDocument();
        $this->assertSame('DOM.getDocument', $cmd['method']);
        $this->assertSame(-1, $cmd['params']['depth']);
    }

    public function testQuerySelectorCommand(): void
    {
        $cmd = Protocol::querySelector(1, '#login');
        $this->assertSame('DOM.querySelector', $cmd['method']);
        $this->assertSame(1, $cmd['params']['nodeId']);
        $this->assertSame('#login', $cmd['params']['selector']);
    }

    public function testEvaluateCommand(): void
    {
        $cmd = Protocol::evaluate('document.title');
        $this->assertSame('Runtime.evaluate', $cmd['method']);
        $this->assertSame('document.title', $cmd['params']['expression']);
        $this->assertTrue($cmd['params']['returnByValue']);
    }

    public function testCaptureScreenshotCommand(): void
    {
        $cmd = Protocol::captureScreenshot('jpeg', 85);
        $this->assertSame('Page.captureScreenshot', $cmd['method']);
        $this->assertSame('jpeg', $cmd['params']['format']);
        $this->assertSame(85, $cmd['params']['quality']);
    }

    public function testDispatchMouseEventCommand(): void
    {
        $cmd = Protocol::dispatchMouseEvent('mousePressed', 100.0, 200.0, 0, 1);
        $this->assertSame('Input.dispatchMouseEvent', $cmd['method']);
        $this->assertSame('mousePressed', $cmd['params']['type']);
        $this->assertSame(100.0, $cmd['params']['x']);
        $this->assertSame('left', $cmd['params']['button']);
    }

    public function testInsertTextCommand(): void
    {
        $cmd = Protocol::insertText('Hello World');
        $this->assertSame('Input.insertText', $cmd['method']);
        $this->assertSame('Hello World', $cmd['params']['text']);
    }

    public function testSetViewportCommand(): void
    {
        $cmd = Protocol::setViewport(1280, 720);
        $this->assertSame('Emulation.setDeviceMetricsOverride', $cmd['method']);
        $this->assertSame(1280, $cmd['params']['width']);
        $this->assertSame(720, $cmd['params']['height']);
    }

    public function testGetCookiesCommand(): void
    {
        $cmd = Protocol::getAllCookies();
        $this->assertSame('Network.getAllCookies', $cmd['method']);
    }

    public function testSetCookiesCommand(): void
    {
        $cookies = [['name' => 'token', 'value' => 'abc', 'domain' => 'example.com']];
        $cmd     = Protocol::setCookies($cookies);
        $this->assertSame('Network.setCookies', $cmd['method']);
        $this->assertSame($cookies, $cmd['params']['cookies']);
    }

    public function testCreateTargetCommand(): void
    {
        $cmd = Protocol::createTarget('https://example.com');
        $this->assertSame('Target.createTarget', $cmd['method']);
        $this->assertSame('https://example.com', $cmd['params']['url']);
    }

    public function testReloadCommand(): void
    {
        $cmd = Protocol::reload(true);
        $this->assertSame('Page.reload', $cmd['method']);
        $this->assertTrue($cmd['params']['ignoreCache']);
    }
}
