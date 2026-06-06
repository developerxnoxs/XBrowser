<?php

declare(strict_types=1);

namespace Xbrowser\Tests\Unit\CDP;

use PHPUnit\Framework\TestCase;
use Xbrowser\CDP\WebSocketClient;
use Xbrowser\Exceptions\BrowserDisconnectException;

class WebSocketClientTest extends TestCase
{
    public function testConnectThrowsWhenServerUnavailable(): void
    {
        $this->expectException(\Xbrowser\Exceptions\TimeoutException::class);

        $client = new WebSocketClient('ws://127.0.0.1:19999/devtools/browser/test');
        $client->connect(500);
    }

    public function testIsConnectedReturnsFalseBeforeConnect(): void
    {
        $client = new WebSocketClient('ws://127.0.0.1:9222/test');
        $this->assertFalse($client->isConnected());
    }

    public function testCloseOnNonConnectedClientDoesNotThrow(): void
    {
        $client = new WebSocketClient('ws://127.0.0.1:9222/test');
        $client->close();
        $this->assertFalse($client->isConnected());
    }

    public function testReceiveReturnsNullWhenNotConnected(): void
    {
        $client = new WebSocketClient('ws://127.0.0.1:9222/test');
        $result = @$client->receive(100);
        $this->assertNull($result);
    }
}
