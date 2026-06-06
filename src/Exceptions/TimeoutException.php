<?php

declare(strict_types=1);

namespace Xbrowser\Exceptions;

class TimeoutException extends XbrowserException
{
    public function __construct(string $operation = 'operation', int $timeoutMs = 30000, ?\Throwable $previous = null)
    {
        parent::__construct(
            "Timeout: {$operation} did not complete within {$timeoutMs}ms",
            0,
            $previous
        );
    }
}
