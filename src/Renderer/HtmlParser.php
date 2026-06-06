<?php

declare(strict_types=1);

namespace Xbrowser\Renderer;

class HtmlParser
{
    private string $html;
    private int $pos = 0;

    public function __construct(string $html)
    {
        $this->html = $html;
    }

    public function parse(): array
    {
        $this->pos = 0;
        return $this->parseNodes();
    }

    private function parseNodes(): array
    {
        $nodes = [];
        while ($this->pos < strlen($this->html)) {
            $node = $this->parseNode();
            if ($node !== null) {
                $nodes[] = $node;
            }
        }
        return $nodes;
    }

    private function parseNode(): ?array
    {
        $this->skipWhitespace();

        if ($this->pos >= strlen($this->html)) {
            return null;
        }

        if ($this->html[$this->pos] === '<') {
            return $this->parseTag();
        }

        return $this->parseText();
    }

    private function parseTag(): ?array
    {
        $this->pos++;

        if ($this->pos >= strlen($this->html)) {
            return null;
        }

        if ($this->html[$this->pos] === '!') {
            $this->skipTo('>');
            $this->pos++;
            return null;
        }

        $closing = false;
        if ($this->html[$this->pos] === '/') {
            $closing = true;
            $this->pos++;
        }

        $tagName = $this->readWord();
        if ($tagName === '') {
            $this->skipTo('>');
            $this->pos++;
            return null;
        }

        $tagName = strtolower($tagName);
        $attrs   = [];

        $this->skipWhitespace();
        while ($this->pos < strlen($this->html) && $this->html[$this->pos] !== '>' && $this->html[$this->pos] !== '/') {
            $attr = $this->parseAttribute();
            if ($attr) {
                $attrs[$attr[0]] = $attr[1];
            }
            $this->skipWhitespace();
        }

        $selfClosing = false;
        if ($this->pos < strlen($this->html) && $this->html[$this->pos] === '/') {
            $selfClosing = true;
            $this->pos++;
        }

        if ($this->pos < strlen($this->html) && $this->html[$this->pos] === '>') {
            $this->pos++;
        }

        if ($closing) {
            return ['type' => 'close', 'tag' => $tagName];
        }

        $children = [];
        $selfClosingTags = ['br', 'hr', 'img', 'input', 'meta', 'link', 'area', 'base', 'col', 'embed', 'param', 'source', 'track', 'wbr'];

        if (!$selfClosing && !in_array($tagName, $selfClosingTags, true)) {
            if (in_array($tagName, ['script', 'style'], true)) {
                $content = $this->readUntilClose($tagName);
                if ($tagName === 'script') {
                    return ['type' => 'element', 'tag' => $tagName, 'attrs' => $attrs, 'children' => [], 'text' => ''];
                }
                return ['type' => 'element', 'tag' => $tagName, 'attrs' => $attrs, 'children' => [], 'text' => ''];
            }

            $children = $this->parseChildren($tagName);
        }

        return [
            'type'     => 'element',
            'tag'      => $tagName,
            'attrs'    => $attrs,
            'children' => $children,
            'text'     => '',
        ];
    }

    private function parseChildren(string $parentTag): array
    {
        $children = [];
        $depth    = 1;

        while ($this->pos < strlen($this->html) && $depth > 0) {
            $this->skipWhitespace();
            if ($this->pos >= strlen($this->html)) break;

            if ($this->html[$this->pos] === '<') {
                $saved = $this->pos;
                $this->pos++;
                $this->skipWhitespace();

                if ($this->pos < strlen($this->html) && $this->html[$this->pos] === '/') {
                    $this->pos++;
                    $tag = $this->readWord();
                    $this->skipTo('>');
                    $this->pos++;
                    if (strtolower($tag) === $parentTag) {
                        $depth--;
                        break;
                    }
                    continue;
                }

                $this->pos = $saved;
                $node = $this->parseNode();
                if ($node) {
                    $children[] = $node;
                }
            } else {
                $text = $this->parseText();
                if ($text && trim($text['text']) !== '') {
                    $children[] = $text;
                }
            }
        }

        return $children;
    }

    private function parseAttribute(): ?array
    {
        $name = $this->readWord();
        if ($name === '') {
            $this->pos++;
            return null;
        }

        $this->skipWhitespace();
        if ($this->pos >= strlen($this->html) || $this->html[$this->pos] !== '=') {
            return [$name, $name];
        }

        $this->pos++;
        $this->skipWhitespace();

        $value = '';
        if ($this->pos < strlen($this->html) && in_array($this->html[$this->pos], ['"', "'"], true)) {
            $quote = $this->html[$this->pos++];
            $start = $this->pos;
            while ($this->pos < strlen($this->html) && $this->html[$this->pos] !== $quote) {
                $this->pos++;
            }
            $value = substr($this->html, $start, $this->pos - $start);
            if ($this->pos < strlen($this->html)) {
                $this->pos++;
            }
        } else {
            $start = $this->pos;
            while ($this->pos < strlen($this->html) && !ctype_space($this->html[$this->pos]) && $this->html[$this->pos] !== '>' && $this->html[$this->pos] !== '/') {
                $this->pos++;
            }
            $value = substr($this->html, $start, $this->pos - $start);
        }

        return [$name, $value];
    }

    private function parseText(): array
    {
        $start = $this->pos;
        while ($this->pos < strlen($this->html) && $this->html[$this->pos] !== '<') {
            $this->pos++;
        }
        $text = substr($this->html, $start, $this->pos - $start);
        return ['type' => 'text', 'text' => html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8')];
    }

    private function readWord(): string
    {
        $start = $this->pos;
        while ($this->pos < strlen($this->html) && (ctype_alnum($this->html[$this->pos]) || $this->html[$this->pos] === '-' || $this->html[$this->pos] === '_' || $this->html[$this->pos] === ':')) {
            $this->pos++;
        }
        return substr($this->html, $start, $this->pos - $start);
    }

    private function skipWhitespace(): void
    {
        while ($this->pos < strlen($this->html) && ctype_space($this->html[$this->pos])) {
            $this->pos++;
        }
    }

    private function skipTo(string $char): void
    {
        while ($this->pos < strlen($this->html) && $this->html[$this->pos] !== $char) {
            $this->pos++;
        }
    }

    private function readUntilClose(string $tag): string
    {
        $pattern = '</' . $tag;
        $start   = $this->pos;
        $lower   = strtolower($this->html);
        $found   = strpos($lower, $pattern, $this->pos);

        if ($found === false) {
            $content  = substr($this->html, $start);
            $this->pos = strlen($this->html);
            return $content;
        }

        $content  = substr($this->html, $start, $found - $start);
        $this->pos = $found;
        $this->skipTo('>');
        $this->pos++;
        return $content;
    }
}
