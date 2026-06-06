<?php

declare(strict_types=1);

namespace Xbrowser\Exceptions;

class SelectorNotFoundException extends XbrowserException
{
    public function __construct(string $selector, ?\Throwable $previous = null)
    {
        parent::__construct("Selector not found: '{$selector}'", 0, $previous);
    }
}
