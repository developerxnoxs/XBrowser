<?php

declare(strict_types=1);

/**
 * Xbrowser — Network Intercept/Capture Example
 *
 * Setara dengan intercept.js (Puppeteer), merekam:
 *   • Semua request ke facebook.com
 *   • Deteksi kredensial di POST body
 *   • Response body untuk POST penting
 *   • Cookies setelah login
 *   • Export ke JSON
 *
 * Penggunaan:
 *   php examples/intercept_capture.php
 *   php examples/intercept_capture.php --email=081234 --password=rahasia
 *   php examples/intercept_capture.php --output=hasil.json
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use Xbrowser\Browser\BrowserFactory;

// ── CLI args ──────────────────────────────────────────────────────────────────
$opts     = getopt('', ['email:', 'password:', 'output:']);
$email    = $opts['email']    ?? '083807650503';
$password = $opts['password'] ?? 'Bulusari2580';
$output   = $opts['output']   ?? 'intercept_result.json';

function logMsg(string $msg): void
{
    $ms = (int)(microtime(true) * 1000) % 1000;
    $ts = date('H:i:s') . '.' . str_pad((string)$ms, 3, '0', STR_PAD_LEFT);
    echo "[{$ts}] {$msg}\n";
}

echo "\n\033[1mXbrowser — Network Capture (setara intercept.js)\033[0m\n";
echo str_repeat('─', 60) . "\n";
echo "Email    : {$email}\n";
echo "Output   : {$output}\n\n";

// ── Boot ──────────────────────────────────────────────────────────────────────
$browser = BrowserFactory::create(['verbose' => false]);

try {
    $browser->launch([
        'headless'   => true,
        'stealth'    => true,
        'no_sandbox' => true,
    ]);

    $page = $browser->newPage();

    // ── Siapkan capture ───────────────────────────────────────────────────────
    logMsg('Menyiapkan network capture...');

    $capture = $page->startCapture();
    $capture
        ->filterDomain('facebook.com')
        ->scanCredentials([$email, 'encpass', 'pass=', 'password']);

    // ── Step 1: Buka halaman awal (ambil datr, cookie awal) ───────────────────
    logMsg('Step 1: Buka m.facebook.com...');
    $page->setUserAgent(
        'Mozilla/5.0 (Linux; Android 10; Redmi Note 8 Build/QKQ1.200114.002; wv) ' .
        'AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 ' .
        'Chrome/132.0.6834.163 Mobile Safari/537.36'
    );
    $page->setExtraHeaders(['accept-language' => 'id-ID,id;q=0.9,en-US;q=0.8,en;q=0.7']);

    $page->goto('https://m.facebook.com/', 30000);
    usleep(1_500_000);

    $cookies0 = $page->getCookies();
    logMsg('Cookies awal: ' . implode(', ', array_column($cookies0, 'name')));

    // ── Step 2: Buka halaman login ────────────────────────────────────────────
    logMsg('Step 2: Buka halaman login...');
    $page->goto('https://m.facebook.com/login/', 30000);
    usleep(2_000_000);

    logMsg('Judul: ' . $page->getTitle());

    // Ambil token dari halaman
    $pageData = $page->evaluate(<<<'JS'
        (() => {
            const html = document.documentElement.outerHTML;
            return {
                lsd:        (html.match(/"LSD",\[\],\{"token":"([^"]+)"\}/) || [])[1] || null,
                jazoest:    (html.match(/jazoest[^0-9]{0,10}(\d{8,})/) || [])[1] || null,
                public_key: (html.match(/"public_key"\s*:\s*"([0-9a-f]{64})"/) || [])[1] || null,
                key_id:     (html.match(/"key_id"\s*:\s*(\d+)/) || [])[1] || null,
                inputs:     [...document.querySelectorAll('input')].map(el => ({
                    type: el.type, name: el.name, id: el.id, value: el.value?.slice(0,30)
                })),
                forms:      [...document.querySelectorAll('form')].map(f => ({
                    action: f.action, method: f.method
                })),
            };
        })()
    JS);

    log("=== PAGE DATA ===");
    log("lsd        : " . ($pageData['lsd'] ?? 'null'));
    log("jazoest    : " . ($pageData['jazoest'] ?? 'null'));
    log("public_key : " . ($pageData['public_key'] ?? 'null'));
    log("key_id     : " . ($pageData['key_id'] ?? 'null'));
    log("Inputs     : " . json_encode($pageData['inputs'] ?? []));

    // Screenshot halaman login
    $page->screenshot('login_page.png');
    logMsg('Screenshot: login_page.png');

    // ── Step 3: Isi form login ─────────────────────────────────────────────────
    $hasEmail = (bool) $page->evaluate(
        '!!document.querySelector(\'input[name="email"], input[type="email"]\')'
    );
    $hasPass = (bool) $page->evaluate(
        '!!document.querySelector(\'input[name="pass"], input[type="password"]\')'
    );

    if (!$hasEmail || !$hasPass) {
        logMsg('[!] Form login tidak ditemukan!');
        exit(1);
    }

    logMsg('Mengisi email...');
    $page->type('input[name="email"]', $email);
    usleep(400_000);

    logMsg('Mengisi password...');
    $page->type('input[name="pass"]', $password);
    usleep(400_000);

    $page->screenshot('before_submit.png');
    logMsg('Screenshot: before_submit.png');

    // ── Step 4: Submit form ────────────────────────────────────────────────────
    logMsg('Submit form login...');
    $urlBefore = (string) $page->evaluate('window.location.href');

    $submitMethod = $page->evaluate(<<<'JS'
        (() => {
            const form = document.querySelector('[name="pass"]')?.closest('form');
            if (form) {
                try { form.requestSubmit(); return 'requestSubmit'; } catch(e){}
                try { form.submit(); return 'formSubmit'; } catch(e){}
            }
            const sels = ['button[type="submit"]','[data-testid="royal_login_button"]',
                          'input[type="submit"]','[name="login"]'];
            for (const s of sels) {
                const el = document.querySelector(s);
                if (el) { el.click(); return 'buttonClick:' + s; }
            }
            return 'notFound';
        })()
    JS);

    log("Metode submit: {$submitMethod}");

    // Tunggu respons Facebook
    $redirected = false;
    $urlAfter   = $urlBefore;
    $deadline   = microtime(true) + 15;

    while (microtime(true) < $deadline) {
        usleep(500_000);
        $page->cdpPump(300);
        $urlAfter = (string) $page->evaluate('window.location.href');
        if ($urlAfter !== $urlBefore) {
            $redirected = true;
            break;
        }
    }

    // Ambil cookies setelah login
    $cookies  = $page->getCookies();
    $cUser    = null;
    $xs       = null;
    foreach ($cookies as $c) {
        if ($c['name'] === 'c_user') $cUser = $c['value'];
        if ($c['name'] === 'xs')     $xs    = $c['value'];
    }

    log("URL sebelum : {$urlBefore}");
    log("URL setelah : {$urlAfter}");
    log("Redirect    : " . ($redirected ? 'YA' : 'TIDAK'));
    log("c_user      : " . ($cUser ?? 'tidak ada'));

    $page->screenshot('after_login.png');
    logMsg('Screenshot: after_login.png');

    // ── Step 5: Ambil body yang tertunda ──────────────────────────────────────
    logMsg('Mengambil response body yang tertunda...');
    $page->cdpPump(1000);
    $capture->fetchPendingBodies();

    // ── Simpan data login ──────────────────────────────────────────────────────
    $afterInfo = [
        'url'     => $urlAfter,
        'c_user'  => $cUser,
        'xs'      => $xs,
        'cookies' => array_map(fn($c) => $c['name'] . '=' . $c['value'], $cookies),
    ];
    file_put_contents('after_login_data.json', json_encode($afterInfo, JSON_PRETTY_PRINT));
    logMsg('Disimpan: after_login_data.json');

    // ── Step 6: Simpan hasil capture ──────────────────────────────────────────
    $capture->saveJson($output);

    $summary = $capture->summary();
    $credReqs = $capture->getWithCredentials();
    $bzPosts  = $capture->findByUrl('/a/bz');

    echo "\n" . str_repeat('─', 60) . "\n";
    echo "\033[1m=== RINGKASAN CAPTURE ===\033[0m\n";
    printf("Total request FB  : %d\n", $summary['total']);
    printf("POST requests     : %d\n", $summary['posts']);
    printf("Dengan kredensial : %d\n", $summary['withCredentials']);
    printf("Response sukses   : %d\n", $summary['succeeded']);
    printf("Response body     : %d\n", $summary['bodyFetched']);

    if (!empty($bzPosts)) {
        echo "\nPOST ke /a/bz:\n";
        foreach ($bzPosts as $i => $r) {
            printf("  %d. %s\n", $i + 1, mb_substr($r->url, 0, 100));
            if ($r->postData) {
                printf("     body: %s\n", mb_substr($r->postData, 0, 200));
            }
            if ($r->responseBody) {
                printf("     resp: %s\n", mb_substr($r->responseBody, 0, 200));
            }
        }
    }

    if (!empty($credReqs)) {
        echo "\nRequest dengan kredensial:\n";
        foreach ($credReqs as $r) {
            printf("  %s %s\n", $r->method, mb_substr($r->url, 0, 100));
            printf("  body: %s\n", mb_substr($r->postData, 0, 400));
        }
    }

    // Verdict
    echo "\n" . str_repeat('─', 60) . "\n";
    if ($cUser) {
        echo "\033[32m✓ LOGIN BERHASIL! c_user={$cUser}\033[0m\n";
    } elseif (str_contains($urlAfter, 'checkpoint')) {
        echo "\033[33m⚠ CHECKPOINT — perlu verifikasi tambahan (2FA/SMS)\033[0m\n";
    } else {
        echo "\033[31m✗ Login gagal — tidak ada c_user\033[0m\n";
    }

    printf("\n\033[32m✓\033[0m Hasil intercept disimpan ke: %s\n\n", $output);

} catch (\Throwable $e) {
    echo "\n\033[31m✗ " . get_class($e) . ": " . $e->getMessage() . "\033[0m\n";
    if (isset($e)) {
        echo $e->getTraceAsString() . "\n";
    }
    exit(1);
} finally {
    $browser->close();
    echo "Browser ditutup.\n";
}
