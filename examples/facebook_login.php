<?php

declare(strict_types=1);

/**
 * Facebook Login Demo — tests stealth + React form submission.
 *
 * Usage:
 *   php examples/facebook_login.php --email=you@example.com --password=secret
 *   php examples/facebook_login.php --no-headless
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use Xbrowser\Browser\BrowserFactory;
use Xbrowser\Exceptions\SelectorNotFoundException;
use Xbrowser\Exceptions\TimeoutException;

// ── CLI args ──────────────────────────────────────────────────────────────────
$opts     = getopt('', ['email:', 'password:', 'screenshot:', 'no-headless']);
$email    = $opts['email']      ?? '083807650503';
$password = $opts['password']   ?? 'Bulusari2580';
$shotFile = $opts['screenshot'] ?? 'facebook_after_login.png';
$headless = !isset($opts['no-headless']);

echo "\n\033[1mXbrowser — Facebook Login Test\033[0m\n";
echo str_repeat('─', 52) . "\n";
echo "Email   : {$email}\n";
echo "Headless: " . ($headless ? 'yes' : 'no') . "\n\n";

// ── Boot ──────────────────────────────────────────────────────────────────────
$browser = BrowserFactory::create(['verbose' => false]);

try {
    $browser->launch([
        'headless'   => $headless,
        'stealth'    => true,
        'no_sandbox' => true,
    ]);

    $page = $browser->newPage();

    // ── 1. Navigate ───────────────────────────────────────────────────────────
    echo "→ [1/6] Navigate to facebook.com ...\n";
    $page->goto('https://www.facebook.com', 25000);
    echo "   Title : " . $page->getTitle() . "\n";
    echo "   URL   : " . $page->getUrl() . "\n";

    // Dismiss cookie popup if present
    $page->evaluate(<<<'JS'
        (() => {
            const btns = [...document.querySelectorAll('button,a')];
            const deny = btns.find(b => /decline|reject|allow only|essential/i.test(b.textContent));
            deny?.click();
        })()
    JS);
    usleep(500_000);

    // ── 2. Stealth check ──────────────────────────────────────────────────────
    echo "\n→ [2/6] Bot-detection check ...\n";
    $det = $page->checkBotDetection();
    echo $det->describe() . "\n";
    echo "   Score: \033[" . ($det->score() >= 4 ? '32' : '33') . "m" . $det->score() . "/5\033[0m\n";

    // ── 3. Find form ──────────────────────────────────────────────────────────
    echo "\n→ [3/6] Looking for login form ...\n";

    // Wait up to 5s for the form to appear
    $waited = 0;
    while ($waited < 5000) {
        $hasEmail = (bool) $page->evaluate(
            '!!document.querySelector(\'input[name="email"], #email, input[type="email"]\')'
        );
        if ($hasEmail) break;
        usleep(500_000);
        $waited += 500;
    }

    $hasPass = (bool) $page->evaluate(
        '!!document.querySelector(\'input[name="pass"], #pass, input[type="password"]\')'
    );

    if (!$hasEmail || !$hasPass) {
        echo "   \033[31m✗ Login form not found.\033[0m\n";
        $page->screenshot($shotFile);
        echo "   Screenshot: {$shotFile}\n";
        exit(1);
    }
    echo "   \033[32m✓ Email field found\033[0m\n";
    echo "   \033[32m✓ Password field found\033[0m\n";

    // ── 4. Fill form ──────────────────────────────────────────────────────────
    echo "\n→ [4/6] Filling form (React-native setter) ...\n";

    $page->type('input[name="email"]', $email);
    usleep(400_000);
    $actualEmail = (string) $page->evaluate('document.querySelector(\'input[name="email"]\').value');
    $emailOk     = $actualEmail === $email;
    echo "   email field: \033[" . ($emailOk ? '32' : '31') . "m{$actualEmail}\033[0m" . ($emailOk ? ' ✓' : ' ✗') . "\n";

    $page->type('input[name="pass"]', $password);
    usleep(400_000);
    $actualPass = (string) $page->evaluate('document.querySelector(\'input[name="pass"]\').value');
    $passOk     = strlen($actualPass) === strlen($password);
    echo "   pass  field: length=" . strlen($actualPass) . ($passOk ? " \033[32m✓\033[0m" : " \033[31m✗\033[0m") . "\n";

    // Screenshot BEFORE submit
    $beforeFile = str_replace('.png', '_before.png', $shotFile);
    $page->screenshot($beforeFile);
    echo "   Screenshot (before submit): {$beforeFile}\n";

    // ── 5. Submit form ────────────────────────────────────────────────────────
    echo "\n→ [5/6] Submitting login form ...\n";

    $urlBefore = (string) $page->evaluate('window.location.href');

    // Strategy: try multiple submit methods in order
    $submitted = (bool) $page->evaluate(<<<'JS'
        (() => {
            // Method A: form.requestSubmit() — most React-compatible
            const form = document.querySelector('[name="pass"]')?.closest('form');
            if (form) {
                try {
                    form.requestSubmit();
                    return 'requestSubmit';
                } catch (e) {}
                // Method B: form.submit()
                try {
                    form.submit();
                    return 'formSubmit';
                } catch (e) {}
            }

            // Method C: click the submit button
            const sels = [
                'button[type="submit"]',
                '[data-testid="royal_login_button"]',
                'input[type="submit"]',
                '[name="login"]',
            ];
            for (const s of sels) {
                const el = document.querySelector(s);
                if (el) { el.click(); return 'buttonClick:' + s; }
            }

            // Method D: press Enter on password field
            const pass = document.querySelector('[name="pass"]');
            if (pass) {
                pass.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', code: 'Enter', keyCode: 13, bubbles: true }));
                pass.dispatchEvent(new KeyboardEvent('keyup',   { key: 'Enter', code: 'Enter', keyCode: 13, bubbles: true }));
                return 'enterKey';
            }

            return false;
        })()
    JS);

    echo "   Submit method used: \033[36m{$submitted}\033[0m\n";

    // ── 6. Wait for Facebook's response ───────────────────────────────────────
    echo "\n→ [6/6] Waiting for Facebook response (up to 12s) ...\n";

    $redirected = false;
    $urlAfter   = $urlBefore;
    $deadline   = microtime(true) + 12;

    while (microtime(true) < $deadline) {
        usleep(500_000);
        $page->cdpPump(300);
        $urlAfter = (string) $page->evaluate('window.location.href');
        if ($urlAfter !== $urlBefore) {
            $redirected = true;
            break;
        }
    }

    $title = (string) $page->evaluate('document.title');

    // Broad Facebook error detection
    $errorText = (string) $page->evaluate(<<<'JS'
        (() => {
            // Try many possible error containers
            const candidates = [
                '[data-testid="login-error-message"]',
                '._9ay7', '.login_error_box', '#error_box',
                '[id*="error" i]', '[class*="error" i]',
                'form [role="alert"]', '[aria-live="polite"]',
                '[aria-live="assertive"]',
                'form .uiContextualLayer', '._5dbb',
                '.rfloat._ohf', '#loginError',
            ];
            for (const s of candidates) {
                try {
                    const el = document.querySelector(s);
                    const t  = el?.innerText?.trim();
                    if (t && t.length > 2) return t;
                } catch (e) {}
            }
            // Fallback: scan all text nodes for error keywords
            const walker = document.createTreeWalker(
                document.body, NodeFilter.SHOW_TEXT,
                { acceptNode: n => /incorrect|wrong|invalid|doesn.t match|try again|not recognized/i.test(n.textContent)
                    ? NodeFilter.FILTER_ACCEPT : NodeFilter.FILTER_SKIP }
            );
            const node = walker.nextNode();
            return node?.textContent?.trim() ?? '';
        })()
    JS);

    echo "   URL before : {$urlBefore}\n";
    echo "   URL after  : {$urlAfter}\n";
    echo "   Title      : {$title}\n";
    echo "   Redirected : " . ($redirected ? "\033[32mYES\033[0m" : "\033[33mNO\033[0m") . "\n";
    if ($errorText) {
        echo "   Error msg  : \033[33m{$errorText}\033[0m\n";
    }

    // ── Verdict ───────────────────────────────────────────────────────────────
    echo "\n" . str_repeat('─', 52) . "\n";

    if ($redirected && !str_contains($urlAfter, 'login') && !str_contains($urlAfter, 'checkpoint')) {
        echo "\033[32m✓ LOGIN SUCCESS — redirected to: {$urlAfter}\033[0m\n";
    } elseif (str_contains($urlAfter, 'checkpoint') || str_contains($urlAfter, '/login/device')) {
        echo "\033[33m⚠ CHECKPOINT — Facebook requires additional verification (2FA/SMS).\033[0m\n";
        echo "  The form submission WORKED but real account needs verification.\n";
    } elseif ($errorText) {
        echo "\033[31m✗ WRONG CREDENTIALS — Facebook responded: \"{$errorText}\"\033[0m\n";
        echo "\033[32m✓ CONCLUSION: Form submission & JavaScript interaction work correctly!\033[0m\n";
        echo "  Use real credentials: --email=REAL --password=REAL\n";
    } elseif ($redirected) {
        echo "\033[32m✓ REDIRECTED — possible login success (check screenshot).\033[0m\n";
    } else {
        echo "\033[33m? No redirect, no error detected — check screenshot below.\033[0m\n";
        echo "  Facebook may be rate-limiting or showing a CAPTCHA.\033[0m\n";
    }

    // Screenshot AFTER submit
    $page->screenshot($shotFile);
    $size = round(filesize($shotFile) / 1024, 1);
    echo "\nScreenshot (after submit): {$shotFile} ({$size} KB)\n";

} catch (\Throwable $e) {
    echo "\033[31m✗ " . get_class($e) . ": " . $e->getMessage() . "\033[0m\n";
    exit(1);
} finally {
    $browser->close();
    echo "Browser closed.\n";
}
