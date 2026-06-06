<?php

declare(strict_types=1);

namespace Xbrowser\Exceptions;

class JavaScriptException extends XbrowserException
{
    public function __construct(string $message, string $jsError = '', ?\Throwable $previous = null)
    {
        $full = $jsError ? "{$message}: {$jsError}" : $message;
        parent::__construct("JavaScript error: {$full}", 0, $previous);
    }
}
