<?php

declare(strict_types=1);

namespace Xbrowser\Events;

abstract class AbstractEvent implements EventInterface
{
    private float $timestamp;

    public function __construct()
    {
        $this->timestamp = microtime(true);
    }

    public function getTimestamp(): float
    {
        return $this->timestamp;
    }
}
