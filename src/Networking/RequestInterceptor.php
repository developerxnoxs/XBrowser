<?php

declare(strict_types=1);

namespace Xbrowser\Networking;

use Xbrowser\CDP\Client;

/**
 * RequestInterceptor — aktif intercept request sebelum dikirim ke server.
 *
 * Menggunakan CDP Fetch domain (bukan Network domain) sehingga bisa:
 *   ✓ Memblokir request (iklan, tracker, resource tertentu)
 *   ✓ Memodifikasi URL, method, headers, body sebelum dikirim
 *   ✓ Membalas request dengan response buatan sendiri (mock)
 *   ✓ Mengizinkan request melanjut tanpa perubahan
 *
 * Berbeda dari NetworkCapture yang hanya mengamati (pasif), interceptor ini
 * menghentikan setiap request yang cocok dengan pola dan menunggu keputusan handler.
 *
 * Penggunaan dasar:
 *   $interceptor = $page->intercept();
 *
 *   // Blokir semua gambar
 *   $interceptor->onRequest(function (InterceptedRequest $req) {
 *       if ($req->resourceType === 'Image') {
 *           $req->abort();
 *       } else {
 *           $req->continue();
 *       }
 *   });
 *
 *   // Tambahkan header ke semua request
 *   $interceptor->onRequest(function (InterceptedRequest $req) {
 *       $req->continue(['headers' => array_merge(
 *           $req->headers,
 *           ['X-Custom-Header' => 'xbrowser']
 *       )]);
 *   });
 *
 *   // Mock respons API
 *   $interceptor->onRequest(function (InterceptedRequest $req) {
 *       if (str_contains($req->url, '/api/user')) {
 *           $req->respond([
 *               'status'   => 200,
 *               'mimeType' => 'application/json',
 *               'body'     => '{"id":1,"name":"Mock User"}',
 *           ]);
 *       } else {
 *           $req->continue();
 *       }
 *   });
 *
 *   $page->goto('https://example.com');
 *   $interceptor->disable(); // matikan setelah selesai
 */
class RequestInterceptor
{
    private bool    $enabled  = false;
    private array   $handlers = [];
    private array   $patterns = [];

    /**
     * Statistik intercept.
     * @var array{total:int, blocked:int, modified:int, mocked:int, allowed:int}
     */
    private array $stats = [
        'total'    => 0,
        'blocked'  => 0,
        'modified' => 0,
        'mocked'   => 0,
        'allowed'  => 0,
    ];

    public function __construct(private readonly Client $cdp) {}

    // ── Konfigurasi pola URL ──────────────────────────────────────────────────

    /**
     * Tambahkan pola URL yang akan di-intercept.
     * Gunakan wildcard '*' — misal '*.jpg', '*\/api\/*'.
     * Default (kosong) = intercept semua request.
     *
     * @param string      $urlPattern    Pola URL (CDP Fetch pattern, bukan regex)
     * @param string|null $resourceType  Filter tipe resource opsional:
     *   Document, Stylesheet, Image, Media, Font, Script, TextTrack, XHR,
     *   Fetch, EventSource, WebSocket, Manifest, SignedExchange, Ping,
     *   CSPViolationReport, Preflight, Other
     */
    public function addPattern(string $urlPattern = '*', ?string $resourceType = null): static
    {
        $pattern = ['urlPattern' => $urlPattern];
        if ($resourceType !== null) {
            $pattern['resourceType'] = $resourceType;
        }
        $this->patterns[] = $pattern;
        return $this;
    }

    // ── Shortcut pattern umum ─────────────────────────────────────────────────

    /** Intercept semua request (default). */
    public function interceptAll(): static
    {
        return $this->addPattern('*');
    }

    /** Intercept hanya XHR dan Fetch. */
    public function interceptXhr(): static
    {
        $this->addPattern('*', 'XHR');
        $this->addPattern('*', 'Fetch');
        return $this;
    }

    /** Intercept hanya resource statis (gambar, CSS, font). */
    public function interceptStatic(): static
    {
        $this->addPattern('*', 'Image');
        $this->addPattern('*', 'Stylesheet');
        $this->addPattern('*', 'Font');
        $this->addPattern('*', 'Media');
        return $this;
    }

    // ── Handler ───────────────────────────────────────────────────────────────

    /**
     * Daftarkan handler yang dipanggil untuk setiap request yang di-intercept.
     *
     * Handler HARUS memanggil salah satu dari:
     *   $request->continue()
     *   $request->abort()
     *   $request->respond([...])
     *
     * Jika tidak ada handler yang menangani request, otomatis di-continue.
     *
     * @param callable(InterceptedRequest): void $handler
     */
    public function onRequest(callable $handler): static
    {
        $this->handlers[] = $handler;
        return $this;
    }

    // ── Preset handler umum ───────────────────────────────────────────────────

    /**
     * Blokir resource berdasarkan tipe.
     *
     * @param string[] $types  Contoh: ['Image', 'Media', 'Font']
     */
    public function blockResourceTypes(array $types): static
    {
        return $this->onRequest(function (InterceptedRequest $req) use ($types): void {
            if (in_array($req->resourceType, $types, true)) {
                $req->abort();
                $this->stats['blocked']++;
            }
        });
    }

    /**
     * Blokir URL yang mengandung substring tertentu.
     *
     * @param string[] $patterns  Contoh: ['ads.', 'analytics', 'doubleclick']
     */
    public function blockUrls(array $patterns): static
    {
        return $this->onRequest(function (InterceptedRequest $req) use ($patterns): void {
            foreach ($patterns as $p) {
                if (str_contains($req->url, $p)) {
                    $req->abort();
                    $this->stats['blocked']++;
                    return;
                }
            }
        });
    }

    /**
     * Tambahkan header ke semua request yang lewat.
     *
     * @param array<string,string> $headers
     */
    public function addHeaders(array $headers): static
    {
        return $this->onRequest(function (InterceptedRequest $req) use ($headers): void {
            if (!$req->isHandled()) {
                $req->continue(['headers' => array_merge($req->headers, $headers)]);
                $this->stats['modified']++;
            }
        });
    }

    /**
     * Blokir iklan & tracker umum (daftar domain bawaan).
     */
    public function blockAds(): static
    {
        return $this->blockUrls([
            'doubleclick.net',
            'googlesyndication.com',
            'googleadservices.com',
            'ads.twitter.com',
            'analytics.google.com',
            'google-analytics.com',
            'facebook.com/tr',
            'hotjar.com',
            'mixpanel.com',
            'segment.com',
            'fullstory.com',
            'mouseflow.com',
            'cdn.optimizely.com',
        ]);
    }

    // ── Aktifkan / nonaktifkan ────────────────────────────────────────────────

    public function enable(): void
    {
        if ($this->enabled) {
            return;
        }

        $patterns = empty($this->patterns)
            ? [['urlPattern' => '*']]
            : $this->patterns;

        $this->cdp->send([
            'method' => 'Fetch.enable',
            'params' => ['patterns' => $patterns],
        ]);

        $this->cdp->onEvent('Fetch.requestPaused', function (array $p): void {
            $this->stats['total']++;

            $req = new InterceptedRequest(
                cdp:          $this->cdp,
                requestId:    $p['requestId']                    ?? '',
                url:          $p['request']['url']               ?? '',
                method:       $p['request']['method']            ?? 'GET',
                headers:      $p['request']['headers']           ?? [],
                postData:     $p['request']['postData']          ?? '',
                resourceType: $p['resourceType']                 ?? 'Other',
                frameId:      $p['frameId']                      ?? '',
            );

            foreach ($this->handlers as $handler) {
                if ($req->isHandled()) {
                    break;
                }
                ($handler)($req);
            }

            // Fallback: jika tidak ada handler yang menangani, teruskan request
            if (!$req->isHandled()) {
                $req->continue();
                $this->stats['allowed']++;
            }
        });

        $this->enabled = true;
    }

    public function disable(): void
    {
        if (!$this->enabled) {
            return;
        }

        $this->cdp->send(['method' => 'Fetch.disable', 'params' => new \stdClass()]);
        $this->enabled  = false;
        $this->handlers = [];
    }

    // ── Statistik ─────────────────────────────────────────────────────────────

    public function getStats(): array
    {
        return $this->stats;
    }

    public function resetStats(): void
    {
        $this->stats = ['total' => 0, 'blocked' => 0, 'modified' => 0, 'mocked' => 0, 'allowed' => 0];
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }
}
