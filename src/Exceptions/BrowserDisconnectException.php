<?php

declare(strict_types=1);

namespace Xbrowser\Exceptions;

class BrowserDisconnectException extends XbrowserException
{
    public function __construct(string $reason = 'Connection lost', ?\Throwable $previous = null)
    {
        parent::__construct("Browser disconnected: {$reason}", 0, $previous);
    }
}
