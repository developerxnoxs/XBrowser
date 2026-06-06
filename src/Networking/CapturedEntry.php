<?php

declare(strict_types=1);

namespace Xbrowser\Networking;

/**
 * Satu entri penuh dari network capture:
 * request + response header + response body (opsional).
 */
class CapturedEntry
{
    public string  $responseBody    = '';
    public int     $responseStatus  = 0;
    public array   $responseHeaders = [];
    public bool    $bodyFetched     = false;

    public function __construct(
        public readonly string $requestId,
        public readonly string $method,
        public readonly string $url,
        public readonly array  $requestHeaders,
        public readonly string $postData,
        public readonly string $resourceType,
        public readonly float  $capturedAt,
        public bool            $hasCredentials = false
    ) {}

    public function toArray(): array
    {
        return [
            'requestId'       => $this->requestId,
            'method'          => $this->method,
            'url'             => $this->url,
            'resourceType'    => $this->resourceType,
            'requestHeaders'  => $this->requestHeaders,
            'postData'        => $this->postData,
            'hasCredentials'  => $this->hasCredentials,
            'responseStatus'  => $this->responseStatus,
            'responseHeaders' => $this->responseHeaders,
            'responseBody'    => $this->responseBody,
            'bodyFetched'     => $this->bodyFetched,
            'capturedAt'      => $this->capturedAt,
        ];
    }
}
