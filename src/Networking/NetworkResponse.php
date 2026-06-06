<?php

declare(strict_types=1);

namespace Xbrowser\Networking;

class NetworkResponse
{
    public readonly float $timestamp;

    public function __construct(
        public readonly string $requestId,
        public readonly string $url,
        public readonly int    $statusCode,
        public readonly string $statusText,
        public readonly array  $headers,
        public readonly float  $mimeType = 0,
        public readonly string $body = ''
    ) {
        $this->timestamp = microtime(true);
    }

    public function isSuccess(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }

    public function toArray(): array
    {
        return [
            'requestId'  => $this->requestId,
            'url'        => $this->url,
            'statusCode' => $this->statusCode,
            'statusText' => $this->statusText,
            'headers'    => $this->headers,
            'body'       => $this->body,
            'timestamp'  => $this->timestamp,
        ];
    }
}
