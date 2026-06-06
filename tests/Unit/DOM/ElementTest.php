<?php

declare(strict_types=1);

namespace Xbrowser\Tests\Unit\DOM;

use PHPUnit\Framework\TestCase;
use Xbrowser\DOM\Element;

class ElementTest extends TestCase
{
    public function testGetNodeId(): void
    {
        $el = new Element(42, 'div', 'Hello');
        $this->assertSame(42, $el->getNodeId());
    }

    public function testGetTagNameIsLowerCase(): void
    {
        $el = new Element(1, 'DIV', '');
        $this->assertSame('div', $el->getTagName());
    }

    public function testGetText(): void
    {
        $el = new Element(1, 'p', 'Some text');
        $this->assertSame('Some text', $el->getText());
    }

    public function testSetAndGetAttribute(): void
    {
        $el = new Element(1, 'a', '');
        $el->setAttribute('href', 'https://example.com');
        $this->assertSame('https://example.com', $el->getAttribute('href'));
    }

    public function testHasAttribute(): void
    {
        $el = new Element(1, 'input', '');
        $el->setAttribute('disabled', '');
        $this->assertTrue($el->hasAttribute('disabled'));
        $this->assertFalse($el->hasAttribute('checked'));
    }

    public function testGetAttributeReturnsNullWhenMissing(): void
    {
        $el = new Element(1, 'div', '');
        $this->assertNull($el->getAttribute('nonexistent'));
    }

    public function testAddAndGetChildren(): void
    {
        $parent = new Element(1, 'ul', '');
        $child1 = new Element(2, 'li', 'Item 1');
        $child2 = new Element(3, 'li', 'Item 2');

        $parent->addChild($child1);
        $parent->addChild($child2);

        $children = $parent->getChildren();
        $this->assertCount(2, $children);
        $this->assertSame('Item 1', $children[0]->getText());
    }

    public function testToArray(): void
    {
        $el = new Element(5, 'span', 'text', '<span>text</span>');
        $el->setAttribute('class', 'highlight');

        $arr = $el->toArray();
        $this->assertSame(5, $arr['nodeId']);
        $this->assertSame('span', $arr['tagName']);
        $this->assertSame('highlight', $arr['attributes']['class']);
    }

    public function testFromCdpNode(): void
    {
        $node = [
            'nodeId'   => 99,
            'nodeName' => 'BUTTON',
            'nodeType' => 1,
            'attributes' => ['class', 'btn', 'id', 'submit-btn'],
            'children' => [
                ['nodeType' => 3, 'nodeValue' => 'Click Me'],
            ],
        ];

        $el = Element::fromCdpNode($node);
        $this->assertSame(99, $el->getNodeId());
        $this->assertSame('button', $el->getTagName());
        $this->assertSame('Click Me', $el->getText());
        $this->assertSame('btn', $el->getAttribute('class'));
        $this->assertSame('submit-btn', $el->getAttribute('id'));
    }

    public function testFromCdpNodeWithNestedChildren(): void
    {
        $node = [
            'nodeId'     => 1,
            'nodeName'   => 'DIV',
            'nodeType'   => 1,
            'attributes' => [],
            'children'   => [
                [
                    'nodeId'     => 2,
                    'nodeName'   => 'P',
                    'nodeType'   => 1,
                    'attributes' => [],
                    'children'   => [['nodeType' => 3, 'nodeValue' => 'Hello']],
                ],
            ],
        ];

        $el       = Element::fromCdpNode($node);
        $children = $el->getChildren();
        $this->assertCount(1, $children);
        $this->assertSame('p', $children[0]->getTagName());
    }
}
