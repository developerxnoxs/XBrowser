<?php

declare(strict_types=1);

use Xbrowser\Browser\Browser;
use Xbrowser\Plugin\PluginInterface;

return new class implements PluginInterface
{
    public function getName(): string
    {
        return 'example-plugin';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getDescription(): string
    {
        return 'Example Xbrowser plugin demonstrating the plugin API';
    }

    public function register(Browser $browser): void
    {
        $dispatcher = $browser->getDispatcher();
        $logger     = $browser->getLogger();

        $dispatcher->on('page.loaded', function ($event) use ($logger): void {
            $payload = $event->getPayload();
            $logger->debug("[example-plugin] Page loaded: " . ($payload['url'] ?? ''));
        });

        $dispatcher->on('element.clicked', function ($event) use ($logger): void {
            $payload = $event->getPayload();
            $logger->debug("[example-plugin] Clicked: " . ($payload['selector'] ?? ''));
        });
    }

    public function getCommands(): array
    {
        return [
            'greet' => function (array $args): void {
                $name = $args[0] ?? 'World';
                echo "Hello from example-plugin, {$name}!\n";
            },
        ];
    }
};
