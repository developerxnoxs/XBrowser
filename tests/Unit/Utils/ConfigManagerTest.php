<?php

declare(strict_types=1);

namespace Xbrowser\Tests\Unit\Utils;

use PHPUnit\Framework\TestCase;
use Xbrowser\Utils\ConfigManager;

class ConfigManagerTest extends TestCase
{
    public function testDefaultValues(): void
    {
        $config = new ConfigManager();
        $this->assertSame(9222, $config->get('remote_debugging_port'));
        $this->assertSame(30000, $config->get('timeout'));
        $this->assertTrue($config->get('headless'));
        $this->assertTrue($config->get('disable_gpu'));
    }

    public function testGetWithFallback(): void
    {
        $config = new ConfigManager();
        $result = $config->get('nonexistent_key', 'fallback');
        $this->assertSame('fallback', $result);
    }

    public function testSetOverridesDefault(): void
    {
        $config = new ConfigManager();
        $config->set('timeout', 60000);
        $this->assertSame(60000, $config->get('timeout'));
    }

    public function testAllReturnsArray(): void
    {
        $config = new ConfigManager();
        $all    = $config->all();
        $this->assertIsArray($all);
        $this->assertArrayHasKey('remote_debugging_port', $all);
        $this->assertArrayHasKey('timeout', $all);
        $this->assertArrayHasKey('headless', $all);
    }

    public function testSetAndGetCustomKey(): void
    {
        $config = new ConfigManager();
        $config->set('my_custom_setting', 'hello');
        $this->assertSame('hello', $config->get('my_custom_setting'));
    }

    public function testGetReturnsNullForMissingKey(): void
    {
        $config = new ConfigManager();
        $this->assertNull($config->get('totally_missing'));
    }

    public function testLoadFromJsonFile(): void
    {
        $file = sys_get_temp_dir() . '/xbrowser_config_test_' . uniqid() . '.json';
        file_put_contents($file, json_encode(['timeout' => 99999, 'headless' => false]));

        $config = new ConfigManager($file);
        $this->assertSame(99999, $config->get('timeout'));
        $this->assertFalse($config->get('headless'));

        unlink($file);
    }

    public function testNonExistentFileIsIgnored(): void
    {
        $config = new ConfigManager('/tmp/this_file_does_not_exist_xbrowser.json');
        $this->assertSame(9222, $config->get('remote_debugging_port'));
    }
}
