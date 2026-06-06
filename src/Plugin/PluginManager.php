<?php

declare(strict_types=1);

namespace Xbrowser\Plugin;

use Xbrowser\Browser\Browser;
use Xbrowser\Exceptions\PluginException;
use Xbrowser\Utils\Logger;

class PluginManager
{
    private array $plugins = [];

    public function __construct(
        private readonly string $pluginDir,
        private readonly Logger $logger
    ) {}

    public function loadAll(): void
    {
        if (!is_dir($this->pluginDir)) {
            return;
        }

        foreach (glob($this->pluginDir . '/*/plugin.php') ?: [] as $file) {
            try {
                $this->loadFile($file);
            } catch (\Throwable $e) {
                $this->logger->error("Failed to load plugin from {$file}: " . $e->getMessage());
            }
        }
    }

    public function loadFile(string $file): void
    {
        if (!file_exists($file)) {
            throw new PluginException($file, 'File not found');
        }

        $plugin = require $file;

        if (!$plugin instanceof PluginInterface) {
            throw new PluginException($file, 'Must return a PluginInterface instance');
        }

        $this->register($plugin);
    }

    public function register(PluginInterface $plugin): void
    {
        $name = $plugin->getName();

        if (isset($this->plugins[$name])) {
            throw new PluginException($name, 'Plugin already registered');
        }

        $this->plugins[$name] = $plugin;
        $this->logger->debug("Plugin loaded: {$name} v{$plugin->getVersion()}");
    }

    public function activate(Browser $browser): void
    {
        foreach ($this->plugins as $plugin) {
            try {
                $plugin->register($browser);
                $this->logger->debug("Plugin activated: {$plugin->getName()}");
            } catch (\Throwable $e) {
                $this->logger->error("Plugin activation failed for {$plugin->getName()}: " . $e->getMessage());
            }
        }
    }

    public function getPlugins(): array
    {
        return $this->plugins;
    }

    public function getPlugin(string $name): ?PluginInterface
    {
        return $this->plugins[$name] ?? null;
    }

    public function getAllCommands(): array
    {
        $commands = [];
        foreach ($this->plugins as $plugin) {
            $commands = array_merge($commands, $plugin->getCommands());
        }
        return $commands;
    }

    public function count(): int
    {
        return count($this->plugins);
    }
}
