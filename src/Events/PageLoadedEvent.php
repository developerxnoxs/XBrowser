<?php

declare(strict_types=1);

namespace Xbrowser\Events;

class PageLoadedEvent extends AbstractEvent
{
    public function __construct(
        private readonly string $url,
        private readonly string $title = '',
        private readonly int $statusCode = 200
    ) {
        parent::__construct();
    }

    public function getName(): string
    {
        return 'page.loaded';
    }

    public function getPayload(): array
    {
        return [
            'url'        => $this->url,
            'title'      => $this->title,
            'statusCode' => $this->statusCode,
        ];
    }

    public function getUrl(): string    { return $this->url; }
    public function getTitle(): string  { return $this->title; }
    public function getStatusCode(): int { return $this->statusCode; }
}
