<?php

declare(strict_types=1);

/**
 * Basic automation example — works like Playwright / Selenium.
 *
 * Usage:
 *   php examples/basic_automation.php
 *   php examples/basic_automation.php --url=https://example.com
 *   php examples/basic_automation.php --no-headless   (show browser window)
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use Xbrowser\Browser\BrowserFactory;

$opts     = getopt('', ['url:', 'no-headless', 'screenshot:']);
$url      = $opts['url']        ?? 'https://example.com';
$headless = !isset($opts['no-headless']);
$shotFile = $opts['screenshot'] ?? '/tmp/xbrowser_basic.png';

echo "\n\033[1mXbrowser — Basic Automation\033[0m\n";
echo str_repeat('─', 50) . "\n";

$browser = BrowserFactory::create(['verbose' => false]);

try {
    $browser->launch([
        'headless'   => $headless,
        'stealth'    => true,
        'no_sandbox' => true,
    ]);

    $page = $browser->newPage();

    // ── Navigate ──────────────────────────────────────────────────────────────
    echo "→ Navigating to: {$url}\n";
    $page->goto($url, 30000);

    $title = $page->getTitle();
    $finalUrl = $page->getUrl();
    echo "  Title: {$title}\n";
    echo "  URL:   {$finalUrl}\n";

    // ── Evaluate JavaScript ───────────────────────────────────────────────────
    $linkCount = $page->evaluate('document.querySelectorAll("a").length');
    $h1Text    = $page->evaluate('document.querySelector("h1")?.textContent?.trim() ?? "(none)"');
    echo "\n→ Page analysis:\n";
    echo "  H1:    {$h1Text}\n";
    echo "  Links: {$linkCount}\n";

    // ── Stealth check ─────────────────────────────────────────────────────────
    $detection = $page->checkBotDetection();
    echo "\n→ Stealth score: " . $detection->score() . "/5\n";
    echo "  UA: " . mb_substr($detection->userAgent, 0, 70) . "\n";

    // ── Terminal render ───────────────────────────────────────────────────────
    echo "\n→ Terminal-rendered content:\n";
    echo str_repeat('─', 50) . "\n";
    echo $page->render();
    echo str_repeat('─', 50) . "\n";

    // ── Screenshot ────────────────────────────────────────────────────────────
    $page->screenshot($shotFile);
    $size = filesize($shotFile);
    echo "\n→ Screenshot saved: {$shotFile} (" . round($size / 1024, 1) . " KB)\n";

} catch (\Throwable $e) {
    echo "\033[31m✗ Error: " . $e->getMessage() . "\033[0m\n";
    exit(1);
} finally {
    $browser->close();
    echo str_repeat('─', 50) . "\n";
    echo "Done.\n\n";
}
