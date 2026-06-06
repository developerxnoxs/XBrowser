<?php

declare(strict_types=1);

namespace Xbrowser\Events;

class ClickEvent extends AbstractEvent
{
    public function __construct(
        private readonly string $selector,
        private readonly float $x = 0,
        private readonly float $y = 0
    ) {
        parent::__construct();
    }

    public function getName(): string
    {
        return 'element.clicked';
    }

    public function getPayload(): array
    {
        return [
            'selector' => $this->selector,
            'x'        => $this->x,
            'y'        => $this->y,
        ];
    }

    public function getSelector(): string { return $this->selector; }
}
