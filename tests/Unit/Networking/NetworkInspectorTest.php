<?php

declare(strict_types=1);

namespace Xbrowser\Tests\Unit\Networking;

use PHPUnit\Framework\TestCase;
use Xbrowser\Networking\NetworkRequest;
use Xbrowser\Networking\NetworkResponse;

class NetworkInspectorTest extends TestCase
{
    public function testNetworkRequestCreation(): void
    {
        $req = new NetworkRequest('req-1', 'https://api.example.com/data', 'GET', ['Accept' => 'application/json']);
        $this->assertSame('req-1', $req->requestId);
        $this->assertSame('https://api.example.com/data', $req->url);
        $this->assertSame('GET', $req->method);
        $this->assertArrayHasKey('Accept', $req->headers);
    }

    public function testNetworkRequestToArray(): void
    {
        $req = new NetworkRequest('req-2', 'https://example.com', 'POST', [], '{"key":"value"}', 'XHR');
        $arr = $req->toArray();

        $this->assertSame('req-2', $arr['requestId']);
        $this->assertSame('POST', $arr['method']);
        $this->assertSame('{"key":"value"}', $arr['postData']);
        $this->assertSame('XHR', $arr['resourceType']);
        $this->assertIsFloat($arr['timestamp']);
    }

    public function testNetworkResponseIsSuccess(): void
    {
        $ok  = new NetworkResponse('r1', 'https://x.com', 200, 'OK', []);
        $err = new NetworkResponse('r2', 'https://x.com', 404, 'Not Found', []);
        $srv = new NetworkResponse('r3', 'https://x.com', 500, 'Server Error', []);

        $this->assertTrue($ok->isSuccess());
        $this->assertFalse($err->isSuccess());
        $this->assertFalse($srv->isSuccess());
    }

    public function testNetworkResponseToArray(): void
    {
        $resp = new NetworkResponse('r1', 'https://x.com', 201, 'Created', ['Content-Type' => 'application/json']);
        $arr  = $resp->toArray();

        $this->assertSame(201, $arr['statusCode']);
        $this->assertSame('Created', $arr['statusText']);
        $this->assertArrayHasKey('Content-Type', $arr['headers']);
    }

    public function testRequestHasTimestamp(): void
    {
        $req = new NetworkRequest('r', 'https://x.com', 'GET', []);
        $this->assertIsFloat($req->timestamp);
        $this->assertGreaterThan(0, $req->timestamp);
    }

    public function testResponseHasTimestamp(): void
    {
        $resp = new NetworkResponse('r', 'https://x.com', 200, 'OK', []);
        $this->assertIsFloat($resp->timestamp);
    }

    public function testResponseRedirectIsNotSuccess(): void
    {
        $resp = new NetworkResponse('r', 'https://x.com', 301, 'Moved Permanently', []);
        $this->assertFalse($resp->isSuccess());
    }

    public function testResponse2xxIsSuccess(): void
    {
        foreach ([200, 201, 204, 206] as $code) {
            $resp = new NetworkResponse('r', 'https://x.com', $code, 'Success', []);
            $this->assertTrue($resp->isSuccess(), "Expected {$code} to be success");
        }
    }
}
