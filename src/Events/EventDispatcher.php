<?php

declare(strict_types=1);

namespace Xbrowser\Events;

class EventDispatcher
{
    private array $listeners = [];

    public function on(string $eventName, callable $listener): void
    {
        $this->listeners[$eventName][] = $listener;
    }

    public function once(string $eventName, callable $listener): void
    {
        $wrapper = null;
        $wrapper = function (EventInterface $event) use ($eventName, $listener, &$wrapper): void {
            $listener($event);
            $this->off($eventName, $wrapper);
        };
        $this->on($eventName, $wrapper);
    }

    public function off(string $eventName, callable $listener): void
    {
        if (!isset($this->listeners[$eventName])) {
            return;
        }
        $this->listeners[$eventName] = array_filter(
            $this->listeners[$eventName],
            fn($l) => $l !== $listener
        );
    }

    public function emit(EventInterface $event): void
    {
        $name = $event->getName();
        foreach ($this->listeners[$name] ?? [] as $listener) {
            $listener($event);
        }
        foreach ($this->listeners['*'] ?? [] as $listener) {
            $listener($event);
        }
    }

    public function removeAll(string $eventName = ''): void
    {
        if ($eventName) {
            unset($this->listeners[$eventName]);
        } else {
            $this->listeners = [];
        }
    }

    public function getListeners(string $eventName): array
    {
        return $this->listeners[$eventName] ?? [];
    }
}
