<?php

declare(strict_types=1);

namespace Xbrowser\Events;

interface EventInterface
{
    public function getName(): string;
    public function getPayload(): array;
    public function getTimestamp(): float;
}
