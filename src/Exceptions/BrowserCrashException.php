<?php

declare(strict_types=1);

namespace Xbrowser\Exceptions;

class BrowserCrashException extends XbrowserException
{
    public function __construct(string $reason = 'Unknown reason', ?\Throwable $previous = null)
    {
        parent::__construct("Browser crashed: {$reason}", 0, $previous);
    }
}
