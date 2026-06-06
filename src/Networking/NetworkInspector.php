<?php

declare(strict_types=1);

namespace Xbrowser\Networking;

use Xbrowser\CDP\Client;

class NetworkInspector
{
    private array $requests  = [];
    private array $responses = [];
    private bool  $enabled   = false;

    public function __construct(private readonly Client $cdp) {}

    public function enable(): void
    {
        if ($this->enabled) {
            return;
        }

        $this->cdp->onEvent('Network.requestWillBeSent', function (array $params): void {
            $req = new NetworkRequest(
                requestId:    $params['requestId'] ?? '',
                url:          $params['request']['url'] ?? '',
                method:       $params['request']['method'] ?? 'GET',
                headers:      $params['request']['headers'] ?? [],
                postData:     $params['request']['postData'] ?? '',
                resourceType: $params['type'] ?? 'Other'
            );
            $this->requests[$req->requestId] = $req;
        });

        $this->cdp->onEvent('Network.responseReceived', function (array $params): void {
            $resp = new NetworkResponse(
                requestId:  $params['requestId'] ?? '',
                url:        $params['response']['url'] ?? '',
                statusCode: $params['response']['status'] ?? 0,
                statusText: $params['response']['statusText'] ?? '',
                headers:    $params['response']['headers'] ?? []
            );
            $this->responses[$resp->requestId] = $resp;
        });

        $this->enabled = true;
    }

    public function getRequests(): array
    {
        return array_values($this->requests);
    }

    public function getResponses(): array
    {
        return array_values($this->responses);
    }

    public function getRequestFor(string $url): ?NetworkRequest
    {
        foreach ($this->requests as $req) {
            if (str_contains($req->url, $url)) {
                return $req;
            }
        }
        return null;
    }

    public function getResponseFor(string $url): ?NetworkResponse
    {
        foreach ($this->responses as $resp) {
            if (str_contains($resp->url, $url)) {
                return $resp;
            }
        }
        return null;
    }

    public function clear(): void
    {
        $this->requests  = [];
        $this->responses = [];
    }

    public function summary(): array
    {
        $total     = count($this->requests);
        $succeeded = count(array_filter($this->responses, fn($r) => $r->isSuccess()));
        $failed    = count($this->responses) - $succeeded;

        return compact('total', 'succeeded', 'failed');
    }
}
