<?php

declare(strict_types=1);

namespace Xbrowser\Networking;

class NetworkRequest
{
    public readonly float $timestamp;

    public function __construct(
        public readonly string $requestId,
        public readonly string $url,
        public readonly string $method,
        public readonly array  $headers,
        public readonly string $postData = '',
        public readonly string $resourceType = 'Other'
    ) {
        $this->timestamp = microtime(true);
    }

    public function toArray(): array
    {
        return [
            'requestId'    => $this->requestId,
            'url'          => $this->url,
            'method'       => $this->method,
            'headers'      => $this->headers,
            'postData'     => $this->postData,
            'resourceType' => $this->resourceType,
            'timestamp'    => $this->timestamp,
        ];
    }
}
