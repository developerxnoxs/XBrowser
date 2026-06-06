<?php

declare(strict_types=1);

namespace Xbrowser\DOM;

class Element
{
    private array $children = [];
    private array $attributes = [];

    public function __construct(
        private readonly int    $nodeId,
        private readonly string $tagName,
        private string          $textContent = '',
        private readonly string $outerHtml = ''
    ) {}

    public function getNodeId(): int       { return $this->nodeId; }
    public function getTagName(): string   { return strtolower($this->tagName); }
    public function getText(): string      { return $this->textContent; }
    public function getOuterHtml(): string { return $this->outerHtml; }

    public function setAttribute(string $name, string $value): void
    {
        $this->attributes[$name] = $value;
    }

    public function getAttribute(string $name): ?string
    {
        return $this->attributes[$name] ?? null;
    }

    public function hasAttribute(string $name): bool
    {
        return isset($this->attributes[$name]);
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function addChild(self $child): void
    {
        $this->children[] = $child;
    }

    public function getChildren(): array
    {
        return $this->children;
    }

    public function toArray(): array
    {
        return [
            'nodeId'      => $this->nodeId,
            'tagName'     => $this->tagName,
            'text'        => $this->textContent,
            'attributes'  => $this->attributes,
            'children'    => array_map(fn(self $c) => $c->toArray(), $this->children),
        ];
    }

    public static function fromCdpNode(array $node): self
    {
        $tagName = $node['nodeName'] ?? 'unknown';
        $nodeId  = $node['nodeId'] ?? 0;
        $text    = '';

        foreach ($node['children'] ?? [] as $child) {
            if (($child['nodeType'] ?? 0) === 3) {
                $text .= $child['nodeValue'] ?? '';
            }
        }

        $el = new self($nodeId, $tagName, trim($text));

        $attrs = $node['attributes'] ?? [];
        for ($i = 0; $i < count($attrs) - 1; $i += 2) {
            $el->setAttribute($attrs[$i], $attrs[$i + 1]);
        }

        foreach ($node['children'] ?? [] as $child) {
            $nodeType = $child['nodeType'] ?? 0;
            if ($nodeType === 1) {
                $el->addChild(self::fromCdpNode($child));
            }
        }

        return $el;
    }
}
