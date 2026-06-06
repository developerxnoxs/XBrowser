<?php

declare(strict_types=1);

namespace Xbrowser\Plugin;

use Xbrowser\Browser\Browser;

interface PluginInterface
{
    public function getName(): string;
    public function getVersion(): string;
    public function getDescription(): string;
    public function register(Browser $browser): void;
    public function getCommands(): array;
}
