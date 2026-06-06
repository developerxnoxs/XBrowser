<?php

declare(strict_types=1);

namespace Xbrowser\Events;

class NetworkRequestEvent extends AbstractEvent
{
    public function __construct(
        private readonly string $requestId,
        private readonly string $url,
        private readonly string $method,
        private readonly array $headers = []
    ) {
        parent::__construct();
    }

    public function getName(): string
    {
        return 'network.request';
    }

    public function getPayload(): array
    {
        return [
            'requestId' => $this->requestId,
            'url'       => $this->url,
            'method'    => $this->method,
            'headers'   => $this->headers,
        ];
    }
}
