# Xbrowser

Browser automation library berbasis **Chrome DevTools Protocol (CDP)** dan Headless Chromium, ditulis murni PHP 8.4+ tanpa dependensi eksternal.

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

- [Cara Pakai](#cara-pakai)
- [Persyaratan](#persyaratan)
- [Instalasi](#instalasi)
- [Automation Library](#automation-library)
  - [Output Mode](#output-mode)
  - [Navigasi](#navigasi)
  - [Elemen DOM](#elemen-dom)
  - [JavaScript](#javascript)
  - [Form & Input](#form--input)
  - [Screenshot](#screenshot)
  - [Cookie & Storage](#cookie--storage)
  - [Headers, User-Agent & Viewport](#headers-user-agent--viewport)
  - [Network Capture](#network-capture)
  - [Request Interception](#request-interception)
  - [Stealth Mode & Bot Detection](#stealth-mode--bot-detection)
  - [Session Management](#session-management)
  - [Rekam & Putar Ulang](#rekam--putar-ulang)
  - [Sistem Event](#sistem-event)
  - [Plugin](#plugin)
- [CLI Terminal Browser](#cli-terminal-browser)
  - [Terminal Renderer](#terminal-renderer)
- [Konfigurasi](#konfigurasi)
- [Docker](#docker)
- [Struktur Project](#struktur-project)
- [Troubleshooting](#troubleshooting)
- [Arsitektur](#arsitektur)
- [Lisensi](#lisensi)

---

## Cara Pakai

Xbrowser bisa digunakan dengan **dua cara**:

### 1. Library PHP untuk automation bot / scraping

Dipakai seperti Playwright atau Puppeteer — **diam total by default**, tidak ada output apapun ke terminal. Kamu yang kendalikan penuh hasilnya.

```php
<?php

require 'vendor/autoload.php';

use Xbrowser\Browser\BrowserFactory;

$browser = BrowserFactory::create();          // diam total, seperti Playwright
$browser->launch(['no_sandbox' => true]);

$page = $browser->newPage();
$page->goto('https://example.com');

$judul = $page->getTitle();                   // "Example Domain"
$harga = $page->evaluate('document.querySelector(".price")?.textContent');

$page->click('#login-button');
$page->type('#email', 'user@example.com');
$page->screenshot('hasil.png');

$browser->close();
```

### 2. CLI browser berbasis terminal

Browsing langsung dari terminal — halaman HTML dirender menjadi teks ASCII/ANSI di layar.

```bash
# Buka halaman dan render ke terminal
Xbrowser open https://example.com

# Interactive shell (REPL)
Xbrowser shell

# Via Docker (tanpa install)
docker run -it --rm --shm-size=256m --cap-add=SYS_ADMIN xbrowser:latest shell
```

> **`render()` hanya relevan untuk cara pakai #2.**
> Untuk automation bot dan scraping, tidak perlu `render()` sama sekali.

---

## Persyaratan

- PHP **8.4+**
- Composer
- Chromium atau Google Chrome
- OS: Linux, macOS, atau Android (Termux)

---

## Instalasi

### Auto-install (semua environment)

Cara paling mudah — satu perintah, deteksi environment otomatis:

```bash
git clone https://github.com/your-org/xbrowser.git
cd xbrowser
bash install.sh
```

Script akan otomatis:
1. Deteksi OS (Termux, Ubuntu/Debian, Fedora, Arch, Alpine, macOS)
2. Install PHP 8.4+ jika belum ada
3. Install Composer jika belum ada
4. Install Chromium jika belum ada
5. Jalankan `composer install`
6. Daftarkan `Xbrowser` ke PATH global
7. Simpan config Chromium ke `~/.xbrowser/config.json`

Setelah selesai, `Xbrowser` bisa dipanggil dari mana saja:

```bash
Xbrowser open https://example.com
Xbrowser shell
```

### Opsi install

```bash
# Install ke PATH user saja (tanpa sudo)
bash install.sh --user

# Skip install Chromium (jika sudah ada)
bash install.sh --no-chromium

# Tentukan direktori instalasi kustom
bash install.sh --dir /home/user/tools/xbrowser
```

### Install per environment

**Termux (Android):**
```bash
pkg install git
git clone https://github.com/your-org/xbrowser.git
cd xbrowser
bash install.sh
```
> Di Termux `sudo` tidak ada — installer otomatis pakai `$PREFIX/bin` dan `$PREFIX/opt/xbrowser`.

**Ubuntu / Debian:**
```bash
git clone https://github.com/your-org/xbrowser.git && cd xbrowser && bash install.sh
```

**macOS:**
```bash
# Butuh Homebrew: https://brew.sh
git clone https://github.com/your-org/xbrowser.git && cd xbrowser && bash install.sh
```

**Alpine Linux / Docker:**
```bash
apk add bash git
git clone https://github.com/your-org/xbrowser.git && cd xbrowser && bash install.sh
```

**Satu baris (curl, jika repo sudah public):**
```bash
curl -fsSL https://raw.githubusercontent.com/your-org/xbrowser/main/install.sh | bash
```

### Uninstall

```bash
bash uninstall.sh
```

Menghapus symlink, direktori instalasi (konfirmasi dulu), dan entri PATH dari shell config.

### Manual (tanpa installer)

```bash
git clone https://github.com/your-org/xbrowser.git
cd xbrowser
composer install
chmod +x bin/Xbrowser
sudo ln -s "$(pwd)/bin/Xbrowser" /usr/local/bin/Xbrowser
```

---

## Automation Library

### Output Mode

Xbrowser **diam total by default** — tidak ada output ke terminal selama operasi berjalan. Cocok untuk automation bot, scraping, dan CI/CD.

| Mode | Perilaku | Cara aktifkan |
|------|----------|---------------|
| **Default** | Nol output, semua berjalan di background | `BrowserFactory::create([])` |
| **Verbose** | Tampilkan semua log: INFO, DEBUG, ✓ sukses | `BrowserFactory::create(['verbose' => true])` |
| **Silent** | Benar-benar kosong, bahkan WARN pun tidak tampil | `BrowserFactory::create(['silent' => true])` |
| **Log ke file** | Tulis ke file, terminal tetap bersih | `BrowserFactory::create(['logFile' => '/var/log/bot.log'])` |

```php
// ── Automation bot — tidak ada output apapun ──────────────────────────────
$browser = BrowserFactory::create();

// ── Debug sementara — aktifkan verbose ───────────────────────────────────
$browser = BrowserFactory::create(['verbose' => true]);

// ── Simpan log ke file tanpa ganggu output terminal ───────────────────────
$browser = BrowserFactory::create(['logFile' => 'logs/bot.log']);

// ── Kombinasi ─────────────────────────────────────────────────────────────
$browser = BrowserFactory::create([
    'verbose' => true,
    'logFile' => 'logs/debug.log',
]);
```

> **Catatan:** `error()` selalu ditulis ke `STDERR`, dan bisa di-suppress sepenuhnya dengan `silent => true`.
> Semua log tetap tersimpan di history internal — akses via `$browser->getLogger()->getHistory()`.

---

### Navigasi

```php
// Buka URL — tunggu hingga document.readyState === 'complete'
$page->goto('https://example.com');
$page->goto('https://example.com', 60000); // timeout custom (ms)

// Navigasi browser
$page->goBack();
$page->goForward();
$page->reload();
$page->reload(ignoreCache: true);

// Tunggu navigasi selesai (berguna setelah klik link yang ganti halaman)
$page->waitForNavigation();

// Tunggu halaman selesai load
$page->waitForLoad(30000);

// Info halaman saat ini
$url   = $page->getUrl();    // string URL
$judul = $page->getTitle();  // string judul
```

---

### Elemen DOM

```php
use Xbrowser\DOM\Element;

// Query satu elemen — melempar SelectorNotFoundException jika tidak ada
$el = $page->query('#login-button');
$el = $page->query('form input[type="email"]');

// Query semua elemen yang cocok — array Element
$links = $page->queryAll('a[href]');
foreach ($links as $link) {
    echo $link->getText() . "\n";
}

// Tunggu elemen muncul — polling hingga timeout
$el = $page->waitForSelector('.dashboard', 10000);

// Ambil HTML mentah halaman
$html = $page->getContent();
```

---

### JavaScript

```php
// Evaluasi ekspresi — nilai PHP dikembalikan
$judul  = $page->evaluate('document.title');                         // string
$jumlah = $page->evaluate('document.querySelectorAll("a").length'); // int
$ada    = $page->evaluate('!!document.querySelector(".modal")');    // bool

// Evaluasi multi-baris — array PHP dikembalikan
$produk = $page->evaluate(<<<JS
    Array.from(document.querySelectorAll('.product')).map(el => ({
        nama  : el.querySelector('.name')?.textContent?.trim() ?? '',
        harga : el.querySelector('.price')?.textContent?.trim() ?? '',
        href  : el.querySelector('a')?.href ?? ''
    }))
JS);

foreach ($produk as $p) {
    echo "{$p['nama']} — {$p['harga']}\n";
}
```

> JavaScript dieksekusi di konteks halaman. Promise otomatis di-await.
> Jika ekspresi throw error, `JavaScriptException` akan dilempar.

---

### Form & Input

```php
// Klik elemen — simulasi mouse click + trigger JS event handler
$page->click('#login-button');
$page->click('button[type="submit"]');

// Ketik ke input — kompatibel dengan React, Vue, Angular
// (menggunakan native prototype setter agar onChange terpicu)
$page->type('#email', 'user@example.com');
$page->type('#password', 'rahasia123');

// Submit form
$page->submit('#login-form'); // selector form spesifik
$page->submit();              // form pertama yang ditemukan
```

---

### Screenshot

```php
// Screenshot ke file — format auto-detect dari ekstensi
$page->screenshot('output/halaman.png');
$page->screenshot('output/halaman.jpg', format: 'jpeg');

// Atur viewport sebelum screenshot
$page->setViewport(1920, 1080);
$page->screenshot('output/fullhd.png');

$page->setViewport(375, 812); // simulasi layar mobile
$page->screenshot('output/mobile.png');
```

---

### Cookie & Storage

```php
// Baca cookies
$cookies = $page->getCookies();
foreach ($cookies as $c) {
    echo $c['name'] . '=' . $c['value'] . "\n";
}

// Set cookies (berguna untuk inject sesi yang sudah ada)
$page->setCookies([
    ['name' => 'session_token', 'value' => 'abc123', 'domain' => 'example.com'],
    ['name' => 'pref',          'value' => 'dark',   'domain' => 'example.com'],
]);

// Hapus semua cookies
$page->clearCookies();

// Baca localStorage
$storage = $page->getLocalStorage();
echo $storage['user_id'] ?? '(kosong)';

// Baca sessionStorage
$session = $page->getSessionStorage();
```

---

### Headers, User-Agent & Viewport

```php
// Tambahkan header ke semua request berikutnya
$page->setExtraHeaders([
    'Authorization'   => 'Bearer token-saya',
    'X-Custom-Header' => 'nilai',
]);

// Ganti User-Agent
$page->setUserAgent(
    'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 ' .
    '(KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36'
);

// Atur ukuran viewport (default 1280×800)
$page->setViewport(1366, 768);
$page->setViewport(375, 812); // simulasi mobile
```

---

### Network Capture

`startCapture()` merekam semua network traffic secara **pasif** — tidak menghentikan atau mengubah request, hanya mengamati.

```php
$capture = $page->startCapture();

// Filter domain tertentu saja
$capture->filterDomain('api.example.com');

// Deteksi otomatis kredensial di POST body
$capture->scanCredentials(['password', 'pass=', 'token']);

// Nonaktifkan fetch body otomatis (hemat memori untuk halaman berat)
$capture->disableLiveBodyFetch();

$page->goto('https://example.com');
$page->click('#login');
$page->type('#email', 'user@example.com');
$page->type('#password', 'rahasia');
$page->submit();

// Ambil body yang tertunda setelah aksi selesai
$capture->fetchPendingBodies();

// ── Akses data yang direkam ────────────────────────────────────────────────
$semua = $capture->getAll();            // semua entry
$posts = $capture->getPosts();          // hanya POST request
$creds = $capture->getWithCredentials(); // POST yang mengandung kredensial
$api   = $capture->findByUrl('/api/');  // filter berdasarkan pola URL

// Statistik
print_r($capture->summary());
// ['total' => 42, 'posts' => 5, 'withCredentials' => 1, 'succeeded' => 40, 'failed' => 2]

// Simpan ke file JSON
$capture->saveJson('output/capture.json');

// Reset data capture
$capture->clear();
```

**Struktur tiap entry yang direkam:**

| Properti | Tipe | Keterangan |
|----------|------|-----------|
| `requestId` | string | ID unik request CDP |
| `method` | string | HTTP method (GET, POST, dll.) |
| `url` | string | URL request |
| `requestHeaders` | array | Request headers |
| `postData` | string | Body POST (jika ada) |
| `resourceType` | string | Tipe resource (XHR, Document, Image, dll.) |
| `hasCredentials` | bool | Apakah POST body mengandung kredensial |
| `responseStatus` | int | HTTP status code |
| `responseHeaders` | array | Response headers |
| `responseBody` | string | Response body (max 5000 karakter) |
| `bodyFetched` | bool | Apakah body sudah diambil |
| `capturedAt` | float | Timestamp microtime |

---

### Request Interception

`intercept()` menghentikan request **sebelum dikirim** dan menunggu keputusan handler.

> Berbeda dari `startCapture()` (pasif/hanya mengamati), `intercept()` bisa **memblokir**, **memodifikasi**, atau **membalas** request dengan response buatan.

```php
use Xbrowser\Networking\InterceptedRequest;

$interceptor = $page->intercept();
```

**Contoh 1 — Blokir gambar & iklan (halaman lebih cepat):**

```php
$interceptor
    ->blockResourceTypes(['Image', 'Media', 'Font'])
    ->blockAds();

$page->goto('https://example.com');

print_r($interceptor->getStats());
// ['total' => 35, 'blocked' => 18, 'allowed' => 17, ...]

$interceptor->disable();
```

**Contoh 2 — Inject header ke setiap request:**

```php
$interceptor->onRequest(function (InterceptedRequest $req): void {
    $req->continue([
        'headers' => array_merge($req->headers, [
            'Authorization' => 'Bearer token-saya',
        ]),
    ]);
});

$page->goto('https://httpbin.org/headers');
$interceptor->disable();
```

**Contoh 3 — Mock respons API (tanpa server nyata):**

```php
$interceptor->onRequest(function (InterceptedRequest $req): void {
    if (str_contains($req->url, '/api/user')) {
        $req->respond([
            'status'   => 200,
            'mimeType' => 'application/json',
            'body'     => json_encode(['id' => 1, 'name' => 'Mock User']),
        ]);
    } else {
        $req->continue(); // teruskan request lain
    }
});

$page->goto('https://example.com');
$interceptor->disable();
```

**Contoh 4 — Redirect URL:**

```php
$interceptor->onRequest(function (InterceptedRequest $req): void {
    $url = str_replace('cdn.lama.com', 'cdn.baru.com', $req->url);
    $req->continue(['url' => $url]);
});
```

**Contoh 5 — Log semua XHR/Fetch request:**

```php
$interceptor
    ->interceptXhr()  // hanya XHR + Fetch API
    ->onRequest(function (InterceptedRequest $req): void {
        echo "[{$req->method}] {$req->url}\n";
        $req->continue();
    });
```

**API RequestInterceptor:**

| Method | Keterangan |
|--------|-----------|
| `onRequest(callable)` | Daftarkan handler — wajib panggil `continue()`, `abort()`, atau `respond()` di dalamnya |
| `blockResourceTypes(string[])` | Blokir berdasarkan tipe resource |
| `blockUrls(string[])` | Blokir URL yang mengandung substring |
| `blockAds()` | Blokir tracker & iklan (domain list bawaan) |
| `addHeaders(array)` | Inject header ke semua request |
| `interceptAll()` | Intercept semua request (default) |
| `interceptXhr()` | Intercept hanya XHR dan Fetch |
| `interceptStatic()` | Intercept resource statis (Image, CSS, Font) |
| `addPattern(url, ?type)` | Tambah pola URL/tipe kustom |
| `getStats()` | Statistik: total, blocked, modified, mocked, allowed |
| `disable()` | Matikan interceptor |

**Di dalam handler, wajib panggil tepat satu aksi:**

| Method | Keterangan |
|--------|-----------|
| `$req->continue(array $overrides = [])` | Teruskan request. Override opsional: `url`, `method`, `headers`, `postData` |
| `$req->abort(string $reason = 'BlockedByClient')` | Batalkan request |
| `$req->respond(array $response)` | Balas dengan response buatan: `status`, `headers`, `body`, `mimeType` |

**Properti `InterceptedRequest` (readonly):**

| Properti | Tipe | Keterangan |
|----------|------|-----------|
| `url` | string | URL request |
| `method` | string | HTTP method |
| `headers` | array | Request headers |
| `postData` | string | Request body |
| `resourceType` | string | Document, XHR, Fetch, Image, Script, Stylesheet, Font, Media, dll. |
| `requestId` | string | ID unik CDP |
| `frameId` | string | ID frame asal |

---

### Stealth Mode & Bot Detection

Xbrowser mengaktifkan Stealth Mode secara default untuk menghindari deteksi bot.

```php
$browser->launch(['stealth' => true]); // default: true
```

Patch yang diterapkan otomatis:
- `navigator.webdriver` → disembunyikan (`undefined`)
- `navigator.plugins` → disimulasikan (3 plugin palsu)
- `navigator.languages` → `['id-ID', 'id', 'en-US', 'en']`
- `window.chrome` → object palsu agar terlihat seperti Chrome biasa
- User-Agent → menghapus string `HeadlessChrome`

```php
// Cek hasil stealth
$result = $page->checkBotDetection();

echo $result->describe();
// ✓ navigator.webdriver hidden
// ✓ navigator.plugins present (3 plugins)
// ✓ window.chrome object present
// ✓ navigator.languages present
// ✓ User-Agent clean (no "HeadlessChrome")

echo "Skor: " . $result->score() . "/5\n";
echo "Lulus: " . ($result->isPassed() ? 'Ya' : 'Tidak') . "\n";

// Detail tiap cek
var_dump($result->webdriverHidden);
var_dump($result->pluginsPresent);
var_dump($result->chromeObjectPresent);
var_dump($result->languagesPresent);
var_dump($result->userAgentClean);
```

---

### Session Management

Simpan dan pulihkan state browser (cookies, localStorage, sessionStorage, URL).

```php
use Xbrowser\Utils\SessionManager;

$sessions = new SessionManager(); // default: ~/.xbrowser/sessions/

// ── Simpan sesi setelah login ─────────────────────────────────────────────
$page->goto('https://example.com/login');
$page->type('#email', 'user@example.com');
$page->type('#password', 'rahasia');
$page->submit();
$page->waitForSelector('.dashboard');

$page->saveSession('sesi-login', $sessions);

// ── Sesi berikutnya — muat ulang tanpa login lagi ─────────────────────────
$page->loadSession('sesi-login', $sessions);
// Otomatis restore cookies + navigasi ke URL terakhir
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

// Hentikan dan dapatkan PHP script siap pakai
$script = $page->stopRecording();
file_put_contents('script_bot.php', $script);

// Atau akses array aksi mentah
$actions = $page->getRecordedActions();
```

---

### Sistem Event

```php
use Xbrowser\Events\PageLoadedEvent;
use Xbrowser\Events\NavigationEvent;
use Xbrowser\Events\ClickEvent;

$dispatcher = $browser->getDispatcher();

// Langganan event
$dispatcher->on('page.loaded', function (PageLoadedEvent $event): void {
    echo "Dimuat: " . $event->getPayload()['url'] . "\n";
});

$dispatcher->on('page.navigation', function (NavigationEvent $event): void {
    $p = $event->getPayload();
    echo "Navigasi: {$p['from']} → {$p['to']}\n";
});

$dispatcher->on('element.clicked', function (ClickEvent $event): void {
    $p = $event->getPayload();
    echo "Klik: {$p['selector']} di ({$p['x']}, {$p['y']})\n";
});

// Hanya sekali
$dispatcher->once('page.loaded', function ($e): void { /* ... */ });

// Wildcard — semua event
$dispatcher->on('*', function ($e): void {
    echo "[event] " . $e->getName() . "\n";
});

// Hapus listener
$dispatcher->off('page.loaded', $handler);
$dispatcher->removeAll('page.loaded');
```

**Daftar event:**

| Nama Event | Kapan Dipicu |
|------------|-------------|
| `page.loaded` | Halaman selesai dimuat |
| `page.navigation` | URL berubah |
| `element.clicked` | `$page->click()` dipanggil |
| `dom.updated` | Mutasi DOM terdeteksi |
| `javascript.executed` | `$page->evaluate()` dipanggil |
| `network.request` | Request network dikirim |
| `*` | Semua event (wildcard) |

---

### Plugin

Buat file `plugins/<nama>/plugin.php` yang mengembalikan instance `PluginInterface`:

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
            echo "[plugin-saya] Dimuat: {$url}\n";
        });
    }

    public function getCommands(): array
    {
        return [
            'salam' => fn(array $args) => print("Halo dari plugin!\n"),
        ];
    }
};
```

Plugin di-discover otomatis saat startup. Direktori kustom:

```php
$browser = BrowserFactory::create(['pluginDir' => '/path/ke/plugins-saya']);
```

---

## CLI Terminal Browser

Untuk yang ingin browsing langsung dari terminal tanpa menulis PHP.

```bash
# Buka URL — render halaman ke terminal
Xbrowser open https://example.com

# Klik elemen
Xbrowser click https://example.com "#login-button"

# Ketik ke input
Xbrowser type https://example.com "#email" "user@example.com"

# Evaluasi JavaScript
Xbrowser eval https://example.com "document.title"
Xbrowser eval https://example.com "document.querySelectorAll('a').length"

# Screenshot
Xbrowser screenshot https://example.com halaman.png

# Dump HTML mentah
Xbrowser html https://example.com
Xbrowser html https://example.com > halaman.html

# Inspeksi network traffic
Xbrowser network https://example.com

# Simpan / muat sesi
Xbrowser save-session https://github.com sesi-saya
Xbrowser load-session sesi-saya

# Rekam aksi → generate PHP script
Xbrowser record
```

### Interactive Shell

```bash
Xbrowser shell
```

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

**Daftar perintah shell:**

| Perintah | Keterangan |
|----------|-----------|
| `open <url>` | Navigasi ke URL |
| `render` | Render halaman ke terminal |
| `html` | Dump HTML mentah |
| `click <selector>` | Klik elemen CSS selector |
| `type <selector> <teks>` | Ketik ke elemen |
| `eval <ekspresi>` | Evaluasi JavaScript |
| `screenshot <file>` | Ambil screenshot |
| `submit [selector]` | Submit form |
| `back` / `forward` | Navigasi browser |
| `reload` | Reload halaman |
| `wait <selector>` | Tunggu elemen muncul |
| `cookies` | Tampilkan cookies |
| `title` / `url` | Info halaman |
| `save-session <nama>` | Simpan sesi |
| `load-session <nama>` | Muat sesi |
| `network` | Ringkasan network |
| `clear` | Bersihkan terminal |
| `help` / `quit` | Bantuan / keluar |

---

### Terminal Renderer

`render()` hanya digunakan ketika kamu ingin **menampilkan isi halaman ke terminal** — seperti browser teks (Lynx, w3m). Untuk automation bot, **tidak perlu dipanggil sama sekali**.

```php
// Kapan dipakai:
echo $page->render(); // preview isi halaman di terminal

// Kapan TIDAK perlu:
$data = $page->evaluate('...');   // ← ambil data langsung via JS
$html = $page->getContent();      // ← HTML mentah
$page->screenshot('out.png');     // ← tangkap screenshot
```

Konversi HTML → terminal:

| Elemen HTML | Output Terminal |
|-------------|----------------|
| `<h1>` | `# JUDUL` + garis bawah |
| `<h2>` | `## JUDUL` |
| `<a>` | `[Teks Link](url)` |
| `<button>` | `[ Tombol ]` |
| `<input>` | `[__placeholder______]` |
| `<textarea>` | Kotak multi-baris |
| `<ul>/<li>` | `• Butir` |
| `<ol>/<li>` | `1. Butir bernomor` |
| `<table>` | Tabel ASCII (lebar auto-cap) |
| `<img>` | `[IMAGE: alt text]` |
| `<hr>` | `────────────────────` |
| `<pre>` | Blok kode bertepi |
| `<blockquote>` | `▌ Teks kutipan` |
| `<strong>` | Teks tebal (ANSI bold) |
| `<code>` | `` `kode inline` `` |

---

## Konfigurasi

### File konfigurasi

Xbrowser membaca dari `~/.xbrowser/config.json`:

```json
{
    "chromium_path":         "/usr/bin/chromium",
    "remote_debugging_port": 9222,
    "timeout":               30000,
    "startup_timeout":       60000,
    "headless":              true,
    "stealth":               true,
    "disable_gpu":           true,
    "no_sandbox":            false,
    "window_width":          1280,
    "window_height":         800,
    "verbose":               false,
    "silent":                false,
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
| `XBROWSER_TIMEOUT` | `30000` | Timeout navigasi/operasi default (ms) |
| `XBROWSER_STARTUP_TIMEOUT` | `60000` | Timeout tunggu Chrome siap (ms) — naikkan di perangkat lambat |
| `XBROWSER_HEADLESS` | `true` | Mode headless |
| `XBROWSER_STEALTH` | `true` | Aktifkan stealth mode |
| `XBROWSER_NO_SANDBOX` | `false` | Nonaktifkan sandbox (wajib `true` di Docker/CI) |
| `XBROWSER_VERBOSE` | `false` | Tampilkan log detail |
| `XBROWSER_LOG_FILE` | `""` | Path file log |
| `XBROWSER_PLUGIN_DIR` | `""` | Direktori plugin kustom |

### Opsi `BrowserFactory::create()`

| Opsi | Tipe | Default | Keterangan |
|------|------|---------|-----------|
| `verbose` | bool | `false` | Tampilkan log INFO, DEBUG, ✓ |
| `silent` | bool | `false` | Suppress semua output termasuk WARN |
| `logFile` | string | `""` | Tulis log ke file |
| `configFile` | string | `""` | Path file config JSON kustom |
| `pluginDir` | string | `""` | Direktori plugin kustom |
| `launch` | bool | `false` | Langsung launch browser setelah create |

### Opsi `$browser->launch()`

| Opsi | Tipe | Default | Keterangan |
|------|------|---------|-----------|
| `headless` | bool | `true` | Mode headless (tanpa GUI) |
| `stealth` | bool | `true` | Aktifkan patch anti-deteksi bot |
| `no_sandbox` | bool | `false` | Wajib `true` di Docker/CI/Replit |
| `disable_gpu` | bool | `true` | Nonaktifkan GPU (stabil di server) |
| `startup_timeout` | int | `60000` | Timeout tunggu Chrome siap (ms). Naikkan di perangkat lambat |

---

## Docker

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
  -v "$(pwd)/scripts:/app/scripts" \
  -v "$(pwd)/output:/app/output" \
  xbrowser:latest php scripts/bot-saya.php
```

### Docker Compose

```bash
# Interactive shell
docker compose up xbrowser

# Jalankan script custom
docker compose run xbrowser-run php examples/scraping.php

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
| `XBROWSER_VERBOSE` | `false` | Tampilkan log detail |

> Chromium butuh shared memory yang cukup. Selalu gunakan `--shm-size=256m` atau lebih, atau Chromium bisa crash saat render halaman berat.

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
│       ├── Logger.php             ← Logger dengan mode default/verbose/silent
│       ├── SessionManager.php
│       └── UrlValidator.php
├── tests/
│   ├── Unit/
│   └── Integration/
├── plugins/
│   └── example/
│       └── plugin.php
├── examples/
│   ├── basic_automation.php       ← Contoh dasar automation
│   ├── scraping.php               ← Web scraping + extract data
│   ├── intercept_capture.php      ← Network capture pasif
│   ├── request_intercept.php      ← Request interception aktif
│   └── facebook_login.php         ← Contoh login
├── Dockerfile
├── docker-compose.yml
├── .dockerignore
├── composer.json
├── phpunit.xml
└── README.md
```

---

## Troubleshooting

### Chromium tidak ditemukan

```bash
# Set path via environment variable
export XBROWSER_CHROMIUM=/usr/bin/chromium-browser

# Atau via config file
echo '{"chromium_path": "/usr/bin/chromium"}' > ~/.xbrowser/config.json
```

Xbrowser secara otomatis mencari Chromium di path umum: `/usr/bin/chromium`, `/usr/bin/google-chrome`, Snap, Flatpak, NixOS, Termux, macOS, dan WSL.

---

### Error sandbox di Linux / Docker / CI

Jika muncul `No usable sandbox!`:

```bash
# Via environment variable
export XBROWSER_NO_SANDBOX=true

# Atau via PHP
$browser->launch(['no_sandbox' => true]);
```

> Sandbox error umum di: Docker container, GitHub Actions, GitLab CI, Replit, atau sistem tanpa kernel namespace support.

---

### Timeout

```bash
# Naikkan timeout global (milidetik)
export XBROWSER_TIMEOUT=60000
```

```php
// Atau per-operasi di kode
$page->goto('https://lambat.com', 60000);
$page->waitForSelector('.konten', 15000);
```

---

### Connection refused (port 9222)

Instance Chromium lain mungkin sudah memakai port 9222.

```bash
export XBROWSER_PORT=9333
```

---

### SPA / React / Vue — konten tidak muncul

JavaScript butuh waktu untuk selesai merender. Tunggu elemen spesifik:

```php
// Tunggu elemen yang baru muncul setelah JS selesai
$page->waitForSelector('#app-root');
$page->waitForSelector('.data-loaded');

// Atau tunggu navigasi selesai setelah klik
$page->click('.load-more');
$page->waitForSelector('.new-items');
```

---

### Memory tinggi / Chromium crash

Untuk halaman berat, naikkan alokasi shared memory:

```bash
# Docker CLI
docker run --shm-size=512m ...

# docker-compose.yml
shm_size: 512m
```

---

## Arsitektur

- **Pure PHP WebSocket** — Implementasi RFC 6455 tanpa dependensi eksternal. Frame encoding/masking dan handshake dilakukan sendiri.
- **CDP via WebSocket** — Semua kontrol browser menggunakan Chrome DevTools Protocol. Chromium adalah mesin JS-nya — tidak ada JS engine custom.
- **HTML Parser** — Recursive descent parser hand-rolled untuk rendering terminal, tanpa membutuhkan `ext-dom` atau `libxml`.
- **Fetch Domain untuk Interception** — Request interception menggunakan CDP Fetch domain (bukan Network domain), sehingga bisa modifikasi aktif sebelum request dikirim ke server.
- **Silent by default** — Seperti Playwright/Puppeteer: nol output saat dipakai sebagai library. Verbose mode hanya aktif bila diminta eksplisit.
- **SOLID OOP** — `Browser` kelola lifecycle proses, `Page` ekspos API otomasi, `DOMManager` tangani selector, `TerminalRenderer` tangani output terminal.
- **Event-driven** — `EventDispatcher` dengan `on()`, `once()`, `off()` memungkinkan plugin dan kode user bereaksi terhadap event browser.
- **Zero mandatory dependencies** — Semua fitur inti berjalan dengan PHP built-in saja.

---

## Lisensi

MIT — bebas digunakan untuk keperluan personal maupun komersial.
