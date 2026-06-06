<?php

declare(strict_types=1);

namespace Xbrowser\Renderer;

class TerminalRenderer
{
    private const BOLD      = "\033[1m";
    private const DIM       = "\033[2m";
    private const UNDERLINE = "\033[4m";
    private const CYAN      = "\033[36m";
    private const YELLOW    = "\033[33m";
    private const GREEN     = "\033[32m";
    private const RED       = "\033[31m";
    private const MAGENTA   = "\033[35m";
    private const RESET     = "\033[0m";
    private const BG_WHITE  = "\033[47m";
    private const FG_BLACK  = "\033[30m";

    private int $termWidth;
    private bool $useColor;

    public function __construct(int $termWidth = 0, bool $useColor = true)
    {
        $this->termWidth = $termWidth > 0 ? $termWidth : $this->detectWidth();
        $this->useColor  = $useColor;
    }

    public function render(string $html): string
    {
        $clean  = $this->stripComments($html);
        $parser = new HtmlParser($clean);
        $nodes  = $parser->parse();

        $output = '';
        foreach ($nodes as $node) {
            $output .= $this->renderNode($node);
        }

        return $this->cleanOutput($output);
    }

    private function renderNode(array $node): string
    {
        if ($node['type'] === 'text') {
            $text = trim($node['text']);
            return $text !== '' ? $text . ' ' : '';
        }

        if ($node['type'] !== 'element') {
            return '';
        }

        return match($node['tag']) {
            'html', 'body', 'div', 'section', 'article', 'main', 'header', 'footer', 'nav', 'aside' => $this->renderBlock($node),
            'h1'        => $this->renderHeading($node, 1),
            'h2'        => $this->renderHeading($node, 2),
            'h3'        => $this->renderHeading($node, 3),
            'h4'        => $this->renderHeading($node, 4),
            'h5'        => $this->renderHeading($node, 5),
            'h6'        => $this->renderHeading($node, 6),
            'p'         => $this->renderParagraph($node),
            'a'         => $this->renderLink($node),
            'button'    => $this->renderButton($node),
            'input'     => $this->renderInput($node),
            'textarea'  => $this->renderTextarea($node),
            'select'    => $this->renderSelect($node),
            'ul', 'ol'  => $this->renderList($node),
            'li'        => $this->renderListItem($node),
            'table'     => $this->renderTable($node),
            'tr'        => $this->renderTableRow($node),
            'th', 'td'  => $this->renderTableCell($node),
            'img'       => $this->renderImage($node),
            'hr'        => $this->renderHr(),
            'br'        => PHP_EOL,
            'strong', 'b' => $this->renderBold($node),
            'em', 'i'   => $this->renderItalic($node),
            'code'      => $this->renderCode($node),
            'pre'       => $this->renderPre($node),
            'blockquote' => $this->renderBlockquote($node),
            'form'      => $this->renderBlock($node),
            'label'     => $this->renderInline($node),
            'span'      => $this->renderInline($node),
            'script', 'style', 'meta', 'link', 'head', 'noscript' => '',
            default     => $this->renderBlock($node),
        };
    }

    private function renderBlock(array $node): string
    {
        $inner = $this->renderChildren($node['children']);
        return $inner !== '' ? PHP_EOL . $inner . PHP_EOL : '';
    }

    private function renderHeading(array $node, int $level): string
    {
        $text    = $this->extractText($node);
        $prefix  = str_repeat('#', $level) . ' ';
        $width   = min($this->termWidth, 80);
        $line    = $prefix . strtoupper($text);
        $underline = match($level) {
            1 => str_repeat('═', min(strlen($line), $width)),
            2 => str_repeat('─', min(strlen($line), $width)),
            default => '',
        };

        $styled = $this->color(self::BOLD . ($level === 1 ? self::CYAN : ($level === 2 ? self::YELLOW : '')), $line);

        return PHP_EOL . $styled . PHP_EOL . ($underline ? $underline . PHP_EOL : '');
    }

    private function renderParagraph(array $node): string
    {
        $text = $this->renderChildren($node['children']);
        $text = trim($text);
        if ($text === '') return '';
        return PHP_EOL . $this->wordWrap($text) . PHP_EOL;
    }

    private function renderLink(array $node): string
    {
        $text = $this->extractText($node);
        $href = $node['attrs']['href'] ?? '#';
        if ($text === '') return '';
        return $this->color(self::UNDERLINE . self::CYAN, "[{$text}]") . $this->color(self::DIM, "({$href})");
    }

    private function renderButton(array $node): string
    {
        $text = $this->extractText($node);
        $type = $node['attrs']['type'] ?? 'button';
        return $this->color(self::BG_WHITE . self::FG_BLACK, "[ {$text} ]");
    }

    private function renderInput(array $node): string
    {
        $type        = $node['attrs']['type'] ?? 'text';
        $placeholder = $node['attrs']['placeholder'] ?? '';
        $value       = $node['attrs']['value'] ?? '';
        $name        = $node['attrs']['name'] ?? '';

        return match($type) {
            'submit', 'button', 'reset' => $this->color(self::BG_WHITE . self::FG_BLACK, "[ " . ($value ?: $name ?: ucfirst($type)) . " ]"),
            'checkbox' => $this->color(self::CYAN, isset($node['attrs']['checked']) ? '[x]' : '[ ]'),
            'radio'    => $this->color(self::CYAN, isset($node['attrs']['checked']) ? '(•)' : '( )'),
            'hidden'   => '',
            'password' => $this->color(self::GREEN, "[" . str_repeat('•', 8) . "________]"),
            default    => $this->color(self::GREEN, "[" . ($placeholder ?: $value ?: str_repeat('_', 20)) . "________]"),
        };
    }

    private function renderTextarea(array $node): string
    {
        $placeholder = $node['attrs']['placeholder'] ?? '';
        $rows        = (int)($node['attrs']['rows'] ?? 4);
        $lines       = array_fill(0, $rows, str_repeat('_', 40));
        $lines[0]    = $placeholder ?: $lines[0];
        return PHP_EOL . $this->color(self::GREEN, '┌' . str_repeat('─', 42) . '┐') . PHP_EOL
            . implode(PHP_EOL, array_map(fn($l) => $this->color(self::GREEN, "│ {$l} │"), $lines)) . PHP_EOL
            . $this->color(self::GREEN, '└' . str_repeat('─', 42) . '┘') . PHP_EOL;
    }

    private function renderSelect(array $node): string
    {
        $options = $this->extractOptions($node);
        $first   = $options[0] ?? 'Select...';
        return $this->color(self::GREEN, "[ {$first} ▾ ]");
    }

    private function renderList(array $node): string
    {
        $ordered = $node['tag'] === 'ol';
        $items   = array_values(array_filter($node['children'], fn($n) => ($n['type'] === 'element' && $n['tag'] === 'li')));
        $out     = PHP_EOL;

        foreach ($items as $i => $item) {
            $text   = trim($this->renderChildren($item['children']));
            $bullet = $ordered ? ($i + 1) . '.' : '•';
            $out   .= $this->color(self::CYAN, "  {$bullet}") . " {$text}" . PHP_EOL;
        }

        return $out;
    }

    private function renderListItem(array $node): string
    {
        $text = trim($this->renderChildren($node['children']));
        return $this->color(self::CYAN, "  •") . " {$text}" . PHP_EOL;
    }

    private function renderTable(array $node): string
    {
        $rows    = $this->collectTableRows($node);
        if (empty($rows)) return '';

        $colWidths = [];
        foreach ($rows as $row) {
            foreach ($row as $ci => $cell) {
                $len = mb_strlen(strip_tags($cell));
                $colWidths[$ci] = max($colWidths[$ci] ?? 0, $len, 3);
            }
        }

        $sep = '┼' . implode('┼', array_map(fn($w) => str_repeat('─', $w + 2), $colWidths)) . '┼';
        $out = PHP_EOL . '┌' . implode('┬', array_map(fn($w) => str_repeat('─', $w + 2), $colWidths)) . '┐' . PHP_EOL;

        foreach ($rows as $ri => $row) {
            $line = '│';
            foreach ($colWidths as $ci => $w) {
                $cell = strip_tags($row[$ci] ?? '');
                $pad  = str_pad($cell, $w);
                $line .= " {$pad} │";
            }
            $out .= $line . PHP_EOL;
            if ($ri === 0 && count($rows) > 1) {
                $out .= '├' . implode('┼', array_map(fn($w) => str_repeat('─', $w + 2), $colWidths)) . '┤' . PHP_EOL;
            }
        }

        $out .= '└' . implode('┴', array_map(fn($w) => str_repeat('─', $w + 2), $colWidths)) . '┘' . PHP_EOL;
        return $out;
    }

    private function renderTableRow(array $node): string { return ''; }
    private function renderTableCell(array $node): string { return ''; }

    private function renderImage(array $node): string
    {
        $alt = $node['attrs']['alt'] ?? 'image';
        $src = $node['attrs']['src'] ?? '';
        return $this->color(self::MAGENTA, "[IMAGE: {$alt}]");
    }

    private function renderHr(): string
    {
        return PHP_EOL . $this->color(self::DIM, str_repeat('─', min($this->termWidth, 80))) . PHP_EOL;
    }

    private function renderBold(array $node): string
    {
        $text = $this->extractText($node);
        return $this->color(self::BOLD, $text);
    }

    private function renderItalic(array $node): string
    {
        $text = $this->extractText($node);
        return "_{$text}_";
    }

    private function renderCode(array $node): string
    {
        $text = $this->extractText($node);
        return $this->color(self::GREEN, "`{$text}`");
    }

    private function renderPre(array $node): string
    {
        $text = $this->extractText($node);
        $lines = explode("\n", $text);
        $out   = PHP_EOL . $this->color(self::DIM, '┌' . str_repeat('─', 60) . '┐') . PHP_EOL;
        foreach ($lines as $line) {
            $out .= $this->color(self::GREEN, '│ ') . $line . PHP_EOL;
        }
        $out .= $this->color(self::DIM, '└' . str_repeat('─', 60) . '┘') . PHP_EOL;
        return $out;
    }

    private function renderBlockquote(array $node): string
    {
        $text  = trim($this->renderChildren($node['children']));
        $lines = explode("\n", $this->wordWrap($text));
        return PHP_EOL . implode(PHP_EOL, array_map(fn($l) => $this->color(self::CYAN, '▌ ') . $l, $lines)) . PHP_EOL;
    }

    private function renderInline(array $node): string
    {
        return $this->renderChildren($node['children']);
    }

    private function renderChildren(array $children): string
    {
        $out = '';
        foreach ($children as $child) {
            $out .= $this->renderNode($child);
        }
        return $out;
    }

    private function extractText(array $node): string
    {
        if ($node['type'] === 'text') {
            return trim($node['text']);
        }

        $text = $node['text'] ?? '';
        if ($text) return $text;

        foreach ($node['children'] ?? [] as $child) {
            $text .= $this->extractText($child) . ' ';
        }

        return trim($text);
    }

    private function collectTableRows(array $node): array
    {
        $rows = [];
        $this->walkForTag($node, ['tr'], function (array $tr) use (&$rows) {
            $cells = [];
            $this->walkForTag($tr, ['th', 'td'], function (array $cell) use (&$cells) {
                $cells[] = $this->extractText($cell);
            });
            if ($cells) $rows[] = $cells;
        });
        return $rows;
    }

    private function walkForTag(array $node, array $tags, callable $fn): void
    {
        foreach ($node['children'] ?? [] as $child) {
            if ($child['type'] === 'element' && in_array($child['tag'], $tags, true)) {
                $fn($child);
            } else {
                $this->walkForTag($child, $tags, $fn);
            }
        }
    }

    private function extractOptions(array $node): array
    {
        $opts = [];
        $this->walkForTag($node, ['option'], function (array $opt) use (&$opts) {
            $opts[] = $this->extractText($opt);
        });
        return $opts;
    }

    private function wordWrap(string $text, int $width = 0): string
    {
        $w = $width ?: min($this->termWidth - 2, 80);
        return wordwrap($text, $w, PHP_EOL, true);
    }

    private function color(string $codes, string $text): string
    {
        if (!$this->useColor) {
            return $text;
        }
        return $codes . $text . self::RESET;
    }

    private function stripComments(string $html): string
    {
        return preg_replace('/<!--.*?-->/s', '', $html) ?? $html;
    }

    private function cleanOutput(string $output): string
    {
        $output = preg_replace('/\n{3,}/', "\n\n", $output) ?? $output;
        return trim($output) . PHP_EOL;
    }

    private function detectWidth(): int
    {
        if (function_exists('shell_exec')) {
            $cols = (int) trim((string) @shell_exec('tput cols 2>/dev/null'));
            if ($cols > 0) return $cols;
        }
        return 120;
    }
}
