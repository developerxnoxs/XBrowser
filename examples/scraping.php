<?php

declare(strict_types=1);

/**
 * Web scraping example — extracts structured data from a real website.
 *
 * Usage:
 *   php examples/scraping.php
 *   php examples/scraping.php --url=https://news.ycombinator.com
 *   php examples/scraping.php --url=https://quotes.toscrape.com --json
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use Xbrowser\Browser\BrowserFactory;

$opts     = getopt('', ['url:', 'json', 'no-headless']);
$url      = $opts['url']    ?? 'https://quotes.toscrape.com';
$asJson   = isset($opts['json']);
$headless = !isset($opts['no-headless']);

echo "\n\033[1mXbrowser — Web Scraping\033[0m\n";
echo str_repeat('─', 50) . "\n";
echo "Target: {$url}\n\n";

$browser = BrowserFactory::create(['verbose' => false]);

try {
    $browser->launch([
        'headless'   => $headless,
        'stealth'    => true,
        'no_sandbox' => true,
    ]);

    $page = $browser->newPage();

    echo "→ Loading page ...\n";
    $page->goto($url, 30000);
    echo "  Title: " . $page->getTitle() . "\n";

    // ── Extract all headings ──────────────────────────────────────────────────
    $headings = $page->evaluate(<<<'JS'
        Array.from(document.querySelectorAll('h1,h2,h3')).slice(0, 10).map(el => ({
            tag:  el.tagName,
            text: el.textContent.trim().slice(0, 100)
        }))
    JS);

    // ── Extract all links ─────────────────────────────────────────────────────
    $links = $page->evaluate(<<<'JS'
        Array.from(document.querySelectorAll('a[href]')).slice(0, 20).map(el => ({
            text: el.textContent.trim().slice(0, 60),
            href: el.href
        })).filter(l => l.text.length > 0)
    JS);

    // ── Extract meta description ──────────────────────────────────────────────
    $meta = $page->evaluate(
        'document.querySelector(\'meta[name="description"]\')?.content ?? "(none)"'
    );

    // ── Extract quote-specific data (quotes.toscrape.com) ────────────────────
    $quotes = $page->evaluate(<<<'JS'
        Array.from(document.querySelectorAll('.quote')).slice(0, 5).map(el => ({
            text:   el.querySelector('.text')?.textContent?.trim() ?? '',
            author: el.querySelector('.author')?.textContent?.trim() ?? '',
            tags:   Array.from(el.querySelectorAll('.tag')).map(t => t.textContent.trim())
        })).filter(q => q.text.length > 0)
    JS);

    // ── Network summary ───────────────────────────────────────────────────────
    $inspector  = $page->getNetworkInspector();
    $netSummary = $inspector->summary();

    // ── Output ────────────────────────────────────────────────────────────────
    $result = [
        'url'         => $page->getUrl(),
        'title'       => $page->getTitle(),
        'description' => $meta,
        'headings'    => $headings,
        'links'       => $links,
        'quotes'      => $quotes,
        'network'     => $netSummary,
    ];

    if ($asJson) {
        echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    } else {
        echo "\n\033[1mExtracted Data\033[0m\n";
        echo str_repeat('─', 50) . "\n";

        echo "Description: " . mb_substr((string) $meta, 0, 80) . "\n\n";

        if (is_array($headings) && count($headings) > 0) {
            echo "Headings (" . count($headings) . "):\n";
            foreach ($headings as $h) {
                echo "  [{$h['tag']}] {$h['text']}\n";
            }
            echo "\n";
        }

        if (is_array($quotes) && count($quotes) > 0) {
            echo "Quotes (" . count($quotes) . "):\n";
            foreach ($quotes as $q) {
                $tags = implode(', ', (array) ($q['tags'] ?? []));
                echo "  \"{$q['text']}\"\n";
                echo "  — {$q['author']}" . ($tags ? " [{$tags}]" : '') . "\n\n";
            }
        }

        if (is_array($links) && count($links) > 0) {
            echo "Links (" . count($links) . "):\n";
            foreach (array_slice($links, 0, 10) as $l) {
                echo "  {$l['text']} → " . mb_substr((string) $l['href'], 0, 60) . "\n";
            }
            echo "\n";
        }

        echo "Network: total={$netSummary['total']} ok={$netSummary['succeeded']} fail={$netSummary['failed']}\n";
    }

} catch (\Throwable $e) {
    echo "\033[31m✗ Error: " . $e->getMessage() . "\033[0m\n";
    exit(1);
} finally {
    $browser->close();
    echo str_repeat('─', 50) . "\n";
    echo "Done.\n\n";
}
