<?php

declare(strict_types=1);

namespace Xbrowser\CDP;

use Xbrowser\Exceptions\BrowserDisconnectException;
use Xbrowser\Exceptions\TimeoutException;

class WebSocketClient
{
    private mixed $socket = null;
    private bool $connected = false;
    private string $host;
    private int $port;
    private string $path;
    private string $buffer = '';
    private array $messageQueue = [];

    private const OPCODE_TEXT   = 0x1;
    private const OPCODE_CLOSE  = 0x8;
    private const OPCODE_PING   = 0x9;
    private const OPCODE_PONG   = 0xA;
    private const GUID          = '258EAFA5-E914-47DA-95CA-C5AB0DC85B11';

    public function __construct(string $url)
    {
        $parsed      = parse_url($url);
        $this->host  = $parsed['host'] ?? 'localhost';
        $this->port  = $parsed['port'] ?? 9222;
        $this->path  = ($parsed['path'] ?? '/') . (isset($parsed['query']) ? '?' . $parsed['query'] : '');
    }

    public function connect(int $timeoutMs = 10000): void
    {
        $deadline = microtime(true) + $timeoutMs / 1000;
        $errno    = 0;
        $errstr   = '';

        while (true) {
            $this->socket = @stream_socket_client(
                "tcp://{$this->host}:{$this->port}",
                $errno,
                $errstr,
                5
            );

            if ($this->socket !== false) {
                break;
            }

            if (microtime(true) >= $deadline) {
                throw new TimeoutException("WebSocket connect to {$this->host}:{$this->port}", $timeoutMs);
            }

            usleep(100_000);
        }

        stream_set_timeout($this->socket, 30);
        stream_set_blocking($this->socket, false);
        $this->handshake();
        $this->connected = true;
    }

    public function send(string $message): void
    {
        $this->assertConnected();
        $frame = $this->encodeFrame($message);
        $written = @fwrite($this->socket, $frame);
        if ($written === false) {
            throw new BrowserDisconnectException('Failed to write to socket');
        }
    }

    public function receive(int $timeoutMs = 30000): ?string
    {
        if (!empty($this->messageQueue)) {
            return array_shift($this->messageQueue);
        }

        $deadline = microtime(true) + $timeoutMs / 1000;

        while (microtime(true) < $deadline) {
            $this->readFrames();
            if (!empty($this->messageQueue)) {
                return array_shift($this->messageQueue);
            }
            usleep(5_000);
        }

        return null;
    }

    public function receiveAll(int $timeoutMs = 100): array
    {
        $deadline = microtime(true) + $timeoutMs / 1000;
        while (microtime(true) < $deadline) {
            $this->readFrames();
            usleep(2_000);
        }
        $messages = $this->messageQueue;
        $this->messageQueue = [];
        return $messages;
    }

    public function isConnected(): bool
    {
        return $this->connected && $this->socket !== null && !feof($this->socket);
    }

    public function close(): void
    {
        if ($this->socket) {
            if ($this->connected) {
                @fwrite($this->socket, $this->buildCloseFrame());
            }
            @fclose($this->socket);
        }
        $this->socket    = null;
        $this->connected = false;
    }

    private function handshake(): void
    {
        $key = base64_encode(random_bytes(16));

        $request = implode("\r\n", [
            "GET {$this->path} HTTP/1.1",
            "Host: {$this->host}:{$this->port}",
            'Upgrade: websocket',
            'Connection: Upgrade',
            "Sec-WebSocket-Key: {$key}",
            'Sec-WebSocket-Version: 13',
            '',
            '',
        ]);

        fwrite($this->socket, $request);

        $response = '';
        $deadline = microtime(true) + 5;
        stream_set_blocking($this->socket, true);

        while (microtime(true) < $deadline) {
            $line = fgets($this->socket, 4096);
            if ($line === false) {
                break;
            }
            $response .= $line;
            if (str_ends_with($response, "\r\n\r\n")) {
                break;
            }
        }

        stream_set_blocking($this->socket, false);

        if (!str_contains($response, '101')) {
            throw new BrowserDisconnectException("WebSocket handshake failed. Response: " . substr($response, 0, 200));
        }

        $expectedAccept = base64_encode(sha1($key . self::GUID, true));
        if (!str_contains($response, $expectedAccept)) {
            throw new BrowserDisconnectException('WebSocket handshake: invalid Sec-WebSocket-Accept');
        }
    }

    private function readFrames(): void
    {
        if (!$this->isConnected()) {
            return;
        }

        $data = @fread($this->socket, 65536);
        if ($data === false || $data === '') {
            return;
        }

        $this->buffer .= $data;
        $this->processBuffer();
    }

    private function processBuffer(): void
    {
        while (strlen($this->buffer) >= 2) {
            $byte1  = ord($this->buffer[0]);
            $byte2  = ord($this->buffer[1]);
            $opcode = $byte1 & 0x0F;
            $masked  = ($byte2 & 0x80) !== 0;
            $payloadLen = $byte2 & 0x7F;
            $headerLen  = 2;

            if ($payloadLen === 126) {
                if (strlen($this->buffer) < 4) return;
                $payloadLen = unpack('n', substr($this->buffer, 2, 2))[1];
                $headerLen += 2;
            } elseif ($payloadLen === 127) {
                if (strlen($this->buffer) < 10) return;
                $payloadLen = unpack('J', substr($this->buffer, 2, 8))[1];
                $headerLen += 8;
            }

            $maskLen  = $masked ? 4 : 0;
            $frameLen = $headerLen + $maskLen + $payloadLen;

            if (strlen($this->buffer) < $frameLen) {
                return;
            }

            $payload = substr($this->buffer, $headerLen + $maskLen, $payloadLen);

            if ($masked) {
                $mask = substr($this->buffer, $headerLen, 4);
                for ($i = 0; $i < strlen($payload); $i++) {
                    $payload[$i] = $payload[$i] ^ $mask[$i % 4];
                }
            }

            $this->buffer = substr($this->buffer, $frameLen);

            switch ($opcode) {
                case self::OPCODE_TEXT:
                    $this->messageQueue[] = $payload;
                    break;
                case self::OPCODE_PING:
                    @fwrite($this->socket, $this->buildPongFrame($payload));
                    break;
                case self::OPCODE_CLOSE:
                    $this->connected = false;
                    return;
            }
        }
    }

    private function encodeFrame(string $payload): string
    {
        $length = strlen($payload);
        $mask   = random_bytes(4);
        $masked = '';

        for ($i = 0; $i < $length; $i++) {
            $masked .= $payload[$i] ^ $mask[$i % 4];
        }

        $header = chr(0x81);

        if ($length <= 125) {
            $header .= chr(0x80 | $length);
        } elseif ($length <= 65535) {
            $header .= chr(0xFE) . pack('n', $length);
        } else {
            $header .= chr(0xFF) . pack('J', $length);
        }

        return $header . $mask . $masked;
    }

    private function buildCloseFrame(): string
    {
        return chr(0x88) . chr(0x80) . random_bytes(4);
    }

    private function buildPongFrame(string $payload): string
    {
        return chr(0x8A) . chr(strlen($payload)) . $payload;
    }

    private function assertConnected(): void
    {
        if (!$this->isConnected()) {
            throw new BrowserDisconnectException('WebSocket is not connected');
        }
    }
}
