<?php

declare(strict_types=1);

namespace Xbrowser\Events;

class DomUpdatedEvent extends AbstractEvent
{
    public function __construct(
        private readonly string $nodeId = '',
        private readonly string $changeType = 'modified'
    ) {
        parent::__construct();
    }

    public function getName(): string
    {
        return 'dom.updated';
    }

    public function getPayload(): array
    {
        return [
            'nodeId'     => $this->nodeId,
            'changeType' => $this->changeType,
        ];
    }
}
