<?php

declare(strict_types=1);

namespace Xbrowser\Networking;

use Xbrowser\CDP\Client;

/**
 * Representasi satu request yang sedang di-intercept.
 *
 * Di dalam handler, panggil salah satu:
 *   $request->continue()          — teruskan apa adanya
 *   $request->continue([...])     — teruskan dengan modifikasi header/url/method/body
 *   $request->abort()             — batalkan request (net::ERR_ABORTED)
 *   $request->respond([...])      — balas dengan response buatan sendiri
 */
class InterceptedRequest
{
    private bool $handled = false;

    public function __construct(
        private readonly Client $cdp,
        public readonly string  $requestId,
        public readonly string  $url,
        public readonly string  $method,
        public readonly array   $headers,
        public readonly string  $postData,
        public readonly string  $resourceType,
        public readonly string  $frameId,
    ) {}

    /**
     * Teruskan request, dengan opsi override field tertentu.
     *
     * @param array $overrides  Key yang didukung:
     *   'url'       => string   — redirect ke URL lain
     *   'method'    => string   — ganti HTTP method
     *   'headers'   => array    — ganti headers (key => value)
     *   'postData'  => string   — ganti body (harus base64 jika biner)
     */
    public function continue(array $overrides = []): void
    {
        $this->assertNotHandled();

        $params = ['requestId' => $this->requestId];

        if (isset($overrides['url']))      $params['url']             = $overrides['url'];
        if (isset($overrides['method']))   $params['method']          = $overrides['method'];
        if (isset($overrides['postData'])) $params['postData']        = base64_encode($overrides['postData']);
        if (isset($overrides['headers'])) {
            $params['headers'] = $this->encodeHeaders($overrides['headers']);
        }

        $this->cdp->send(['method' => 'Fetch.continueRequest', 'params' => $params]);
        $this->handled = true;
    }

    /**
     * Batalkan request.
     *
     * @param string $errorReason  Alasan CDP — salah satu:
     *   Failed | Aborted | TimedOut | AccessDenied | ConnectionClosed |
     *   ConnectionReset | ConnectionRefused | ConnectionAborted |
     *   ConnectionFailed | NameNotResolved | InternetDisconnected |
     *   AddressUnreachable | BlockedByClient | BlockedByResponse
     */
    public function abort(string $errorReason = 'BlockedByClient'): void
    {
        $this->assertNotHandled();

        $this->cdp->send([
            'method' => 'Fetch.failRequest',
            'params' => [
                'requestId'   => $this->requestId,
                'errorReason' => $errorReason,
            ],
        ]);

        $this->handled = true;
    }

    /**
     * Balas request dengan response buatan sendiri (mock/stub).
     *
     * @param array $response  Key yang didukung:
     *   'status'       => int     (default 200)
     *   'headers'      => array   key => value
     *   'body'         => string  response body (teks atau biner)
     *   'mimeType'     => string  (default 'text/plain')
     */
    public function respond(array $response): void
    {
        $this->assertNotHandled();

        $status   = $response['status']   ?? 200;
        $mimeType = $response['mimeType'] ?? 'text/plain';
        $body     = $response['body']     ?? '';
        $headers  = $response['headers']  ?? [];

        $mergedHeaders = array_merge(
            ['Content-Type' => $mimeType],
            $headers
        );

        $this->cdp->send([
            'method' => 'Fetch.fulfillRequest',
            'params' => [
                'requestId'       => $this->requestId,
                'responseCode'    => $status,
                'responseHeaders' => $this->encodeHeaders($mergedHeaders),
                'body'            => base64_encode($body),
            ],
        ]);

        $this->handled = true;
    }

    public function isHandled(): bool
    {
        return $this->handled;
    }

    // ── Internal ──────────────────────────────────────────────────────────────

    private function encodeHeaders(array $headers): array
    {
        $encoded = [];
        foreach ($headers as $name => $value) {
            $encoded[] = ['name' => $name, 'value' => (string) $value];
        }
        return $encoded;
    }

    private function assertNotHandled(): void
    {
        if ($this->handled) {
            throw new \LogicException(
                "Request [{$this->requestId}] sudah di-handle. " .
                "Panggil hanya satu dari: continue(), abort(), atau respond()."
            );
        }
    }
}
