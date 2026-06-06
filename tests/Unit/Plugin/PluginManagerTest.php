<?php

declare(strict_types=1);

namespace Xbrowser\Tests\Unit\Plugin;

use PHPUnit\Framework\TestCase;
use Xbrowser\Browser\Browser;
use Xbrowser\Events\EventDispatcher;
use Xbrowser\Exceptions\PluginException;
use Xbrowser\Plugin\PluginInterface;
use Xbrowser\Plugin\PluginManager;
use Xbrowser\Utils\ConfigManager;
use Xbrowser\Utils\Logger;

class PluginManagerTest extends TestCase
{
    private PluginManager $manager;
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir  = sys_get_temp_dir() . '/xbrowser_plugins_' . uniqid();
        mkdir($this->tmpDir, 0755, true);
        $this->manager = new PluginManager($this->tmpDir, new Logger());
    }

    protected function tearDown(): void
    {
        $this->rmdirRecursive($this->tmpDir);
    }

    public function testRegisterPlugin(): void
    {
        $plugin = $this->makePlugin('test-plugin');
        $this->manager->register($plugin);

        $this->assertSame(1, $this->manager->count());
        $this->assertSame($plugin, $this->manager->getPlugin('test-plugin'));
    }

    public function testRegisterDuplicateThrows(): void
    {
        $this->expectException(PluginException::class);
        $plugin = $this->makePlugin('dup-plugin');
        $this->manager->register($plugin);
        $this->manager->register($plugin);
    }

    public function testGetPlugins(): void
    {
        $this->manager->register($this->makePlugin('alpha'));
        $this->manager->register($this->makePlugin('beta'));

        $plugins = $this->manager->getPlugins();
        $this->assertCount(2, $plugins);
        $this->assertArrayHasKey('alpha', $plugins);
        $this->assertArrayHasKey('beta', $plugins);
    }

    public function testGetNonExistentPlugin(): void
    {
        $this->assertNull($this->manager->getPlugin('missing'));
    }

    public function testLoadAllFromEmptyDir(): void
    {
        $this->manager->loadAll();
        $this->assertSame(0, $this->manager->count());
    }

    public function testLoadAllIgnoresBadFiles(): void
    {
        @mkdir($this->tmpDir . '/bad-plugin', 0755, true);
        file_put_contents($this->tmpDir . '/bad-plugin/plugin.php', '<?php return null;');

        $this->manager->loadAll();
        $this->assertSame(0, $this->manager->count());
    }

    public function testGetAllCommandsEmpty(): void
    {
        $this->assertSame([], $this->manager->getAllCommands());
    }

    public function testGetAllCommandsFromPlugins(): void
    {
        $plugin = $this->makePlugin('cmd-plugin', ['my-command' => fn() => 'hi']);
        $this->manager->register($plugin);

        $commands = $this->manager->getAllCommands();
        $this->assertArrayHasKey('my-command', $commands);
    }

    private function makePlugin(string $name, array $commands = []): PluginInterface
    {
        return new class($name, $commands) implements PluginInterface {
            public function __construct(
                private readonly string $name,
                private readonly array $cmds
            ) {}
            public function getName(): string    { return $this->name; }
            public function getVersion(): string { return '1.0.0'; }
            public function getDescription(): string { return 'Test plugin'; }
            public function register(Browser $browser): void {}
            public function getCommands(): array { return $this->cmds; }
        };
    }

    private function rmdirRecursive(string $dir): void
    {
        foreach (glob($dir . '/*') ?: [] as $item) {
            is_dir($item) ? $this->rmdirRecursive($item) : unlink($item);
        }
        @rmdir($dir);
    }
}
