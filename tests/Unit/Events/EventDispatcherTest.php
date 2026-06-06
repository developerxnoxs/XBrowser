<?php

declare(strict_types=1);

namespace Xbrowser\Tests\Unit\Events;

use PHPUnit\Framework\TestCase;
use Xbrowser\Events\ClickEvent;
use Xbrowser\Events\EventDispatcher;
use Xbrowser\Events\PageLoadedEvent;
use Xbrowser\Events\NavigationEvent;
use Xbrowser\Events\JavaScriptExecutedEvent;
use Xbrowser\Events\DomUpdatedEvent;

class EventDispatcherTest extends TestCase
{
    private EventDispatcher $dispatcher;

    protected function setUp(): void
    {
        $this->dispatcher = new EventDispatcher();
    }

    public function testOnAndEmit(): void
    {
        $received = null;
        $this->dispatcher->on('page.loaded', function ($event) use (&$received) {
            $received = $event;
        });

        $event = new PageLoadedEvent('https://example.com', 'Example', 200);
        $this->dispatcher->emit($event);

        $this->assertSame($event, $received);
    }

    public function testOnceFiresOnlyOnce(): void
    {
        $count = 0;
        $this->dispatcher->once('element.clicked', function () use (&$count) {
            $count++;
        });

        $this->dispatcher->emit(new ClickEvent('#btn'));
        $this->dispatcher->emit(new ClickEvent('#btn'));

        $this->assertSame(1, $count);
    }

    public function testOffRemovesListener(): void
    {
        $count = 0;
        $listener = function () use (&$count) { $count++; };

        $this->dispatcher->on('page.loaded', $listener);
        $this->dispatcher->off('page.loaded', $listener);
        $this->dispatcher->emit(new PageLoadedEvent('https://example.com'));

        $this->assertSame(0, $count);
    }

    public function testWildcardListenerReceivesAllEvents(): void
    {
        $received = [];
        $this->dispatcher->on('*', function ($event) use (&$received) {
            $received[] = $event->getName();
        });

        $this->dispatcher->emit(new PageLoadedEvent('https://a.com'));
        $this->dispatcher->emit(new ClickEvent('#x'));

        $this->assertContains('page.loaded', $received);
        $this->assertContains('element.clicked', $received);
    }

    public function testMultipleListenersForSameEvent(): void
    {
        $results = [];
        $this->dispatcher->on('page.loaded', function () use (&$results) { $results[] = 'A'; });
        $this->dispatcher->on('page.loaded', function () use (&$results) { $results[] = 'B'; });

        $this->dispatcher->emit(new PageLoadedEvent('https://x.com'));

        $this->assertSame(['A', 'B'], $results);
    }

    public function testRemoveAllForEvent(): void
    {
        $count = 0;
        $this->dispatcher->on('page.loaded', fn() => $count++);
        $this->dispatcher->on('page.loaded', fn() => $count++);
        $this->dispatcher->removeAll('page.loaded');
        $this->dispatcher->emit(new PageLoadedEvent('https://x.com'));

        $this->assertSame(0, $count);
    }

    public function testRemoveAllRemovesEveryEvent(): void
    {
        $count = 0;
        $this->dispatcher->on('page.loaded', fn() => $count++);
        $this->dispatcher->on('element.clicked', fn() => $count++);
        $this->dispatcher->removeAll();

        $this->dispatcher->emit(new PageLoadedEvent('https://x.com'));
        $this->dispatcher->emit(new ClickEvent('#x'));

        $this->assertSame(0, $count);
    }

    public function testNavigationEventPayload(): void
    {
        $event = new NavigationEvent('https://from.com', 'https://to.com', 'click');
        $this->assertSame('https://to.com', $event->getToUrl());
        $this->assertSame('page.navigation', $event->getName());
        $this->assertArrayHasKey('fromUrl', $event->getPayload());
    }

    public function testClickEventPayload(): void
    {
        $event = new ClickEvent('#login', 10.5, 20.3);
        $this->assertSame('#login', $event->getSelector());
        $payload = $event->getPayload();
        $this->assertSame(10.5, $payload['x']);
        $this->assertSame(20.3, $payload['y']);
    }

    public function testJsEventPayload(): void
    {
        $event = new JavaScriptExecutedEvent('document.title', 'My Title', false);
        $this->assertFalse($event->hasError());
        $this->assertSame('My Title', $event->getResult());
    }
}
