<?php

/**
 * Contoh Request Interception dengan Xbrowser.
 *
 * Menunjukkan tiga use-case utama:
 *  1. Blokir gambar & iklan → halaman lebih cepat
 *  2. Inject header ke setiap request
 *  3. Mock respons API (tidak perlu jaringan)
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Xbrowser\Browser\Browser;
use Xbrowser\Networking\InterceptedRequest;

$browser = new Browser();
$page    = $browser->newPage();

// ═══════════════════════════════════════════════════════════════════════════
// CONTOH 1 — Blokir semua gambar dan tracker iklan
// ═══════════════════════════════════════════════════════════════════════════
echo "\n[CONTOH 1] Blokir gambar + iklan\n";

$interceptor = $page->intercept();
$interceptor
    ->blockResourceTypes(['Image', 'Media', 'Font'])  // blokir resource berat
    ->blockAds();                                      // blokir tracker umum

$page->goto('https://example.com');

$stats = $interceptor->getStats();
echo "Total request   : {$stats['total']}\n";
echo "Diblokir        : {$stats['blocked']}\n";
echo "Diizinkan       : {$stats['allowed']}\n";

$interceptor->disable();

// ═══════════════════════════════════════════════════════════════════════════
// CONTOH 2 — Inject header ke setiap request
// ═══════════════════════════════════════════════════════════════════════════
echo "\n[CONTOH 2] Inject custom header\n";

$interceptor2 = $page->intercept();
$interceptor2->onRequest(function (InterceptedRequest $req): void {
    $req->continue([
        'headers' => array_merge($req->headers, [
            'X-Xbrowser'       => '1.0',
            'X-Custom-Token'   => 'secret-token-123',
        ]),
    ]);
});

$page->goto('https://httpbin.org/headers');
echo $page->render();
$interceptor2->disable();

// ═══════════════════════════════════════════════════════════════════════════
// CONTOH 3 — Mock respons API (tanpa server nyata)
// ═══════════════════════════════════════════════════════════════════════════
echo "\n[CONTOH 3] Mock respons API\n";

$interceptor3 = $page->intercept();
$interceptor3->onRequest(function (InterceptedRequest $req): void {
    if (str_contains($req->url, '/api/user')) {
        // Balas dengan data palsu
        $req->respond([
            'status'   => 200,
            'mimeType' => 'application/json',
            'body'     => json_encode([
                'id'    => 1,
                'name'  => 'Mock User',
                'email' => 'mock@xbrowser.dev',
            ]),
        ]);
    } else {
        $req->continue();
    }
});

$page->goto('https://example.com');
// Panggil endpoint yang di-mock
$result = $page->evaluate(<<<JS
    fetch('/api/user')
        .then(r => r.json())
        .then(d => JSON.stringify(d))
JS);
echo "API Response: {$result}\n";
$interceptor3->disable();

// ═══════════════════════════════════════════════════════════════════════════
// CONTOH 4 — Intercept XHR saja, log ke terminal
// ═══════════════════════════════════════════════════════════════════════════
echo "\n[CONTOH 4] Log semua XHR/Fetch request\n";

$interceptor4 = $page->intercept();
$interceptor4
    ->interceptXhr()
    ->onRequest(function (InterceptedRequest $req): void {
        echo "  [{$req->method}] {$req->url}\n";
        $req->continue();
    });

$page->goto('https://httpbin.org/get');
$interceptor4->disable();

$browser->close();
echo "\nSelesai.\n";
