<?php

declare(strict_types=1);

namespace Xbrowser\Networking;

use Xbrowser\CDP\Client;
use Xbrowser\CDP\Protocol;

/**
 * NetworkCapture — intercept & rekam semua network traffic via CDP.
 *
 * Setara dengan blok CDP di intercept.js (Puppeteer):
 *   - Network.requestWillBeSent  → tangkap request + postData + deteksi kredensial
 *   - Network.responseReceived   → simpan status & response headers
 *   - Network.loadingFinished    → ambil response body (Network.getResponseBody)
 *
 * Penggunaan dasar:
 *   $capture = $page->startCapture();
 *   $capture->filterDomain('facebook.com')
 *           ->scanCredentials(['email@', 'password', 'pass=']);
 *   $page->goto('https://m.facebook.com/login/');
 *   // ... lakukan aksi ...
 *   $capture->saveJson('intercept_result.json');
 *   print_r($capture->summary());
 */
class NetworkCapture
{
    private array  $entries  = [];   // requestId => CapturedEntry
    private array  $filters  = [];   // pola URL yang harus dicocokkan (substring)
    private array  $credKeys = [];   // kata kunci yang menandai kredensial di postData
    private bool   $enabled  = false;
    private array  $bodyQueue = [];  // requestId yang menunggu pengambilan body
    private bool   $fetchBodiesLive = true; // ambil body saat loadingFinished

    public function __construct(private readonly Client $cdp) {}

    // ── Konfigurasi ──────────────────────────────────────────────────────────

    /**
     * Hanya rekam URL yang mengandung substring ini.
     * Panggil beberapa kali untuk multi-filter (OR).
     */
    public function filterDomain(string $domain): static
    {
        $this->filters[] = $domain;
        return $this;
    }

    /**
     * Daftarkan kata kunci yang menandai kredensial di postData.
     * Contoh: ['email@example.com', 'password', 'encpass', 'pass=']
     */
    public function scanCredentials(array $keywords): static
    {
        $this->credKeys = array_merge($this->credKeys, $keywords);
        return $this;
    }

    /**
     * Nonaktifkan pengambilan response body secara langsung (hemat waktu/memori).
     * Body tetap bisa diambil manual lewat fetchPendingBodies().
     */
    public function disableLiveBodyFetch(): static
    {
        $this->fetchBodiesLive = false;
        return $this;
    }

    // ── Aktifkan capture ─────────────────────────────────────────────────────

    public function enable(): void
    {
        if ($this->enabled) {
            return;
        }

        // Aktifkan domain Network di CDP
        // Gunakan networkEnable() bukan enable('Network') — Chrome >=112 validasi
        // params sebagai object {}, bukan array [].
        $this->cdp->send(Protocol::networkEnable());

        // ── requestWillBeSent ────────────────────────────────────────────────
        $this->cdp->onEvent('Network.requestWillBeSent', function (array $p): void {
            $url = $p['request']['url'] ?? '';
            if (!$this->urlMatches($url)) {
                return;
            }

            $postData = $p['request']['postData'] ?? '';
            $hasCred  = $this->detectCredentials($postData);

            $entry = new CapturedEntry(
                requestId:      $p['requestId'] ?? '',
                method:         $p['request']['method'] ?? 'GET',
                url:            $url,
                requestHeaders: $p['request']['headers'] ?? [],
                postData:       $postData,
                resourceType:   $p['type'] ?? 'Other',
                capturedAt:     microtime(true),
                hasCredentials: $hasCred
            );

            $this->entries[$entry->requestId] = $entry;
        });

        // ── responseReceived ────────────────────────────────────────────────
        $this->cdp->onEvent('Network.responseReceived', function (array $p): void {
            $id = $p['requestId'] ?? '';
            if (!isset($this->entries[$id])) {
                return;
            }

            $this->entries[$id]->responseStatus  = $p['response']['status'] ?? 0;
            $this->entries[$id]->responseHeaders = $p['response']['headers'] ?? [];
        });

        // ── loadingFinished ─────────────────────────────────────────────────
        $this->cdp->onEvent('Network.loadingFinished', function (array $p): void {
            $id = $p['requestId'] ?? '';
            if (!isset($this->entries[$id])) {
                return;
            }

            $entry = $this->entries[$id];

            if ($this->fetchBodiesLive && $this->shouldFetchBody($entry)) {
                $this->doFetchBody($id);
            } else {
                $this->bodyQueue[] = $id;
            }
        });

        $this->enabled = true;
    }

    // ── Ambil body yang tertunda secara manual ───────────────────────────────

    /**
     * Ambil response body untuk semua request yang masih dalam antrian.
     * Berguna setelah selesai navigasi / aksi.
     *
     * @param array|null $filter  null = semua; 'all' = paksa semua; atau array requestId
     */
    public function fetchPendingBodies(?array $requestIds = null): void
    {
        $ids = $requestIds ?? $this->bodyQueue;

        foreach ($ids as $id) {
            if (!isset($this->entries[$id])) {
                continue;
            }
            $this->doFetchBody($id);
        }

        // Kosongkan antrian yang sudah diambil
        if ($requestIds === null) {
            $this->bodyQueue = [];
        }
    }

    /**
     * Paksa ambil body untuk SEMUA entry yang cocok dengan filter.
     */
    public function fetchAllBodies(): void
    {
        foreach (array_keys($this->entries) as $id) {
            if (!$this->entries[$id]->bodyFetched) {
                $this->doFetchBody($id);
            }
        }
    }

    // ── Akses data ────────────────────────────────────────────────────────────

    /** Semua entry yang direkam. */
    public function getAll(): array
    {
        return array_values($this->entries);
    }

    /** Entry yang mengandung kredensial. */
    public function getWithCredentials(): array
    {
        return array_values(array_filter(
            $this->entries,
            fn(CapturedEntry $e) => $e->hasCredentials
        ));
    }

    /** Entry dengan method POST. */
    public function getPosts(): array
    {
        return array_values(array_filter(
            $this->entries,
            fn(CapturedEntry $e) => $e->method === 'POST'
        ));
    }

    /** Cari entry berdasarkan substring URL. */
    public function findByUrl(string $pattern): array
    {
        return array_values(array_filter(
            $this->entries,
            fn(CapturedEntry $e) => str_contains($e->url, $pattern)
        ));
    }

    /** Ringkasan statistik. */
    public function summary(): array
    {
        $all    = array_values($this->entries);
        $posts  = array_filter($all, fn($e) => $e->method === 'POST');
        $creds  = array_filter($all, fn($e) => $e->hasCredentials);
        $ok     = array_filter($all, fn($e) => $e->responseStatus >= 200 && $e->responseStatus < 300);
        $err    = array_filter($all, fn($e) => $e->responseStatus >= 400);

        return [
            'total'          => count($all),
            'posts'          => count($posts),
            'withCredentials'=> count($creds),
            'succeeded'      => count($ok),
            'failed'         => count($err),
            'bodyFetched'    => count(array_filter($all, fn($e) => $e->bodyFetched)),
        ];
    }

    /** Ekspor semua entri ke file JSON. */
    public function saveJson(string $path, bool $prettyPrint = true): void
    {
        $flags = $prettyPrint ? JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES : 0;
        $data  = [
            'capturedAt' => date('c'),
            'summary'    => $this->summary(),
            'entries'    => array_map(fn($e) => $e->toArray(), array_values($this->entries)),
        ];

        $dir = dirname($path);
        if ($dir && $dir !== '.' && !is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($path, json_encode($data, $flags));
    }

    /** Reset semua data yang tersimpan. */
    public function clear(): void
    {
        $this->entries   = [];
        $this->bodyQueue = [];
    }

    // ── Internal ──────────────────────────────────────────────────────────────

    private function urlMatches(string $url): bool
    {
        if (empty($this->filters)) {
            return true;
        }

        foreach ($this->filters as $filter) {
            if (str_contains($url, $filter)) {
                return true;
            }
        }

        return false;
    }

    private function detectCredentials(string $postData): bool
    {
        if (empty($this->credKeys) || $postData === '') {
            return false;
        }

        foreach ($this->credKeys as $kw) {
            if (str_contains($postData, $kw)) {
                return true;
            }
        }

        return false;
    }

    private function shouldFetchBody(CapturedEntry $entry): bool
    {
        return $entry->hasCredentials
            || $entry->method === 'POST'
            || str_contains($entry->url, '/a/bz');
    }

    private function doFetchBody(string $requestId): void
    {
        if (!isset($this->entries[$requestId])) {
            return;
        }

        try {
            $result = $this->cdp->send(
                Protocol::getResponseBody($requestId),
                5000
            );

            $body = $result['body'] ?? '';

            // CDP mengembalikan base64 jika biner
            if (!empty($result['base64Encoded'])) {
                $body = base64_decode($body);
            }

            $this->entries[$requestId]->responseBody  = mb_substr($body, 0, 5000);
            $this->entries[$requestId]->bodyFetched   = true;
        } catch (\Throwable) {
            // Body tidak tersedia (redirect, cancelled, dll.) — abaikan
        }
    }
}
