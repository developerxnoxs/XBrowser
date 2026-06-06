<?php

declare(strict_types=1);

namespace Xbrowser\Events;

class JavaScriptExecutedEvent extends AbstractEvent
{
    public function __construct(
        private readonly string $expression,
        private readonly mixed $result = null,
        private readonly bool $hasError = false
    ) {
        parent::__construct();
    }

    public function getName(): string
    {
        return 'javascript.executed';
    }

    public function getPayload(): array
    {
        return [
            'expression' => $this->expression,
            'result'     => $this->result,
            'hasError'   => $this->hasError,
        ];
    }

    public function getResult(): mixed { return $this->result; }
    public function hasError(): bool    { return $this->hasError; }
}
