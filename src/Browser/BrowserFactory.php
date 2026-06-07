<?php

declare(strict_types=1);

namespace Xbrowser\Browser;

use Xbrowser\Events\EventDispatcher;
use Xbrowser\Plugin\PluginManager;
use Xbrowser\Utils\ConfigManager;
use Xbrowser\Utils\Logger;

class BrowserFactory
{
    public static function create(array $options = []): Browser
    {
        $config     = new ConfigManager($options['configFile'] ?? '');
        $verbose    = (bool) ($options['verbose'] ?? $config->get('verbose', false));
        $silent     = (bool) ($options['silent'] ?? $config->get('silent', false));
        $logFile    = (string) ($options['logFile'] ?? $config->get('log_file', ''));
        $logger     = new Logger($verbose, $logFile);
        if ($silent) {
            $logger->setSilent(true);
        }
        $dispatcher = new EventDispatcher();

        $pluginDir  = $options['pluginDir'] ?? $config->get('plugin_dir', '');
        if (!$pluginDir) {
            $pluginDir = dirname(__DIR__, 2) . '/plugins';
        }

        $plugins = new PluginManager($pluginDir, $logger);
        $plugins->loadAll();

        $browser = new Browser($config, $logger, $dispatcher, $plugins);

        if (!empty($options['launch'])) {
            $browser->launch($options);
        }

        return $browser;
    }
}
