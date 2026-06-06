<?php

declare(strict_types=1);

namespace Xbrowser\Tests\Unit\Networking;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Xbrowser\CDP\Client;
use Xbrowser\Networking\CapturedEntry;
use Xbrowser\Networking\NetworkCapture;

class NetworkCaptureTest extends TestCase
{
    private Client&MockObject $cdp;
    private NetworkCapture $capture;

    /** Simpan semua listener yang didaftarkan via onEvent */
    private array $listeners = [];

    protected function setUp(): void
    {
        $this->cdp = $this->createMock(Client::class);

        // Tangkap setiap onEvent() call supaya kita bisa trigger manual
        $this->listeners = [];
        $this->cdp
            ->method('onEvent')
            ->willReturnCallback(function (string $method, callable $cb): void {
                $this->listeners[$method][] = $cb;
            });

        // Network.enable tidak perlu return value
        $this->cdp->method('send')->willReturn([]);

        $this->capture = new NetworkCapture($this->cdp);
        $this->capture->enable();
    }

    // ── Helper ────────────────────────────────────────────────────────────────

    private function fireEvent(string $method, array $params): void
    {
        foreach ($this->listeners[$method] ?? [] as $cb) {
            $cb($params);
        }
    }

    private function makeSent(string $id, string $url, string $method = 'GET', string $postData = ''): void
    {
        $this->fireEvent('Network.requestWillBeSent', [
            'requestId' => $id,
            'type'      => 'XHR',
            'request'   => [
                'url'      => $url,
                'method'   => $method,
                'headers'  => ['Accept' => 'application/json'],
                'postData' => $postData,
            ],
        ]);
    }

    private function makeReceived(string $id, int $status = 200): void
    {
        $this->fireEvent('Network.responseReceived', [
            'requestId' => $id,
            'response'  => [
                'status'     => $status,
                'statusText' => 'OK',
                'headers'    => ['Content-Type' => 'application/json'],
            ],
        ]);
    }

    private function makeFinished(string $id): void
    {
        $this->fireEvent('Network.loadingFinished', ['requestId' => $id]);
    }

    // ── CapturedEntry ─────────────────────────────────────────────────────────

    public function testCapturedEntryCreation(): void
    {
        $entry = new CapturedEntry('r1', 'POST', 'https://example.com', [], 'foo=bar', 'XHR', microtime(true));

        $this->assertSame('r1',                  $entry->requestId);
        $this->assertSame('POST',                $entry->method);
        $this->assertSame('https://example.com', $entry->url);
        $this->assertSame('foo=bar',             $entry->postData);
        $this->assertFalse($entry->hasCredentials);
        $this->assertFalse($entry->bodyFetched);
    }

    public function testCapturedEntryToArray(): void
    {
        $entry = new CapturedEntry('r2', 'GET', 'https://x.com', ['h' => 'v'], '', 'Document', microtime(true), true);
        $arr   = $entry->toArray();

        $this->assertSame('r2',       $arr['requestId']);
        $this->assertSame('GET',      $arr['method']);
        $this->assertTrue($arr['hasCredentials']);
        $this->assertArrayHasKey('responseBody',    $arr);
        $this->assertArrayHasKey('responseStatus',  $arr);
        $this->assertArrayHasKey('responseHeaders', $arr);
    }

    // ── NetworkCapture — filtrasi ─────────────────────────────────────────────

    public function testNoCaptureBeforeEnable(): void
    {
        $fresh = new NetworkCapture($this->cdp);
        // Sebelum enable(), onEvent belum didaftarkan jadi events tidak ada efek
        $this->assertEmpty($fresh->getAll());
    }

    public function testCapturesRequestAfterEnable(): void
    {
        $this->makeSent('r1', 'https://api.example.com/data');

        $all = $this->capture->getAll();
        $this->assertCount(1, $all);
        $this->assertSame('r1', $all[0]->requestId);
        $this->assertSame('GET', $all[0]->method);
    }

    public function testFilterDomainAcceptsMatch(): void
    {
        $this->capture->filterDomain('facebook.com');

        $this->makeSent('fb1', 'https://m.facebook.com/api/test');
        $this->makeSent('gl1', 'https://google.com/search');

        $all = $this->capture->getAll();
        $this->assertCount(1, $all);
        $this->assertSame('fb1', $all[0]->requestId);
    }

    public function testMultipleFilterDomainsOR(): void
    {
        $this->capture->filterDomain('facebook.com');
        $this->capture->filterDomain('instagram.com');

        $this->makeSent('fb', 'https://m.facebook.com/');
        $this->makeSent('ig', 'https://www.instagram.com/');
        $this->makeSent('tw', 'https://twitter.com/');

        $this->assertCount(2, $this->capture->getAll());
    }

    public function testNoFilterCapturesAll(): void
    {
        $this->makeSent('a', 'https://site-a.com/');
        $this->makeSent('b', 'https://site-b.com/');
        $this->makeSent('c', 'https://site-c.com/');

        $this->assertCount(3, $this->capture->getAll());
    }

    // ── Deteksi kredensial ────────────────────────────────────────────────────

    public function testCredentialDetectionInPostData(): void
    {
        $this->capture->scanCredentials(['email@example.com', 'pass=']);

        $this->makeSent('r1', 'https://api.example.com/login', 'POST', 'email=email@example.com&pass=secret');
        $this->makeSent('r2', 'https://api.example.com/data',  'GET',  '');

        $creds = $this->capture->getWithCredentials();
        $this->assertCount(1, $creds);
        $this->assertSame('r1', $creds[0]->requestId);
        $this->assertTrue($creds[0]->hasCredentials);
    }

    public function testNoCredentialKeywordsMeansNoDetection(): void
    {
        // Tidak ada scanCredentials() dipanggil
        $this->makeSent('r1', 'https://api.example.com/', 'POST', 'pass=secret');

        $this->assertCount(0, $this->capture->getWithCredentials());
    }

    // ── Response status ───────────────────────────────────────────────────────

    public function testResponseStatusCaptured(): void
    {
        $this->makeSent('r1', 'https://example.com/');
        $this->makeReceived('r1', 201);

        $all = $this->capture->getAll();
        $this->assertSame(201, $all[0]->responseStatus);
        $this->assertArrayHasKey('Content-Type', $all[0]->responseHeaders);
    }

    public function testResponseForUnknownRequestIdIgnored(): void
    {
        // responseReceived for unknown id must not crash
        $this->makeReceived('unknown-id', 200);
        $this->assertCount(0, $this->capture->getAll());
    }

    // ── getPosts / findByUrl ──────────────────────────────────────────────────

    public function testGetPosts(): void
    {
        $this->makeSent('g', 'https://x.com/', 'GET');
        $this->makeSent('p', 'https://x.com/api', 'POST', 'data=1');

        $posts = $this->capture->getPosts();
        $this->assertCount(1, $posts);
        $this->assertSame('POST', $posts[0]->method);
    }

    public function testFindByUrl(): void
    {
        $this->makeSent('a', 'https://example.com/api/users');
        $this->makeSent('b', 'https://example.com/api/orders');
        $this->makeSent('c', 'https://other.com/page');

        $results = $this->capture->findByUrl('/api/');
        $this->assertCount(2, $results);
    }

    // ── Summary ───────────────────────────────────────────────────────────────

    public function testSummary(): void
    {
        $this->capture->scanCredentials(['cred=']);

        $this->makeSent('r1', 'https://x.com/', 'GET');
        $this->makeSent('r2', 'https://x.com/api', 'POST', 'cred=123');
        $this->makeSent('r3', 'https://x.com/other', 'POST');

        $this->makeReceived('r1', 200);
        $this->makeReceived('r2', 200);
        $this->makeReceived('r3', 404);

        $s = $this->capture->summary();

        $this->assertSame(3, $s['total']);
        $this->assertSame(2, $s['posts']);
        $this->assertSame(1, $s['withCredentials']);
        $this->assertSame(2, $s['succeeded']);
        $this->assertSame(1, $s['failed']);
        $this->assertSame(0, $s['bodyFetched']);
    }

    // ── clear ─────────────────────────────────────────────────────────────────

    public function testClear(): void
    {
        $this->makeSent('r1', 'https://example.com/');
        $this->assertCount(1, $this->capture->getAll());

        $this->capture->clear();
        $this->assertCount(0, $this->capture->getAll());
    }

    // ── saveJson ──────────────────────────────────────────────────────────────

    public function testSaveJson(): void
    {
        $this->makeSent('r1', 'https://example.com/', 'GET');
        $this->makeReceived('r1', 200);

        $path = sys_get_temp_dir() . '/xbrowser_capture_test_' . uniqid() . '.json';

        try {
            $this->capture->saveJson($path);

            $this->assertFileExists($path);
            $data = json_decode(file_get_contents($path), true);

            $this->assertArrayHasKey('capturedAt', $data);
            $this->assertArrayHasKey('summary',    $data);
            $this->assertArrayHasKey('entries',    $data);
            $this->assertCount(1, $data['entries']);
            $this->assertSame('r1', $data['entries'][0]['requestId']);
        } finally {
            @unlink($path);
        }
    }

    public function testSaveJsonCreatesDirectory(): void
    {
        $this->makeSent('r1', 'https://example.com/', 'GET');

        $dir  = sys_get_temp_dir() . '/xbrowser_test_dir_' . uniqid();
        $path = $dir . '/output.json';

        try {
            $this->capture->saveJson($path);
            $this->assertFileExists($path);
        } finally {
            @unlink($path);
            @rmdir($dir);
        }
    }

    // ── enable() idempotent ───────────────────────────────────────────────────

    public function testEnableIsIdempotent(): void
    {
        // Panggil enable() dua kali — onEvent seharusnya tidak dobel
        $this->capture->enable();

        $this->makeSent('r1', 'https://example.com/');
        // Hanya 1 entry, bukan 2
        $this->assertCount(1, $this->capture->getAll());
    }
}
