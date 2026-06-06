<?php

declare(strict_types=1);

namespace Xbrowser\Tests\Unit\Renderer;

use PHPUnit\Framework\TestCase;
use Xbrowser\Renderer\HtmlParser;

class HtmlParserTest extends TestCase
{
    private function parse(string $html): array
    {
        return (new HtmlParser($html))->parse();
    }

    public function testParseSingleTag(): void
    {
        $nodes = $this->parse('<h1>Hello</h1>');
        $this->assertNotEmpty($nodes);
        $this->assertSame('h1', $nodes[0]['tag']);
    }

    public function testParseTextNode(): void
    {
        $nodes = $this->parse('Just text');
        $this->assertNotEmpty($nodes);
        $this->assertSame('text', $nodes[0]['type']);
        $this->assertSame('Just text', $nodes[0]['text']);
    }

    public function testParseNestedTags(): void
    {
        $nodes = $this->parse('<div><p>Hello</p></div>');
        $this->assertSame('div', $nodes[0]['tag']);
        $children = $nodes[0]['children'];
        $this->assertNotEmpty($children);
        $this->assertSame('p', $children[0]['tag']);
    }

    public function testParseAttributes(): void
    {
        $nodes = $this->parse('<a href="https://example.com" class="link">Click</a>');
        $this->assertSame('a', $nodes[0]['tag']);
        $this->assertSame('https://example.com', $nodes[0]['attrs']['href']);
        $this->assertSame('link', $nodes[0]['attrs']['class']);
    }

    public function testSelfClosingInput(): void
    {
        $nodes = $this->parse('<input type="text" placeholder="Enter text">');
        $this->assertSame('input', $nodes[0]['tag']);
        $this->assertSame('text', $nodes[0]['attrs']['type']);
    }

    public function testParseMultipleSiblings(): void
    {
        $nodes = $this->parse('<h1>Title</h1><p>Body</p>');
        $tags  = array_column(array_filter($nodes, fn($n) => $n['type'] === 'element'), 'tag');
        $this->assertContains('h1', $tags);
        $this->assertContains('p', $tags);
    }

    public function testIgnoresScriptTags(): void
    {
        $nodes = $this->parse('<div><script>var x = 1;</script><p>Content</p></div>');
        $children = $nodes[0]['children'];
        $scripts  = array_filter($children, fn($n) => isset($n['tag']) && $n['tag'] === 'script');
        foreach ($scripts as $script) {
            $this->assertEmpty($script['children']);
        }
    }

    public function testHtmlEntitiesDecoded(): void
    {
        $nodes = $this->parse('<p>&lt;Hello&gt; &amp; World</p>');
        $text  = '';
        foreach ($nodes[0]['children'] ?? [] as $child) {
            if ($child['type'] === 'text') $text .= $child['text'];
        }
        $this->assertStringContainsString('<Hello>', $text);
        $this->assertStringContainsString('& World', $text);
    }

    public function testParseTable(): void
    {
        $html  = '<table><tr><th>Name</th><th>Age</th></tr><tr><td>Alice</td><td>30</td></tr></table>';
        $nodes = $this->parse($html);
        $this->assertSame('table', $nodes[0]['tag']);
        $this->assertNotEmpty($nodes[0]['children']);
    }

    public function testSkipsComments(): void
    {
        $html  = '<!-- comment --><p>Text</p>';
        $nodes = (new HtmlParser($html))->parse();
        $elements = array_filter($nodes, fn($n) => $n['type'] === 'element');
        $this->assertCount(1, $elements);
    }
}
