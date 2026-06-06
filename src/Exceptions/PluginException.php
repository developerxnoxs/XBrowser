<?php

declare(strict_types=1);

namespace Xbrowser\Exceptions;

class PluginException extends XbrowserException
{
    public function __construct(string $plugin, string $reason, ?\Throwable $previous = null)
    {
        parent::__construct("Plugin '{$plugin}' error: {$reason}", 0, $previous);
    }
}
