<?php

declare(strict_types=1);

namespace Xbrowser\Tests\Unit\Utils;

use PHPUnit\Framework\TestCase;
use Xbrowser\Exceptions\InvalidUrlException;
use Xbrowser\Utils\UrlValidator;

class UrlValidatorTest extends TestCase
{
    public function testValidHttpUrl(): void
    {
        $url = UrlValidator::validate('https://example.com');
        $this->assertSame('https://example.com', $url);
    }

    public function testAddsHttpsSchemeWhenMissing(): void
    {
        $url = UrlValidator::validate('example.com');
        $this->assertSame('https://example.com', $url);
    }

    public function testHttpSchemeIsAllowed(): void
    {
        $url = UrlValidator::validate('http://example.com/path');
        $this->assertSame('http://example.com/path', $url);
    }

    public function testFileSchemeIsAllowed(): void
    {
        $url = UrlValidator::validate('file:///etc/hosts');
        $this->assertSame('file:///etc/hosts', $url);
    }

    public function testAboutBlankIsAllowed(): void
    {
        $url = UrlValidator::validate('about:blank');
        $this->assertSame('about:blank', $url);
    }

    public function testInvalidUrlThrows(): void
    {
        $this->expectException(InvalidUrlException::class);
        UrlValidator::validate('ftp://invalid-scheme.com');
    }

    public function testIsValidReturnsTrueForGoodUrl(): void
    {
        $this->assertTrue(UrlValidator::isValid('https://google.com'));
    }

    public function testIsValidReturnsFalseForBadUrl(): void
    {
        $this->assertFalse(UrlValidator::isValid('ftp://not-allowed'));
    }

    public function testUrlWithQueryString(): void
    {
        $url = UrlValidator::validate('https://example.com/search?q=hello&lang=en');
        $this->assertSame('https://example.com/search?q=hello&lang=en', $url);
    }

    public function testUrlWithFragment(): void
    {
        $url = UrlValidator::validate('https://example.com/page#section');
        $this->assertSame('https://example.com/page#section', $url);
    }
}
