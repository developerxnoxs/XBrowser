<?php

declare(strict_types=1);

namespace Xbrowser\Tests\Unit\Utils;

use PHPUnit\Framework\TestCase;
use Xbrowser\Utils\SessionManager;

class SessionManagerTest extends TestCase
{
    private SessionManager $sessions;
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir   = sys_get_temp_dir() . '/xbrowser_sessions_' . uniqid();
        $this->sessions = new SessionManager($this->tmpDir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->tmpDir . '/*.json') ?: [] as $f) {
            unlink($f);
        }
        @rmdir($this->tmpDir);
    }

    public function testSaveAndLoad(): void
    {
        $data = [
            'url'            => 'https://example.com',
            'cookies'        => [['name' => 'token', 'value' => 'abc']],
            'localStorage'   => ['key' => 'value'],
            'sessionStorage' => [],
        ];

        $this->sessions->save('test', $data);
        $loaded = $this->sessions->load('test');

        $this->assertSame('https://example.com', $loaded['url']);
        $this->assertCount(1, $loaded['cookies']);
        $this->assertSame('abc', $loaded['cookies'][0]['value']);
    }

    public function testExistsReturnsTrueAfterSave(): void
    {
        $this->sessions->save('mysession', ['url' => '']);
        $this->assertTrue($this->sessions->exists('mysession'));
    }

    public function testExistsReturnsFalseForMissing(): void
    {
        $this->assertFalse($this->sessions->exists('nonexistent'));
    }

    public function testDeleteRemovesSession(): void
    {
        $this->sessions->save('todelete', ['url' => '']);
        $this->sessions->delete('todelete');
        $this->assertFalse($this->sessions->exists('todelete'));
    }

    public function testListReturnsSavedSessions(): void
    {
        $this->sessions->save('alpha', ['url' => '']);
        $this->sessions->save('beta', ['url' => '']);

        $list = $this->sessions->list();
        $this->assertContains('alpha', $list);
        $this->assertContains('beta', $list);
    }

    public function testLoadThrowsForMissingSession(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->sessions->load('missing_session');
    }

    public function testSpecialCharsInNameAreSanitized(): void
    {
        $this->sessions->save('my/../session!', ['url' => 'https://test.com']);
        $list = $this->sessions->list();
        $this->assertNotEmpty($list);
    }

    public function testSessionContainsVersion(): void
    {
        $this->sessions->save('versioned', ['url' => 'https://example.com']);
        $data = $this->sessions->load('versioned');
        $this->assertArrayHasKey('version', $data);
        $this->assertSame('1.0', $data['version']);
    }

    public function testSessionContainsSavedAt(): void
    {
        $this->sessions->save('dated', ['url' => '']);
        $data = $this->sessions->load('dated');
        $this->assertArrayHasKey('savedAt', $data);
    }
}
