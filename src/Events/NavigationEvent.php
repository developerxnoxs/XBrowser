<?php

declare(strict_types=1);

namespace Xbrowser\Events;

class NavigationEvent extends AbstractEvent
{
    public function __construct(
        private readonly string $fromUrl,
        private readonly string $toUrl,
        private readonly string $reason = 'navigation'
    ) {
        parent::__construct();
    }

    public function getName(): string
    {
        return 'page.navigation';
    }

    public function getPayload(): array
    {
        return [
            'fromUrl' => $this->fromUrl,
            'toUrl'   => $this->toUrl,
            'reason'  => $this->reason,
        ];
    }

    public function getToUrl(): string { return $this->toUrl; }
}
