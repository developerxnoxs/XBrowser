<?php

declare(strict_types=1);

namespace Xbrowser\DOM;

use Xbrowser\CDP\Client;
use Xbrowser\CDP\Protocol;
use Xbrowser\Exceptions\SelectorNotFoundException;

class DOMManager
{
    private ?array $documentNode = null;
    private int $rootNodeId = 0;

    public function __construct(private readonly Client $cdp) {}

    public function refresh(): void
    {
        $result = $this->cdp->send(Protocol::getDocument());
        $this->documentNode = $result['root'] ?? null;
        $this->rootNodeId   = $this->documentNode['nodeId'] ?? 0;
    }

    public function getRootNodeId(): int
    {
        if ($this->rootNodeId === 0) {
            $this->refresh();
        }
        return $this->rootNodeId;
    }

    public function querySelector(string $selector): Element
    {
        $nodeId = $this->getRootNodeId();
        $result = $this->cdp->send(Protocol::querySelector($nodeId, $selector));

        if (empty($result['nodeId']) || $result['nodeId'] === 0) {
            throw new SelectorNotFoundException($selector);
        }

        return $this->buildElement($result['nodeId'], $selector);
    }

    public function querySelectorAll(string $selector): array
    {
        $nodeId  = $this->getRootNodeId();
        $result  = $this->cdp->send(Protocol::querySelectorAll($nodeId, $selector));
        $nodeIds = $result['nodeIds'] ?? [];

        return array_map(fn(int $id) => $this->buildElement($id, $selector), $nodeIds);
    }

    public function getOuterHTML(?int $nodeId = null): string
    {
        $id     = $nodeId ?? $this->getRootNodeId();
        $result = $this->cdp->send(Protocol::getOuterHTML($id));
        return $result['outerHTML'] ?? '';
    }

    public function getBoxModel(int $nodeId): array
    {
        $result = $this->cdp->send(Protocol::getBoxModel($nodeId));
        return $result['model'] ?? [];
    }

    public function getElementCenter(int $nodeId): array
    {
        $model = $this->getBoxModel($nodeId);
        if (empty($model['content'])) {
            return ['x' => 0, 'y' => 0];
        }

        $content = $model['content'];
        $x = ($content[0] + $content[2] + $content[4] + $content[6]) / 4;
        $y = ($content[1] + $content[3] + $content[5] + $content[7]) / 4;

        return ['x' => $x, 'y' => $y];
    }

    public function focusElement(int $nodeId): void
    {
        $this->cdp->send(Protocol::focusNode($nodeId));
    }

    private function buildElement(int $nodeId, string $selector): Element
    {
        $html = '';
        try {
            $html = $this->getOuterHTML($nodeId);
        } catch (\Exception) {}

        $el = new Element($nodeId, $this->guessTagName($html), '', $html);
        return $el;
    }

    private function guessTagName(string $html): string
    {
        if (preg_match('/<([a-zA-Z][a-zA-Z0-9]*)/', $html, $m)) {
            return $m[1];
        }
        return 'element';
    }
}
