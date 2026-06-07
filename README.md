# Xbrowser

Browser terminal berbasis **Chrome DevTools Protocol (CDP)** dan Headless Chromium. Jelajahi, otomasi, dan intercept halaman web modern — termasuk situs berbasis React, Vue, Next.js — langsung dari terminal atau lewat PHP API.

```
  ██╗  ██╗██████╗ ██████╗  ██████╗ ██╗    ██╗███████╗███████╗██████╗
  ╚██╗██╔╝██╔══██╗██╔══██╗██╔═══██╗██║    ██║██╔════╝██╔════╝██╔══██╗
   ╚███╔╝ ██████╔╝██████╔╝██║   ██║██║ █╗ ██║███████╗█████╗  ██████╔╝
   ██╔██╗ ██╔══██╗██╔══██╗██║   ██║██║███╗██║╚════██║██╔══╝  ██╔══██╗
  ██╔╝ ██╗██████╔╝██║  ██║╚██████╔╝╚███╔███╔╝███████║███████╗██║  ██║
  ╚═╝  ╚═╝╚═════╝ ╚═╝  ╚═╝ ╚═════╝  ╚══╝╚══╝ ╚══════╝╚══════╝╚═╝  ╚═╝
```

---

## Daftar Isi

- [Persyaratan](#persyaratan)
- [Instalasi](#instalasi)
- [Quick Start](#quick-start)
- [CLI Commands](#cli-commands)
- [Interactive Shell](#interactive-shell)
- [Automation API](#automation-api)
  - [Navigasi](#navigasi)
  - [Elemen DOM](#elemen-dom)
  - [JavaScript](#javascript)
  - [Form & Input](#form--input)
  - [Screenshot](#screenshot)
  - [Cookie & Storage](#cookie--storage)
  - [Headers, User-Agent & Viewport](#headers-user-agent--viewport)
  - [Rekam & Putar Ulang](#rekam--putar-ulang)
- [Network Capture](#network-capture)
- [Request Interception](#request-interception)
- [Stealth Mode & Bot Detection](#stealth-mode--bot-detection)
- [Session Management](#session-management)
- [Sistem Event](#sistem-event)
- [Plugin](#plugin)
- [Konfigurasi](#konfigurasi)
- [Docker](#docker)
- [Terminal Renderer](#terminal-renderer)
- [Struktur Project](#struktur-project)
- [Menjalankan Tests](#menjalankan-tests)
- [Troubleshooting](#troubleshooting)
- [Arsitektur](#arsitektur)
- [Lisensi](#lisensi)

---

## Persyaratan

- PHP **8.4+**
- Composer
- Chromium atau Google Chrome
- OS: Linux, macOS, atau Android (Termux)

---

## Instalasi

```bash
git clone https://github.com/your-org/xbrowser.git
cd xbrowser
composer install
chmod +x bin/Xbrowser
```

Opsional — daftarkan sebagai command global:

```bash
sudo ln -s "$(pwd)/bin/Xbrowser" /usr/local/bin/Xbrowser
```

---

## Quick Start

```bash
# Buka URL dan render ke terminal
Xbrowser open https://example.com

# Mulai interactive shell
Xbrowser shell

# Jalankan tanpa install (via Docker)
docker run -it --rm --shm-size=256m --cap-add=SYS_ADMIN xbrowser:latest shell
```

---

## CLI Commands

### `open` — Buka URL

```bash
Xbrowser open https://github.com
```

Meluncurkan Chromium, memuat halaman (termasuk JavaScript), lalu merender hasilnya di terminal.

---

### `click` — Klik elemen

```bash
Xbrowser click https://example.com "#login-button"
```

---

### `type` — Ketik ke elemen

```bash
Xbrowser type https://example.com "#email" "user@example.com"
```

---

### `eval` — Evaluasi JavaScript

```bash
Xbrowser eval https://example.com "document.title"
Xbrowser eval https://example.com "document.querySelectorAll('a').length"
```

---

### `screenshot` — Ambil screenshot

```bash
Xbrowser screenshot https://example.com halaman.png
Xbrowser screenshot https://example.com halaman.jpg
```

---

### `html` — Dump HTML mentah

```bash
Xbrowser html https://example.com
Xbrowser html https://example.com > halaman.html
```

---

### `network` — Inspeksi network traffic

```bash
Xbrowser network https://example.com
```

Menampilkan semua request beserta method, status code, dan URL-nya.

---

### `save-session` / `load-session` — Manajemen sesi

```bash
# Simpan cookies, localStorage, dan URL saat ini
Xbrowser save-session https://github.com sesi-saya

# Muat sesi yang tersimpan
Xbrowser load-session sesi-saya
```

Sesi disimpan di `~/.xbrowser/sessions/`.

---

### `record` — Rekam aksi

```bash
Xbrowser record
```

Membuka perekam interaktif. Setiap aksi yang kamu lakukan dikonversi menjadi PHP automation script:

```php
$page->goto('https://example.com');
$page->click('#login');
$page->type('#email', 'user@example.com');
```

---

## Interactive Shell

```bash
Xbrowser shell
```

Membuka REPL-style browser shell:

```
Xbrowser v1.0.0 — Terminal Browser
Ketik 'help' untuk daftar perintah.

[Xbrowser] > open https://github.com
[GitHub · Build and ship software...] > render
[GitHub · ...] > click a[href="/login"]
[GitHub · ...] > type #login_field myuser
[GitHub · ...] > type #password mysecret
[GitHub · ...] > submit
[GitHub · ...] > screenshot dashboard.png
[GitHub · ...] > save-session github-sesi
[GitHub · ...] > quit
```

### Daftar Perintah Shell

| Perintah                 | Keterangan                           |
|--------------------------|--------------------------------------|
| `open <url>`             | Navigasi ke URL                      |
| `render`                 | Render halaman ke terminal           |
| `html`                   | Dump HTML mentah                     |
| `click <selector>`       | Klik elemen CSS selector             |
| `type <selector> <teks>` | Ketik ke dalam elemen                |
| `eval <ekspresi>`        | Evaluasi JavaScript                  |
| `screenshot <file>`      | Ambil screenshot                     |
| `submit [selector]`      | Submit form                          |
| `back`                   | Kembali                              |
| `forward`                | Maju                                 |
| `reload`                 | Reload halaman                       |
| `wait <selector>`        | Tunggu elemen muncul                 |
| `cookies`                | Tampilkan cookies saat ini           |
| `title`                  | Tampilkan judul halaman              |
| `url`                    | Tampilkan URL saat ini               |
| `save-session <nama>`    | Simpan sesi browser                  |
| `load-session <nama>`    | Muat sesi yang tersimpan             |
| `network`                | Tampilkan ringkasan network          |
| `clear`                  | Bersihkan terminal                   |
| `help`                   | Tampilkan daftar perintah            |
| `quit` / `exit`          | Tutup dan keluar                     |

---

## Automation API

Xbrowser dapat digunakan sebagai library PHP untuk otomasi browser — mirip Playwright atau Puppeteer.

```php
<?php

require 'vendor/autoload.php';

use Xbrowser\Browser\BrowserFactory;

$browser = BrowserFactory::create(['verbose' => true]);
$browser->launch();

$page = $browser->newPage();

// ... aksi otomasi ...

$browser->close();
```

> **BrowserFactory::create(array $options)** — cara yang direkomendasikan untuk membuat instance Browser.
> Secara otomatis mengatur Logger, EventDispatcher, ConfigManager, dan PluginManager.
>
> Option yang tersedia: `verbose`, `logFile`, `configFile`, `pluginDir`, `launch` (bool — langsung launch).

---

### Navigasi

```php
// Buka URL (tunggu hingga document.readyState === 'complete')
$page->goto('https://example.com');
$page->goto('https://example.com', timeoutMs: 60000); // timeout custom

// Navigasi relatif
$page->goBack();
$page->goForward();
$page->reload();
$page->reload(ignoreCache: true);

// Tunggu navigasi selesai (berguna setelah klik link)
$page->waitForNavigation();

// Tunggu halaman selesai load
$page->waitForLoad(timeoutMs: 30000);

// Ambil info halaman
echo $page->getUrl();    // URL saat ini
echo $page->getTitle();  // Judul halaman
```

---

### Elemen DOM

```php
use Xbrowser\DOM\Element;

// Query satu elemen (melempar SelectorNotFoundException jika tidak ada)
$element = $page->query('#login-button');
$element = $page->query('form input[type="email"]');

// Query semua elemen yang cocok
$links = $page->queryAll('a[href]');
foreach ($links as $link) {
    // $link adalah instance Element
}

// Tunggu elemen muncul (polling hingga timeout)
$el = $page->waitForSelector('.dashboard', timeoutMs: 10000);

// Ambil HTML halaman
$html = $page->html();

// Render ke terminal
echo $page->render();
```

---

### JavaScript

```php
// Evaluasi ekspresi JavaScript — mengembalikan nilai PHP
$title  = $page->evaluate('document.title');                       // string
$count  = $page->evaluate('document.querySelectorAll("a").length'); // int
$exists = $page->evaluate('!!document.querySelector(".modal")');   // bool

// Evaluasi multi-baris
$data = $page->evaluate(<<<JS
    Array.from(document.querySelectorAll('.item'))
        .map(el => ({ text: el.textContent.trim(), href: el.href ?? '' }))
JS);

// $data adalah array PHP siap pakai
foreach ($data as $item) {
    echo $item['text'] . ' → ' . $item['href'] . "\n";
}
```

> JavaScript dieksekusi di konteks halaman. Promise otomatis di-await. Jika ekspresi melempar error, `JavaScriptException` akan dilempar.

---

### Form & Input

```php
// Klik elemen (mensimulasikan mouse click + JS click handler)
$page->click('#login-button');
$page->click('button[type="submit"]');

// Ketik ke input (kompatibel dengan React, Vue, Angular)
$page->type('#email', 'user@example.com');
$page->type('#password', 'rahasia123');

// Submit form
$page->submit('#login-form');
$page->submit(); // default: elemen 'form' pertama
```

> `type()` menggunakan native prototype setter sehingga framework controlled input (React, Vue) bisa mendeteksi perubahan dengan benar.

---

### Screenshot

```php
// Screenshot ke file (format auto-detect dari ekstensi)
$page->screenshot('output/halaman.png');
$page->screenshot('output/halaman.jpg', format: 'jpeg');

// Ubah ukuran viewport sebelum screenshot
$page->setViewport(1920, 1080);
$page->screenshot('output/fullhd.png');
```

---

### Cookie & Storage

```php
// Baca cookies
$cookies = $page->getCookies();
foreach ($cookies as $cookie) {
    echo $cookie['name'] . '=' . $cookie['value'] . "\n";
}

// Set cookies
$page->setCookies([
    ['name' => 'token', 'value' => 'abc123', 'domain' => 'example.com'],
]);

// Hapus semua cookies
$page->clearCookies();

// Baca localStorage
$storage = $page->getLocalStorage();
echo $storage['user_id'] ?? 'tidak ada';

// Baca sessionStorage
$session = $page->getSessionStorage();
```

---

### Headers, User-Agent & Viewport

```php
// Tambahkan header ke semua request berikutnya
$page->setExtraHeaders([
    'Authorization'  => 'Bearer token-saya',
    'X-Custom-Header'=> 'nilai',
]);

// Ganti User-Agent
$page->setUserAgent(
    'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 ' .
    '(KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36'
);

// Atur ukuran viewport (default 1280x800)
$page->setViewport(1366, 768);
$page->setViewport(375, 812); // simulasi mobile
```

---

### Rekam & Putar Ulang

```php
// Mulai perekaman
$page->startRecording();

$page->goto('https://example.com');
$page->click('#login');
$page->type('#email', 'user@example.com');
$page->submit();

// Hentikan dan dapatkan script PHP
$script = $page->stopRecording();
file_put_contents('script_saya.php', $script);

// Akses raw array aksi yang direkam
$actions = $page->getRecordedActions();
```

Script yang dihasilkan siap dijalankan ulang:

```php
<?php

require 'vendor/autoload.php';

use Xbrowser\Browser\Browser;

$browser = new Browser(...);
$page    = $browser->newPage();

$page->goto('https://example.com');
$page->click('#login');
$page->type('#email', 'user@example.com');
$page->submit();

$browser->close();
```

---

## Network Capture

`startCapture()` merekam semua network traffic secara **pasif** — tidak menghentikan request.

```php
<?php

use Xbrowser\Browser\BrowserFactory;

$browser = BrowserFactory::create();
$browser->launch();

$page    = $browser->newPage();
$capture = $page->startCapture();

// Filter domain tertentu saja
$capture->filterDomain('api.example.com');

// Deteksi otomatis kredensial di POST body
$capture->scanCredentials(['password', 'pass=', 'token']);

// Nonaktifkan fetch body otomatis (hemat memori)
$capture->disableLiveBodyFetch();

$page->goto('https://example.com');
$page->click('#login');
$page->type('#email', 'user@example.com');
$page->type('#password', 'rahasia');
$page->submit();

// Ambil body yang tertunda setelah aksi selesai
$capture->fetchPendingBodies();

// Akses data
$semua   = $capture->getAll();           // semua entry
$posts   = $capture->getPosts();         // hanya POST
$creds   = $capture->getWithCredentials(); // yang mengandung kredensial
$api     = $capture->findByUrl('/api/');  // filter by URL pattern

// Statistik
print_r($capture->summary());
// ['total' => 42, 'posts' => 5, 'withCredentials' => 1, 'succeeded' => 40, 'failed' => 2, ...]

// Simpan ke JSON
$capture->saveJson('output/capture.json');

// Reset data
$capture->clear();

$browser->close();
```

### Struktur CapturedEntry

Setiap entry yang direkam memiliki properti berikut:

| Properti | Tipe | Keterangan |
|----------|------|-----------|
| `requestId` | string | ID unik request CDP |
| `method` | string | HTTP method (GET, POST, dll.) |
| `url` | string | URL request |
| `requestHeaders` | array | Request headers |
| `postData` | string | Body POST (jika ada) |
| `resourceType` | string | Tipe resource (XHR, Document, Image, dll.) |
| `hasCredentials` | bool | Apakah POST data mengandung kredensial |
| `responseStatus` | int | HTTP status code response |
| `responseHeaders` | array | Response headers |
| `responseBody` | string | Response body (max 5000 karakter) |
| `bodyFetched` | bool | Apakah body sudah diambil |
| `capturedAt` | float | Timestamp (microtime) |

---

## Request Interception

`intercept()` menghentikan setiap request **sebelum dikirim** ke server dan menunggu keputusan handler.

> Berbeda dari `startCapture()` yang hanya mengamati (pasif), `intercept()` bisa memblokir, memodifikasi, atau membalas request dengan response buatan.

```php
<?php

use Xbrowser\Browser\BrowserFactory;
use Xbrowser\Networking\InterceptedRequest;

$browser = BrowserFactory::create();
$browser->launch();
$page = $browser->newPage();
```

### 1. Blokir gambar & iklan

```php
$interceptor = $page->intercept();
$interceptor
    ->blockResourceTypes(['Image', 'Media', 'Font'])  // berdasarkan tipe
    ->blockAds();                                      // tracker umum

$page->goto('https://example.com');
print_r($interceptor->getStats());
// ['total' => 35, 'blocked' => 18, 'allowed' => 17, ...]

$interceptor->disable();
```

### 2. Blokir URL tertentu

```php
$interceptor = $page->intercept();
$interceptor->blockUrls(['analytics', 'hotjar', 'pixel.facebook']);

$page->goto('https://example.com');
$interceptor->disable();
```

### 3. Inject header ke setiap request

```php
$interceptor = $page->intercept();
$interceptor->onRequest(function (InterceptedRequest $req): void {
    $req->continue([
        'headers' => array_merge($req->headers, [
            'Authorization'  => 'Bearer token-saya',
            'X-Xbrowser'     => '1.0',
        ]),
    ]);
});

$page->goto('https://httpbin.org/headers');
$interceptor->disable();
```

### 4. Mock respons API

```php
$interceptor = $page->intercept();
$interceptor->onRequest(function (InterceptedRequest $req): void {
    if (str_contains($req->url, '/api/user')) {
        $req->respond([
            'status'   => 200,
            'mimeType' => 'application/json',
            'body'     => json_encode(['id' => 1, 'name' => 'Mock User']),
        ]);
    } else {
        $req->continue();
    }
});

$page->goto('https://example.com');
$interceptor->disable();
```

### 5. Redirect URL

```php
$interceptor = $page->intercept();
$interceptor->onRequest(function (InterceptedRequest $req): void {
    $url = str_replace('cdn.lama.com', 'cdn.baru.com', $req->url);
    $req->continue(['url' => $url]);
});

$page->goto('https://example.com');
$interceptor->disable();
```

### 6. Intercept XHR saja

```php
$interceptor = $page->intercept();
$interceptor
    ->interceptXhr()  // hanya XHR + Fetch
    ->onRequest(function (InterceptedRequest $req): void {
        echo "[{$req->method}] {$req->url}\n";
        $req->continue();
    });

$page->goto('https://example.com');
$interceptor->disable();
```

### API — RequestInterceptor

| Method | Keterangan |
|--------|-----------|
| `onRequest(callable)` | Daftarkan handler untuk setiap request |
| `blockResourceTypes(string[])` | Blokir berdasarkan tipe (Image, Script, Font, Media, dll.) |
| `blockUrls(string[])` | Blokir URL yang mengandung substring |
| `addHeaders(array)` | Inject header ke semua request |
| `blockAds()` | Blokir tracker & iklan (daftar domain bawaan) |
| `interceptAll()` | Intercept semua request (default) |
| `interceptXhr()` | Intercept hanya XHR dan Fetch |
| `interceptStatic()` | Intercept resource statis (Image, Stylesheet, Font) |
| `addPattern(urlPattern, ?resourceType)` | Tambah pola URL/tipe kustom |
| `getStats()` | Statistik: total, blocked, modified, mocked, allowed |
| `resetStats()` | Reset statistik |
| `disable()` | Matikan interceptor |
| `isEnabled()` | Cek apakah aktif |

### API — InterceptedRequest

Di dalam handler, **wajib** memanggil tepat satu aksi:

| Method | Keterangan |
|--------|-----------|
| `$req->continue(array $overrides = [])` | Teruskan request. Override opsional: `url`, `method`, `headers`, `postData` |
| `$req->abort(string $reason = 'BlockedByClient')` | Batalkan request |
| `$req->respond(array $response)` | Balas dengan response buatan: `status`, `headers`, `body`, `mimeType` |
| `$req->isHandled()` | Cek apakah sudah ditangani |

**Properti InterceptedRequest (readonly):**

| Properti | Tipe | Keterangan |
|----------|------|-----------|
| `url` | string | URL request |
| `method` | string | HTTP method |
| `headers` | array | Request headers |
| `postData` | string | Request body |
| `resourceType` | string | Tipe: Document, XHR, Fetch, Image, Script, Stylesheet, Font, Media, dll. |
| `requestId` | string | ID unik CDP |
| `frameId` | string | ID frame asal |

---

## Stealth Mode & Bot Detection

Xbrowser aktifkan Stealth Mode secara default untuk menghindari deteksi bot.

```php
$browser = BrowserFactory::create();
$browser->launch(['stealth' => true]); // default: true
```

Patch yang diterapkan secara otomatis:
- `navigator.webdriver` → disembunyikan (`undefined`)
- `navigator.plugins` → disimulasikan (3 plugin palsu)
- `navigator.languages` → `['id-ID', 'id', 'en-US', 'en']`
- `window.chrome` → object palsu agar terlihat seperti Chrome biasa
- User-Agent → menghapus string `HeadlessChrome`

### Cek Status Bot Detection

```php
$result = $page->checkBotDetection();

echo $result->describe();
// ✓ navigator.webdriver hidden
// ✓ navigator.plugins present (3 plugins)
// ✓ window.chrome object present
// ✓ navigator.languages present
// ✓ User-Agent clean (no "HeadlessChrome")

echo "Skor: " . $result->score() . "/5\n";
echo "Lulus: " . ($result->isPassed() ? 'Ya' : 'Tidak') . "\n";
echo "User-Agent: " . $result->userAgent . "\n";

// Akses detail cek individual
var_dump($result->webdriverHidden);
var_dump($result->pluginsPresent);
var_dump($result->chromeObjectPresent);
var_dump($result->languagesPresent);
var_dump($result->userAgentClean);
var_dump($result->rawChecks); // semua nilai mentah dari browser
```

---

## Session Management

Simpan dan pulihkan state browser (cookies, localStorage, sessionStorage, URL).

```php
<?php

use Xbrowser\Browser\BrowserFactory;
use Xbrowser\Utils\SessionManager;

$browser  = BrowserFactory::create();
$sessions = new SessionManager(); // default: ~/.xbrowser/sessions/

$browser->launch();
$page = $browser->newPage();

// Login sekali
$page->goto('https://example.com/login');
$page->type('#email', 'user@example.com');
$page->type('#password', 'rahasia');
$page->submit();
$page->waitForSelector('.dashboard');

// Simpan sesi setelah login berhasil
$page->saveSession('sesi-login', $sessions);

// --- sesi berikutnya ---

// Muat sesi (memulihkan cookies + navigasi ke URL terakhir)
$page->loadSession('sesi-login', $sessions);
// Sekarang sudah login tanpa perlu input ulang

$browser->close();
```

---

## Sistem Event

```php
<?php

use Xbrowser\Browser\BrowserFactory;
use Xbrowser\Events\PageLoadedEvent;
use Xbrowser\Events\NavigationEvent;
use Xbrowser\Events\ClickEvent;
use Xbrowser\Events\JavaScriptExecutedEvent;

$browser    = BrowserFactory::create();
$dispatcher = $browser->getDispatcher();

// Langganan event
$dispatcher->on('page.loaded', function (PageLoadedEvent $event): void {
    echo "Halaman dimuat: " . $event->getPayload()['url'] . "\n";
});

$dispatcher->on('page.navigation', function (NavigationEvent $event): void {
    $p = $event->getPayload();
    echo "Navigasi: {$p['from']} → {$p['to']}\n";
});

$dispatcher->on('element.clicked', function (ClickEvent $event): void {
    $p = $event->getPayload();
    echo "Diklik: {$p['selector']} di ({$p['x']}, {$p['y']})\n";
});

// Hanya dipanggil sekali
$dispatcher->once('page.loaded', function ($event): void {
    echo "Ini hanya muncul sekali.\n";
});

// Wildcard — semua event
$dispatcher->on('*', function ($event): void {
    echo "[event] " . $event->getName() . "\n";
});

// Hapus listener tertentu
$handler = function ($event) { /* ... */ };
$dispatcher->on('page.loaded', $handler);
$dispatcher->off('page.loaded', $handler);

// Hapus semua listener suatu event
$dispatcher->removeAll('page.loaded');

$browser->launch();
$page = $browser->newPage();
$page->goto('https://example.com');
$browser->close();
```

### Daftar Event

| Nama Event | Class | Kapan Dipicu |
|------------|-------|-------------|
| `page.loaded` | `PageLoadedEvent` | Halaman selesai dimuat |
| `page.navigation` | `NavigationEvent` | URL berubah |
| `element.clicked` | `ClickEvent` | `$page->click()` dipanggil |
| `dom.updated` | `DomUpdatedEvent` | Mutasi DOM terdeteksi |
| `javascript.executed` | `JavaScriptExecutedEvent` | `$page->evaluate()` dipanggil |
| `network.request` | `NetworkRequestEvent` | Request network dikirim |
| `*` | — | Semua event (wildcard) |

---

## Plugin

Buat direktori di `plugins/` dengan file `plugin.php` yang mengembalikan instance `PluginInterface`:

```
plugins/
└── plugin-saya/
    └── plugin.php
```

```php
<?php
// plugins/plugin-saya/plugin.php

use Xbrowser\Browser\Browser;
use Xbrowser\Plugin\PluginInterface;

return new class implements PluginInterface
{
    public function getName(): string        { return 'plugin-saya'; }
    public function getVersion(): string     { return '1.0.0'; }
    public function getDescription(): string { return 'Plugin kustom saya'; }

    public function register(Browser $browser): void
    {
        $browser->getDispatcher()->on('page.loaded', function ($event): void {
            $url = $event->getPayload()['url'];
            echo "[plugin-saya] Halaman dimuat: {$url}\n";
        });
    }

    public function getCommands(): array
    {
        return [
            'salam' => function (array $args): void {
                echo "Halo dari plugin-saya!\n";
            },
        ];
    }
};
```

Plugin di-discover otomatis dari direktori `plugins/` saat startup.

Daftarkan direktori plugin kustom:

```php
$browser = BrowserFactory::create([
    'pluginDir' => '/path/ke/plugins-saya',
]);
```

---

## Konfigurasi

### File konfigurasi

Xbrowser membaca konfigurasi dari `~/.xbrowser/config.json`:

```json
{
    "chromium_path":         "/usr/bin/chromium",
    "remote_debugging_port": 9222,
    "timeout":               30000,
    "headless":              true,
    "stealth":               true,
    "disable_gpu":           true,
    "no_sandbox":            false,
    "window_width":          1280,
    "window_height":         800,
    "verbose":               false,
    "log_file":              "",
    "plugin_dir":            "",
    "user_data_dir":         ""
}
```

### Environment Variables

| Variable | Default | Keterangan |
|----------|---------|-----------|
| `XBROWSER_CHROMIUM` | auto-detect | Path ke binary Chromium |
| `XBROWSER_PORT` | `9222` | Port remote debugging |
| `XBROWSER_TIMEOUT` | `30000` | Timeout default (ms) |
| `XBROWSER_HEADLESS` | `true` | Mode headless |
| `XBROWSER_STEALTH` | `true` | Aktifkan stealth mode |
| `XBROWSER_NO_SANDBOX` | `false` | Nonaktifkan sandbox (wajib `true` di Docker/CI) |
| `XBROWSER_VERBOSE` | `false` | Aktifkan log debug |
| `XBROWSER_LOG_FILE` | `""` | Path file log |
| `XBROWSER_PLUGIN_DIR` | `""` | Direktori plugin kustom |

---

## Docker

Xbrowser tersedia sebagai Docker image — PHP 8.4 + Chromium sudah tersedia di dalam container.

### Build image

```bash
docker build -t xbrowser:latest .
```

### Interactive shell

```bash
docker run -it --rm \
  --shm-size=256m \
  --cap-add=SYS_ADMIN \
  --security-opt seccomp=unconfined \
  xbrowser:latest shell
```

### Jalankan script automation

```bash
docker run --rm \
  --shm-size=256m \
  --cap-add=SYS_ADMIN \
  --security-opt seccomp=unconfined \
  -v "$(pwd)/examples:/app/examples" \
  -v "$(pwd)/output:/app/output" \
  xbrowser:latest php examples/scraping.php
```

### Gunakan docker compose

```bash
# Interactive shell
docker compose up xbrowser

# Jalankan script custom
docker compose run xbrowser-run php examples/request_intercept.php

# Build ulang setelah ada perubahan kode
docker compose build
```

### Environment variables di Docker

| Variable | Default | Keterangan |
|----------|---------|-----------|
| `XBROWSER_CHROMIUM` | `/usr/bin/chromium` | Path Chromium di dalam container |
| `XBROWSER_NO_SANDBOX` | `true` | Wajib `true` di container |
| `XBROWSER_HEADLESS` | `true` | Mode headless |
| `XBROWSER_TIMEOUT` | `30000` | Timeout default (ms) |
| `XBROWSER_VERBOSE` | `false` | Aktifkan log debug |

> **Penting:** Chromium butuh shared memory yang cukup. Selalu gunakan `--shm-size=256m` atau lebih, atau Chromium bisa crash saat render halaman berat.

---

## Terminal Renderer

HTML dikonversi ke representasi teks terminal:

| Elemen HTML | Output Terminal |
|-------------|----------------|
| `<h1>` | `# JUDUL` |
| `<h2>` | `## JUDUL` |
| `<h3>` | `### JUDUL` |
| `<a>` | `[Teks Link](url)` |
| `<button>` | `[ Tombol ]` |
| `<input>` | `[__placeholder______]` |
| `<textarea>` | Kotak multi-baris |
| `<ul>/<li>` | `• Butir` |
| `<ol>/<li>` | `1. Butir bernomor` |
| `<table>` | Tabel ASCII bertepi |
| `<img>` | `[IMAGE: alt text]` |
| `<hr>` | `────────────────────` |
| `<pre>` | Blok kode bertepi |
| `<blockquote>` | `▌ Teks kutipan` |
| `<strong>` | Teks tebal (ANSI bold) |
| `<code>` | `` `kode inline` `` |

---

## Struktur Project

```
xbrowser/
├── bin/
│   └── Xbrowser                   ← Entrypoint CLI
├── src/
│   ├── Browser/
│   │   ├── Browser.php            ← Kelola proses Chromium & halaman
│   │   ├── BrowserFactory.php     ← Factory dengan DI otomatis
│   │   ├── Page.php               ← API utama otomasi halaman
│   │   ├── BotDetectionResult.php ← Hasil cek bot detection
│   │   └── StealthMode.php        ← Injeksi patch anti-deteksi
│   ├── CDP/
│   │   ├── Client.php             ← CDP message client & event dispatcher
│   │   ├── Protocol.php           ← Builder perintah CDP
│   │   └── WebSocketClient.php    ← Pure PHP WebSocket RFC 6455
│   ├── DOM/
│   │   ├── DOMManager.php         ← Query DOM via CDP
│   │   └── Element.php            ← Wrapper elemen DOM
│   ├── Renderer/
│   │   ├── HtmlParser.php         ← Parser HTML pure PHP
│   │   └── TerminalRenderer.php   ← Konversi HTML → teks terminal
│   ├── Commands/
│   │   ├── Application.php        ← CLI router
│   │   ├── OpenCommand.php
│   │   ├── ClickCommand.php
│   │   ├── TypeCommand.php
│   │   ├── EvalCommand.php
│   │   ├── ScreenshotCommand.php
│   │   ├── HtmlCommand.php
│   │   ├── NetworkCommand.php
│   │   ├── SessionCommand.php
│   │   ├── RecordCommand.php
│   │   ├── CaptureCommand.php
│   │   └── ShellCommand.php       ← Interactive shell
│   ├── Networking/
│   │   ├── NetworkCapture.php     ← Rekam traffic (pasif)
│   │   ├── NetworkInspector.php   ← Inspeksi ringan
│   │   ├── RequestInterceptor.php ← Intercept & modifikasi request (aktif)
│   │   ├── InterceptedRequest.php ← Object request yang di-intercept
│   │   ├── CapturedEntry.php      ← Data satu request+response
│   │   ├── NetworkRequest.php
│   │   └── NetworkResponse.php
│   ├── Events/
│   │   ├── EventDispatcher.php
│   │   ├── EventInterface.php
│   │   ├── AbstractEvent.php
│   │   ├── PageLoadedEvent.php
│   │   ├── NavigationEvent.php
│   │   ├── ClickEvent.php
│   │   ├── DomUpdatedEvent.php
│   │   ├── JavaScriptExecutedEvent.php
│   │   └── NetworkRequestEvent.php
│   ├── Exceptions/
│   │   ├── XbrowserException.php
│   │   ├── BrowserCrashException.php
│   │   ├── BrowserDisconnectException.php
│   │   ├── TimeoutException.php
│   │   ├── SelectorNotFoundException.php
│   │   ├── JavaScriptException.php
│   │   ├── InvalidUrlException.php
│   │   └── PluginException.php
│   ├── Plugin/
│   │   ├── PluginInterface.php
│   │   └── PluginManager.php
│   └── Utils/
│       ├── ConfigManager.php      ← Konfigurasi + env var
│       ├── Logger.php
│       ├── SessionManager.php
│       └── UrlValidator.php
├── tests/
│   ├── Unit/
│   │   ├── CDP/
│   │   ├── DOM/
│   │   ├── Events/
│   │   ├── Exceptions/
│   │   ├── Networking/
│   │   ├── Plugin/
│   │   ├── Renderer/
│   │   └── Utils/
│   └── Integration/
├── plugins/
│   └── example/
│       └── plugin.php
├── examples/
│   ├── basic_automation.php
│   ├── scraping.php
│   ├── intercept_capture.php
│   ├── request_intercept.php
│   └── facebook_login.php
├── Dockerfile
├── docker-compose.yml
├── .dockerignore
├── composer.json
├── phpunit.xml
└── README.md
```

---

## Menjalankan Tests

```bash
# Jalankan semua tests
composer test

# Output verbose
./vendor/bin/phpunit --testdox

# Jalankan suite tertentu
./vendor/bin/phpunit --testsuite Unit
./vendor/bin/phpunit --testsuite Integration

# Laporan coverage HTML
composer test:coverage
# Buka: coverage/index.html
```

---

## Troubleshooting

### Chromium tidak ditemukan

```bash
# Set path via environment variable
export XBROWSER_CHROMIUM=/usr/bin/chromium-browser

# Atau via config file ~/.xbrowser/config.json
{ "chromium_path": "/usr/bin/chromium" }
```

Xbrowser secara otomatis mencari Chromium di path umum: `/usr/bin/chromium`, `/usr/bin/google-chrome`, Snap, Flatpak, NixOS, Termux, macOS, dan WSL.

---

### Error sandbox di Linux

Jika muncul `No usable sandbox!`:

```bash
# Via env var
export XBROWSER_NO_SANDBOX=true

# Atau via config file
{ "no_sandbox": true }
```

> Sandbox error umum terjadi di container Docker, CI/CD (GitHub Actions, GitLab CI), atau sistem tanpa kernel namespace support.

---

### Timeout

```bash
# Naikkan timeout global (dalam milidetik)
export XBROWSER_TIMEOUT=60000

# Atau per-operasi di kode
$page->goto('https://lambat.com', timeoutMs: 60000);
$page->waitForSelector('.konten', timeoutMs: 15000);
```

---

### Connection refused (port 9222)

Instance Chromium lain mungkin sudah memakai port 9222.

```json
{ "remote_debugging_port": 9333 }
```

Atau via env: `export XBROWSER_PORT=9333`

---

### Halaman render kosong / SPA tidak muncul

SPA (Single Page Application) membutuhkan waktu ekstra untuk JavaScript selesai. Gunakan:

```php
// Tunggu elemen spesifik yang muncul setelah JS selesai
$page->waitForSelector('#app-root');
$page->waitForSelector('.data-loaded');
```

---

### Memory tinggi / Chromium crash

Untuk halaman berat, naikkan alokasi shared memory:

```bash
# Docker
docker run --shm-size=512m ...

# docker-compose.yml
shm_size: 512m
```

---

## Arsitektur

- **Pure PHP WebSocket** — Implementasi RFC 6455 tanpa dependensi eksternal. Semua frame encoding/masking dan handshake dilakukan custom.
- **CDP via WebSocket** — Semua kontrol browser menggunakan Chrome DevTools Protocol, menjadikan Chromium sebagai mesin JS. Tidak ada JS engine custom.
- **HTML Parser** — Parser recursive descent hand-rolled untuk rendering terminal tanpa membutuhkan `ext-dom` atau `libxml`.
- **Fetch Domain untuk Interception** — Request interception menggunakan CDP Fetch domain (bukan Network domain), memungkinkan modifikasi aktif sebelum request dikirim.
- **SOLID OOP** — Setiap class punya tanggung jawab tunggal: `Browser` kelola lifecycle proses, `Page` ekspos API otomasi, `DOMManager` tangani selectors, `TerminalRenderer` tangani output.
- **Event-driven** — `EventDispatcher` dengan `on()`, `once()`, `off()` memungkinkan plugin dan kode user bereaksi terhadap event browser.
- **Zero mandatory dependencies** — Semua fitur inti berjalan dengan PHP built-in saja. Tidak ada library pihak ketiga yang diwajibkan.

---

## Lisensi

MIT — bebas digunakan untuk keperluan personal maupun komersial. Kontribusi sangat disambut.
