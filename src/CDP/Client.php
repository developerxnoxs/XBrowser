<?php

declare(strict_types=1);

namespace Xbrowser\CDP;

use Xbrowser\Events\EventDispatcher;
use Xbrowser\Events\NetworkRequestEvent;
use Xbrowser\Exceptions\BrowserDisconnectException;
use Xbrowser\Exceptions\JavaScriptException;
use Xbrowser\Exceptions\TimeoutException;
use Xbrowser\Utils\Logger;

class Client
{
    private WebSocketClient $ws;
    private int $commandId = 0;
    private array $pendingCommands = [];
    private array $receivedResponses = [];
    private array $eventListeners = [];
    private Logger $logger;

    public function __construct(
        string $wsUrl,
        private readonly EventDispatcher $dispatcher,
        ?Logger $logger = null
    ) {
        $this->ws     = new WebSocketClient($wsUrl);
        $this->logger = $logger ?? new Logger();
    }

    public function connect(int $timeoutMs = 10000): void
    {
        $this->ws->connect($timeoutMs);
        $this->logger->debug("CDP connected");
    }

    public function send(array $command, int $timeoutMs = 30000): array
    {
        $id = ++$this->commandId;
        $command['id'] = $id;
        $json = json_encode($command);

        $this->logger->debug("CDP → {$command['method']} (id={$id})");
        $this->ws->send($json);

        return $this->waitForResponse($id, $timeoutMs);
    }

    public function onEvent(string $method, callable $handler): void
    {
        $this->eventListeners[$method][] = $handler;
    }

    public function pump(int $timeoutMs = 50): void
    {
        $messages = $this->ws->receiveAll($timeoutMs);
        foreach ($messages as $raw) {
            $this->processMessage($raw);
        }
    }

    public function isConnected(): bool
    {
        return $this->ws->isConnected();
    }

    public function close(): void
    {
        $this->ws->close();
    }

    private function waitForResponse(int $id, int $timeoutMs): array
    {
        $deadline = microtime(true) + $timeoutMs / 1000;

        while (microtime(true) < $deadline) {
            $raw = $this->ws->receive(min($timeoutMs, 200));
            if ($raw !== null) {
                $this->processMessage($raw);
            }

            if (isset($this->receivedResponses[$id])) {
                $response = $this->receivedResponses[$id];
                unset($this->receivedResponses[$id]);
                return $response;
            }
        }

        throw new TimeoutException("CDP command id={$id}", $timeoutMs);
    }

    private function processMessage(string $raw): void
    {
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return;
        }

        if (isset($data['id'])) {
            $id = $data['id'];
            if (isset($data['error'])) {
                $this->receivedResponses[$id] = ['error' => $data['error']];
            } else {
                $this->receivedResponses[$id] = $data['result'] ?? [];
            }
            return;
        }

        if (isset($data['method'])) {
            $this->handleEvent($data['method'], $data['params'] ?? []);
        }
    }

    private function handleEvent(string $method, array $params): void
    {
        $this->logger->debug("CDP ← {$method}");

        foreach ($this->eventListeners[$method] ?? [] as $handler) {
            $handler($params);
        }

        foreach ($this->eventListeners['*'] ?? [] as $handler) {
            $handler($method, $params);
        }

        if ($method === 'Network.requestWillBeSent') {
            $event = new NetworkRequestEvent(
                $params['requestId'] ?? '',
                $params['request']['url'] ?? '',
                $params['request']['method'] ?? 'GET',
                $params['request']['headers'] ?? []
            );
            $this->dispatcher->emit($event);
        }
    }
}
