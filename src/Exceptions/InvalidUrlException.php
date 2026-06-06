<?php

declare(strict_types=1);

namespace Xbrowser\Exceptions;

class InvalidUrlException extends XbrowserException
{
    public function __construct(string $url, ?\Throwable $previous = null)
    {
        parent::__construct("Invalid URL: '{$url}'", 0, $previous);
    }
}
