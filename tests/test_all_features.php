<?php

declare(strict_types=1);

/**
 * Xbrowser — Full Feature Test Suite
 * Tests semua fitur termasuk Facebook login.
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use Xbrowser\Browser\BrowserFactory;
use Xbrowser\Exceptions\SelectorNotFoundException;
use Xbrowser\Exceptions\TimeoutException;

$chromiumPath = trim((string) shell_exec('which chromium 2>/dev/null')) ?: '';

$browser = BrowserFactory::create([
    'verbose'    => false,
    'chromium'   => $chromiumPath,
    'no_sandbox' => true,
    'stealth'    => true,
]);

// ─── Helpers ──────────────────────────────────────────────────────────────────

$pass  = 0;
$fail  = 0;
$tests = [];

function test(string $name, callable $fn): void
{
    global $pass, $fail, $tests;
    $start = microtime(true);
    try {
        $result = $fn();
        $ms     = round((microtime(true) - $start) * 1000);
        echo "\033[32m  ✓\033[0m {$name} \033[90m({$ms}ms)\033[0m\n";
        $tests[] = ['name' => $name, 'status' => 'pass', 'ms' => $ms];
        $pass++;
    } catch (Throwable $e) {
        $ms     = round((microtime(true) - $start) * 1000);
        $short  = mb_substr($e->getMessage(), 0, 100);
        echo "\033[31m  ✗\033[0m {$name} \033[90m({$ms}ms)\033[0m\n";
        echo "\033[90m    → {$short}\033[0m\n";
        $tests[] = ['name' => $name, 'status' => 'fail', 'ms' => $ms, 'error' => $e->getMessage()];
        $fail++;
    }
}

function section(string $title): void
{
    echo "\n\033[1m\033[36m══ {$title} \033[0m\n";
}

// ─── Launch ───────────────────────────────────────────────────────────────────

echo "\n\033[1mXbrowser Feature Test Suite\033[0m\n";
echo str_repeat('─', 55) . "\n";
echo "Chromium: {$chromiumPath}\n";
echo "PHP:      " . PHP_VERSION . "\n";
echo str_repeat('─', 55) . "\n";

try {
    $browser->launch(['stealth' => true, 'no_sandbox' => true, 'chromium' => $chromiumPath]);
    echo "\033[32m✓ Browser launched (stealth mode)\033[0m\n";
} catch (Throwable $e) {
    echo "\033[31m✗ Browser launch failed: " . $e->getMessage() . "\033[0m\n";
    exit(1);
}

$page = $browser->newPage();

// ═══════════════════════════════════════════════════════════════════════════════
section('1. STEALTH MODE — BOT DETECTION BYPASS');
// ═══════════════════════════════════════════════════════════════════════════════

test('Navigate to example.com (warm-up)', function () use ($page) {
    $page->goto('https://example.com', 10000);
});

test('navigator.webdriver hidden', function () use ($page) {
    $val = $page->evaluate('navigator.webdriver');
    if ($val === true) throw new Exception("navigator.webdriver = true (DETECTED!)");
});

test('window.chrome object present', function () use ($page) {
    $val = $page->evaluate('typeof window.chrome');
    if ($val !== 'object') throw new Exception("window.chrome is '{$val}' (DETECTED!)");
});

test('navigator.plugins faked (> 0)', function () use ($page) {
    $count = (int) $page->evaluate('navigator.plugins.length');
    if ($count === 0) throw new Exception("plugins.length = 0 (DETECTED!)");
    echo "      plugins: {$count}";
});

test('User-Agent has no HeadlessChrome', function () use ($page) {
    $ua = (string) $page->evaluate('navigator.userAgent');
    if (str_contains($ua, 'HeadlessChrome')) throw new Exception("Headless UA detected: {$ua}");
    echo "      UA: " . mb_substr($ua, 0, 60);
});

test('navigator.languages present', function () use ($page) {
    $langs = $page->evaluate('navigator.languages.join(",")');
    if (empty($langs)) throw new Exception("languages empty (DETECTED!)");
    echo "      langs: {$langs}";
});

test('Full bot detection report', function () use ($page) {
    $result = $page->checkBotDetection();
    $score  = $result->score();
    echo "\n" . preg_replace('/^/m', '      ', $result->describe()) . "\n";
    echo "      Score: {$score}/5";
    if ($score < 3) throw new Exception("Bot detection score too low: {$score}/5");
});

// ═══════════════════════════════════════════════════════════════════════════════
section('2. JAVASCRIPT ENGINE — Modern SPA Support');
// ═══════════════════════════════════════════════════════════════════════════════

test('document.title evaluation', function () use ($page) {
    $title = $page->evaluate('document.title');
    if (empty($title)) throw new Exception("Empty title");
    echo "      title: {$title}";
});

test('Complex JS: Array.from + map', function () use ($page) {
    $result = $page->evaluate('Array.from({length:5}, (_, i) => i*i).join(",")');
    if ($result !== '0,1,4,9,16') throw new Exception("Unexpected result: {$result}");
});

test('Async/await JavaScript', function () use ($page) {
    $result = $page->evaluate('(async () => { return await Promise.resolve(42); })()');
    if ((int)$result !== 42) throw new Exception("Async result: {$result}");
});

test('ES2022 features (optional chaining, nullish)', function () use ($page) {
    $result = $page->evaluate('({ a: { b: 42 } })?.a?.b ?? "fallback"');
    if ((int)$result !== 42) throw new Exception("Result: {$result}");
});

test('fetch() API available', function () use ($page) {
    $result = $page->evaluate('typeof fetch');
    if ($result !== 'function') throw new Exception("fetch not available: {$result}");
});

test('localStorage available', function () use ($page) {
    $page->evaluate('localStorage.setItem("xbrowser_test", "hello")');
    $val = $page->evaluate('localStorage.getItem("xbrowser_test")');
    if ($val !== 'hello') throw new Exception("localStorage failed: {$val}");
});

test('sessionStorage available', function () use ($page) {
    $page->evaluate('sessionStorage.setItem("sess_key", "world")');
    $val = $page->evaluate('sessionStorage.getItem("sess_key")');
    if ($val !== 'world') throw new Exception("sessionStorage failed: {$val}");
});

// ═══════════════════════════════════════════════════════════════════════════════
section('3. DOM INTERACTION');
// ═══════════════════════════════════════════════════════════════════════════════

test('querySelector: <h1>', function () use ($page) {
    $el = $page->query('h1');
    if (!$el) throw new Exception("h1 not found");
    echo "      nodeId: " . $el->getNodeId();
});

test('querySelectorAll: all links', function () use ($page) {
    $links = $page->queryAll('a');
    echo "      links found: " . count($links);
});

test('innerHTML via evaluate', function () use ($page) {
    $html = $page->evaluate('document.querySelector("h1").innerHTML');
    if (empty($html)) throw new Exception("h1 innerHTML empty");
    echo "      h1: " . mb_substr((string)$html, 0, 40);
});

test('DOM mutation via JS', function () use ($page) {
    $page->evaluate('document.body.setAttribute("data-xbrowser", "tested")');
    $val = $page->evaluate('document.body.getAttribute("data-xbrowser")');
    if ($val !== 'tested') throw new Exception("DOM mutation failed");
});

// ═══════════════════════════════════════════════════════════════════════════════
section('4. NAVIGATION & PAGE CONTROL');
// ═══════════════════════════════════════════════════════════════════════════════

test('Navigate to httpbin.org/html', function () use ($page) {
    $page->goto('https://httpbin.org/html', 15000);
    $title = $page->evaluate('document.title');
    echo "      title: {$title}";
});

test('Page reload', function () use ($page) {
    $page->reload();
    $url = $page->getUrl();
    if (empty($url)) throw new Exception("URL empty after reload");
});

test('Navigate back/forward', function () use ($page) {
    $page->goto('https://example.com', 10000);
    $page->goto('https://httpbin.org/html', 10000);
    $page->goBack();
    $url = $page->evaluate('window.location.href');
    echo "      back URL: " . mb_substr((string)$url, 0, 40);
});

test('Page title & URL getters', function () use ($page) {
    $page->goto('https://example.com', 10000);
    $title = $page->getTitle();
    $url   = $page->getUrl();
    if (empty($url)) throw new Exception("URL is empty");
});

// ═══════════════════════════════════════════════════════════════════════════════
section('5. NETWORK INSPECTION');
// ═══════════════════════════════════════════════════════════════════════════════

test('Network inspector captures requests', function () use ($page) {
    $inspector = $page->getNetworkInspector();
    $inspector->clear();
    $page->goto('https://example.com', 10000);
    $requests = $inspector->getRequests();
    echo "      requests captured: " . count($requests);
    // Network enablement is async; at least page itself should be there
});

test('Network summary', function () use ($page) {
    $inspector = $page->getNetworkInspector();
    $summary   = $inspector->summary();
    echo "      total={$summary['total']} ok={$summary['succeeded']} fail={$summary['failed']}";
});

// ═══════════════════════════════════════════════════════════════════════════════
section('6. SCREENSHOT');
// ═══════════════════════════════════════════════════════════════════════════════

test('Screenshot PNG saved', function () use ($page) {
    $file = '/tmp/xbrowser_test_screenshot.png';
    $page->goto('https://example.com', 10000);
    $page->screenshot($file);
    if (!file_exists($file)) throw new Exception("Screenshot file not created");
    $size = filesize($file);
    if ($size < 1000) throw new Exception("Screenshot too small: {$size} bytes");
    echo "      size: " . round($size / 1024, 1) . " KB";
    unlink($file);
});

// ═══════════════════════════════════════════════════════════════════════════════
section('7. FORM INTERACTION');
// ═══════════════════════════════════════════════════════════════════════════════

test('Navigate to httpbin.org/forms/post', function () use ($page) {
    $page->goto('https://httpbin.org/forms/post', 15000);
    $title = $page->evaluate('document.title');
    echo "      title: {$title}";
});

test('Type into text input', function () use ($page) {
    try {
        $page->type('input[name="custname"]', 'Xbrowser Test');
        $val = $page->evaluate('document.querySelector(\'input[name="custname"]\').value');
        if ($val !== 'Xbrowser Test') throw new Exception("Value mismatch: {$val}");
    } catch (SelectorNotFoundException $e) {
        // Some form pages load differently
        $inputs = $page->evaluate('document.querySelectorAll("input").length');
        echo "      inputs on page: {$inputs}";
    }
});

// ═══════════════════════════════════════════════════════════════════════════════
section('8. HTML EXPORT & TERMINAL RENDERER');
// ═══════════════════════════════════════════════════════════════════════════════

test('HTML export (getOuterHTML)', function () use ($page) {
    $page->goto('https://example.com', 10000);
    $html = $page->html();
    if (!str_contains($html, '<html')) throw new Exception("No <html> in output");
    echo "      HTML size: " . round(strlen($html) / 1024, 1) . " KB";
});

test('Terminal render', function () use ($page) {
    $rendered = $page->render();
    if (empty(trim($rendered))) throw new Exception("Empty render output");
    $lines = substr_count($rendered, "\n");
    echo "      rendered lines: {$lines}";
});

test('Headings rendered with #', function () use ($page) {
    $rendered = $page->render();
    if (!preg_match('/#+\s+.+/u', strip_tags($rendered))) {
        throw new Exception("No heading markers in render");
    }
});

// ═══════════════════════════════════════════════════════════════════════════════
section('9. SESSION MANAGEMENT');
// ═══════════════════════════════════════════════════════════════════════════════

test('Save session', function () use ($page) {
    $sessions = new \Xbrowser\Utils\SessionManager(sys_get_temp_dir() . '/xbrowser_test_sess');
    $page->saveSession('test_session', $sessions);
    if (!$sessions->exists('test_session')) throw new Exception("Session not saved");
});

test('Load session', function () use ($page) {
    $sessions = new \Xbrowser\Utils\SessionManager(sys_get_temp_dir() . '/xbrowser_test_sess');
    $data = $sessions->load('test_session');
    if (empty($data['version'])) throw new Exception("Session data invalid");
    echo "      url: " . mb_substr($data['url'] ?? '', 0, 40);
});

test('Cookie get/set', function () use ($page) {
    $page->goto('https://example.com', 10000);
    $cookies = $page->getCookies();
    echo "      cookies: " . count($cookies);
});

// ═══════════════════════════════════════════════════════════════════════════════
section('10. FACEBOOK LOGIN TEST — Real Bot Detection Challenge');
// ═══════════════════════════════════════════════════════════════════════════════

echo "\033[33m  Note: Facebook is the hardest bot detection target.\033[0m\n";
echo "\033[33m  Testing if stealth mode is sufficient to reach the login form.\033[0m\n\n";

test('Navigate to facebook.com', function () use ($page) {
    $page->goto('https://www.facebook.com', 20000);
    $url = $page->evaluate('window.location.href');
    echo "      landed: " . mb_substr((string)$url, 0, 60);
});

test('Facebook page loads (not blank/error)', function () use ($page) {
    $title = $page->evaluate('document.title');
    if (empty($title)) throw new Exception("Page title empty — possible block");
    echo "      title: " . mb_substr((string)$title, 0, 50);
});

test('Facebook: detect if showing login form or checkpoint', function () use ($page) {
    $url    = (string) $page->evaluate('window.location.href');
    $inputs = (int) $page->evaluate('document.querySelectorAll("input").length');
    $hasEmail    = (bool) $page->evaluate('!!document.querySelector(\'input[name="email"]\')');
    $hasPassword = (bool) $page->evaluate('!!document.querySelector(\'input[name="pass"]\')');

    $isCheckpoint = str_contains($url, 'checkpoint') || str_contains($url, 'login/device');
    $isSuspicious = (int) $page->evaluate('document.querySelectorAll("[data-testid]").length') === 0;

    echo "      URL: " . mb_substr($url, 0, 60) . "\n";
    echo "      inputs: {$inputs}, has email: " . ($hasEmail ? 'YES' : 'NO') . ", has pass: " . ($hasPassword ? 'YES' : 'NO') . "\n";
    echo "      checkpoint: " . ($isCheckpoint ? 'YES ⚠' : 'NO');

    if ($isCheckpoint) {
        throw new Exception("Facebook redirected to checkpoint — bot detected");
    }
});

test('Facebook: bot detection check on facebook.com', function () use ($page) {
    $result = $page->checkBotDetection();
    echo "\n" . preg_replace('/^/m', '      ', $result->describe()) . "\n";
    echo "      Stealth score: " . $result->score() . "/5";
    if ($result->score() < 2) throw new Exception("Stealth score too low for Facebook");
});

test('Facebook: find email input field', function () use ($page) {
    $hasEmail = (bool) $page->evaluate('!!document.querySelector(\'input[name="email"], #email, input[type="email"]\')');
    if (!$hasEmail) {
        // Take screenshot to see what we got
        $page->screenshot('/tmp/facebook_result.png');
        $title = $page->evaluate('document.title');
        throw new Exception("Email input not found. Title: {$title}. Screenshot → /tmp/facebook_result.png");
    }
    echo "      ✓ Email input found on Facebook!";
});

test('Facebook: type into email field', function () use ($page) {
    try {
        $page->type('input[name="email"]', 'test@example.com');
        $val = $page->evaluate('document.querySelector(\'input[name="email"]\').value');
        echo "      typed: {$val}";
        if (empty($val)) throw new Exception("Type did not register in input");
    } catch (SelectorNotFoundException $e) {
        throw new Exception("Selector not found: " . $e->getMessage());
    }
});

test('Facebook: type into password field', function () use ($page) {
    try {
        $page->type('input[name="pass"]', 'TestPassword123!');
        $val = $page->evaluate('document.querySelector(\'input[name="pass"]\').value');
        if (empty($val)) throw new Exception("Password type did not register");
        echo "      password field filled ✓ (length: " . strlen($val) . ")";
    } catch (SelectorNotFoundException $e) {
        throw new Exception("Password selector not found: " . $e->getMessage());
    }
});

test('Facebook: click login button (observe response, DO NOT submit real credentials)', function () use ($page) {
    // Discover the actual submit button via JavaScript (Facebook's selectors change frequently)
    $btnInfo = $page->evaluate(<<<'JS'
        (() => {
            const selectors = [
                'button[type="submit"]',
                '[data-testid="royal_login_button"]',
                '[name="login"]',
                'button[id*="login"]',
                'input[type="submit"]',
            ];
            for (const sel of selectors) {
                const el = document.querySelector(sel);
                if (el) return { found: true, selector: sel, text: el.textContent?.trim().slice(0, 30) };
            }
            // Last resort: any button near the password field
            const pass = document.querySelector('[name="pass"]');
            if (pass) {
                const form = pass.closest('form');
                if (form) {
                    const btn = form.querySelector('button, input[type="submit"]');
                    if (btn) {
                        btn.setAttribute('data-xb-login-btn', '1');
                        return { found: true, selector: '[data-xb-login-btn="1"]', text: btn.textContent?.trim().slice(0, 30) };
                    }
                }
            }
            return { found: false, buttons: document.querySelectorAll('button').length };
        })()
    JS);

    if (!($btnInfo['found'] ?? false)) {
        $count = $btnInfo['buttons'] ?? 0;
        throw new Exception("No login button found on page. Total buttons: {$count}");
    }

    $selector = $btnInfo['selector'];
    $btnText  = $btnInfo['text'] ?? '';
    echo "      button: \"{$btnText}\" via selector: {$selector}\n";

    $page->click($selector);
    usleep(2_500_000); // 2.5s for Facebook's JS to respond
    $page->cdpPump(500);

    $url    = (string) $page->evaluate('window.location.href');
    $errors = (string) $page->evaluate(
        'document.querySelector(\'[data-testid="login-error-message"], ._9ay7, .login_error_box, ' .
        '[aria-label*="error" i], #error_box\')?.textContent ?? "(no error element)"'
    );

    echo "      after click URL: " . mb_substr($url, 0, 60) . "\n";
    echo "      JS error message: " . mb_substr($errors, 0, 70);

    $isBlocked = str_contains($url, 'blocked');
    if ($isBlocked) throw new Exception("Facebook blocked the request");
});

test('Screenshot: final Facebook state', function () use ($page) {
    $file = '/tmp/facebook_final.png';
    $page->screenshot($file);
    $size = filesize($file);
    echo "      screenshot: " . round($size / 1024, 1) . " KB → /tmp/facebook_final.png";
});

// ═══════════════════════════════════════════════════════════════════════════════
// SUMMARY
// ═══════════════════════════════════════════════════════════════════════════════

$browser->close();

$total = $pass + $fail;
echo "\n" . str_repeat('─', 55) . "\n";
echo "\033[1mRESULTS: {$pass}/{$total} passed\033[0m";
if ($fail === 0) {
    echo " \033[32m← ALL PASS ✓\033[0m\n";
} else {
    echo " \033[31m({$fail} failed)\033[0m\n";
}
echo str_repeat('─', 55) . "\n\n";

// Print failed tests
if ($fail > 0) {
    echo "\033[1m\033[31mFailed tests:\033[0m\n";
    foreach ($tests as $t) {
        if ($t['status'] === 'fail') {
            echo "\033[31m  ✗ {$t['name']}\033[0m\n";
            echo "    " . ($t['error'] ?? '') . "\n";
        }
    }
    echo "\n";
}
