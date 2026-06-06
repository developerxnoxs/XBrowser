<?php

declare(strict_types=1);

namespace Xbrowser\Tests\Unit\Renderer;

use PHPUnit\Framework\TestCase;
use Xbrowser\Renderer\TerminalRenderer;

class TerminalRendererTest extends TestCase
{
    private TerminalRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new TerminalRenderer(termWidth: 80, useColor: false);
    }

    private function renderStrip(string $html): string
    {
        return strip_tags($this->renderer->render($html));
    }

    public function testH1RendersWithHash(): void
    {
        $output = $this->renderStrip('<h1>Hello World</h1>');
        $this->assertStringContainsString('# HELLO WORLD', $output);
    }

    public function testH2RendersWithDoubleHash(): void
    {
        $output = $this->renderStrip('<h2>Section</h2>');
        $this->assertStringContainsString('## SECTION', $output);
    }

    public function testParagraphRendered(): void
    {
        $output = $this->renderStrip('<p>This is a paragraph.</p>');
        $this->assertStringContainsString('This is a paragraph.', $output);
    }

    public function testLinkRenderedWithBrackets(): void
    {
        $output = $this->renderer->render('<a href="https://example.com">Click here</a>');
        $clean  = strip_tags($output);
        $this->assertStringContainsString('[Click here]', $clean);
    }

    public function testButtonRenderedWithBrackets(): void
    {
        $output = $this->renderer->render('<button>Login</button>');
        $clean  = strip_tags($output);
        $this->assertStringContainsString('[ Login ]', $clean);
    }

    public function testInputRenderedWithUnderscores(): void
    {
        $output = $this->renderer->render('<input type="text" placeholder="Enter name">');
        $clean  = strip_tags($output);
        $this->assertStringContainsString('Enter name', $clean);
    }

    public function testCheckboxRendered(): void
    {
        $output = $this->renderer->render('<input type="checkbox">');
        $clean  = strip_tags($output);
        $this->assertStringContainsString('[ ]', $clean);
    }

    public function testImageRenderedWithAlt(): void
    {
        $output = $this->renderer->render('<img src="photo.jpg" alt="My Photo">');
        $clean  = strip_tags($output);
        $this->assertStringContainsString('[IMAGE: My Photo]', $clean);
    }

    public function testUnorderedListRendered(): void
    {
        $output = $this->renderStrip('<ul><li>Item One</li><li>Item Two</li></ul>');
        $this->assertStringContainsString('Item One', $output);
        $this->assertStringContainsString('Item Two', $output);
    }

    public function testTableRenderedAsAscii(): void
    {
        $html   = '<table><tr><th>Name</th><th>Age</th></tr><tr><td>Alice</td><td>30</td></tr></table>';
        $output = $this->renderStrip($html);
        $this->assertStringContainsString('Name', $output);
        $this->assertStringContainsString('Alice', $output);
        $this->assertStringContainsString('30', $output);
        $this->assertStringContainsString('─', $output);
    }

    public function testHrRenderedAsLine(): void
    {
        $output = $this->renderStrip('<hr>');
        $this->assertStringContainsString('─', $output);
    }

    public function testScriptTagIgnored(): void
    {
        $output = $this->renderStrip('<p>Hello</p><script>alert("xss")</script>');
        $this->assertStringNotContainsString('alert', $output);
        $this->assertStringContainsString('Hello', $output);
    }

    public function testPreRendered(): void
    {
        $output = $this->renderStrip('<pre>code block here</pre>');
        $this->assertStringContainsString('code block here', $output);
    }

    public function testBoldRendered(): void
    {
        $output = $this->renderer->render('<strong>Bold text</strong>');
        $this->assertStringContainsString('Bold text', $output);
    }

    public function testBlockquoteRendered(): void
    {
        $output = $this->renderStrip('<blockquote>Famous quote</blockquote>');
        $this->assertStringContainsString('Famous quote', $output);
    }

    public function testComplexPageRendered(): void
    {
        $html   = '<html><body><h1>Title</h1><p>Paragraph</p><ul><li>A</li></ul></body></html>';
        $output = $this->renderStrip($html);
        $this->assertStringContainsString('TITLE', $output);
        $this->assertStringContainsString('Paragraph', $output);
        $this->assertStringContainsString('A', $output);
    }
}
