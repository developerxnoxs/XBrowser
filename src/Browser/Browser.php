<?php

declare(strict_types=1);

namespace Xbrowser\Browser;

use Xbrowser\CDP\Client;
use Xbrowser\Events\EventDispatcher;
use Xbrowser\Exceptions\BrowserCrashException;
use Xbrowser\Exceptions\TimeoutException;
use Xbrowser\Plugin\PluginManager;
use Xbrowser\Utils\ConfigManager;
use Xbrowser\Utils\Logger;

class Browser
{
    private mixed $chromiumProcess = null;
    private array $pipes   = [];
    private array $pages   = [];
    private array $clients = [];
    private ?Page $currentPage    = null;
    private bool  $launched       = false;
    private int   $port           = 9222;
    private bool  $stealth        = true;
    private int   $startupTimeout = 60000;
    private string $debugHost     = '127.0.0.1'; // resolved saat waitForBrowserReady

    public function __construct(
        private readonly ConfigManager   $config,
        private readonly Logger          $logger,
        private readonly EventDispatcher $dispatcher,
        private readonly ?PluginManager  $plugins = null
    ) {}

    // ─── Launch ───────────────────────────────────────────────────────────────

    public function launch(array $options = []): void
    {
        if ($this->launched) {
            return;
        }

        $this->port    = (int)  ($options['port']            ?? $this->config->get('remote_debugging_port', 9222));
        $headless      = (bool) ($options['headless']         ?? $this->config->get('headless', true));
        $chromium      = $options['chromium']                 ?? $this->config->getChromiumPath();
        $userDataDir   = $options['userDataDir']              ?? $this->config->get('user_data_dir', '');
        $this->stealth         = (bool) ($options['stealth']         ?? $this->config->get('stealth', true));
        $this->startupTimeout  = (int)  ($options['startup_timeout'] ?? $this->config->get('startup_timeout', 60000));

        $this->logger->info("Launching Chromium: {$chromium}");

        $args = $this->buildArgs($this->port, $headless, $userDataDir, $options);
        $cmd  = $chromium . ' ' . implode(' ', $args);

        $this->logger->debug("Command: {$cmd}");

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $this->chromiumProcess = proc_open($cmd, $descriptors, $this->pipes);

        if (!is_resource($this->chromiumProcess)) {
            throw new BrowserCrashException("Failed to start Chromium");
        }

        stream_set_blocking($this->pipes[1], false);
        stream_set_blocking($this->pipes[2], false);

        // Wait for Chromium HTTP endpoint to be ready (don't connect WS yet)
        $this->waitForBrowserReady($this->port, $this->startupTimeout);

        $this->launched = true;
        $this->logger->success("Browser launched");

        if ($this->plugins) {
            $this->plugins->activate($this);
        }
    }

    // ─── Page creation ────────────────────────────────────────────────────────

    /**
     * Open a new page (tab) and return a Page connected to its own CDP session.
     *
     * Each page gets a dedicated WebSocket connection to its CDP target, which
     * is the only way Runtime.evaluate / DOM.* / Page.* etc. actually work.
     */
    public function newPage(): Page
    {
        $this->ensureLaunched();

        // Ask Chromium to create a new blank tab and return its target info
        $targetInfo = $this->createNewTarget($this->startupTimeout);
        $wsUrl      = $targetInfo['webSocketDebuggerUrl']
            ?? throw new BrowserCrashException("No WebSocket URL for new page target");

        $this->logger->debug("New page WS: {$wsUrl}");

        // Each page gets its OWN CDP client connected to the page-level WS
        $cdp = new Client($wsUrl, $this->dispatcher, $this->logger);
        $cdp->connect();

        // Apply stealth patches on this page's connection
        if ($this->stealth) {
            (new StealthMode($cdp))->apply();
            $this->logger->debug("Stealth applied to new page");
        }

        $page = new Page(
            $cdp,
            $this->dispatcher,
            $this->logger,
            (int) $this->config->get('timeout', 30000)
        );

        $this->clients[]   = $cdp;
        $this->pages[]     = $page;
        $this->currentPage = $page;

        return $page;
    }

    // ─── Accessors ────────────────────────────────────────────────────────────

    public function getPage(): ?Page
    {
        return $this->currentPage;
    }

    public function getPages(): array
    {
        return $this->pages;
    }

    public function isLaunched(): bool
    {
        return $this->launched;
    }

    public function getConfig(): ConfigManager
    {
        return $this->config;
    }

    public function getLogger(): Logger
    {
        return $this->logger;
    }

    public function getDispatcher(): EventDispatcher
    {
        return $this->dispatcher;
    }

    // ─── Close ────────────────────────────────────────────────────────────────

    public function close(): void
    {
        foreach ($this->clients as $client) {
            try { $client->close(); } catch (\Throwable) {}
        }
        $this->clients = [];

        if (is_resource($this->chromiumProcess)) {
            foreach ($this->pipes as $pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }
            proc_terminate($this->chromiumProcess);
            proc_close($this->chromiumProcess);
        }

        $this->launched = false;
        $this->logger->info("Browser closed");
    }

    // ─── Internal ─────────────────────────────────────────────────────────────

    /**
     * Create a new page target via PUT /json/new.
     * Falls back to re-using the first existing "page" target if /json/new fails.
     *
     * Uses a raw TCP socket instead of file_get_contents because Chrome 138+
     * does not close the HTTP keep-alive connection after the response, which
     * causes file_get_contents to block until the timeout expires.
     * The raw socket reads until the JSON payload is complete, then closes.
     */
    private function createNewTarget(int $timeoutMs = 5000): array
    {
        $data = $this->putJsonNew($this->debugHost, $this->port, min($timeoutMs, 8000));

        if (is_array($data) && !empty($data['webSocketDebuggerUrl'])) {
            return $data;
        }

        // Fallback: find an existing "page" type target from /json/list
        return $this->findOrWaitForPageTarget($timeoutMs);
    }

    /**
     * Issue PUT /json/new via a raw TCP socket.
     *
     * Chrome 138+ keeps the HTTP connection alive after sending the JSON
     * response, so file_get_contents() blocks until its timeout.  Reading
     * the raw socket and stopping as soon as we see "webSocketDebuggerUrl"
     * avoids that indefinite wait.
     */
    private function putJsonNew(string $host, int $port, int $timeoutMs = 8000): ?array
    {
        $deadline = microtime(true) + $timeoutMs / 1000;

        // fsockopen tidak menerima bracket IPv6 — strip jika ada
        $connectHost = ($host[0] === '[') ? substr($host, 1, -1) : $host;
        $sock = @fsockopen($connectHost, $port, $errno, $errstr, min(3, $timeoutMs / 1000));
        if ($sock === false) {
            return null;
        }

        stream_set_timeout($sock, 0, 300_000);

        // HTTP Host header untuk IPv6 harus menyertakan bracket: [::1]:port
        $request = "PUT /json/new HTTP/1.1\r\nHost: {$host}:{$port}\r\nContent-Length: 0\r\n\r\n";
        fwrite($sock, $request);

        $response = '';
        while (!feof($sock) && microtime(true) < $deadline) {
            $chunk = @fread($sock, 4096);
            if ($chunk === false || $chunk === '') {
                usleep(5_000);
                continue;
            }
            $response .= $chunk;
            if (str_contains($response, 'webSocketDebuggerUrl')) {
                break;
            }
        }

        @fclose($sock);

        $pos  = strpos($response, "\r\n\r\n");
        $body = $pos !== false ? substr($response, $pos + 4) : $response;

        return json_decode($body, true) ?: null;
    }

    /**
     * Poll /json until a target of type "page" appears.
     */
    private function findOrWaitForPageTarget(int $timeoutMs = 5000): array
    {
        $deadline = microtime(true) + $timeoutMs / 1000;
        $ctx      = stream_context_create(['http' => ['timeout' => 2]]);

        while (microtime(true) < $deadline) {
            $url  = "http://{$this->debugHost}:{$this->port}/json";
            $body = @file_get_contents($url, false, $ctx);

            if ($body !== false) {
                $targets = json_decode($body, true) ?? [];
                foreach ($targets as $target) {
                    if (($target['type'] ?? '') === 'page' && !empty($target['webSocketDebuggerUrl'])) {
                        return $target;
                    }
                }
            }

            usleep(200_000);
        }

        throw new BrowserCrashException("No page target available in Chromium");
    }

    /**
     * Poll /json/version until Chromium's HTTP debug server is ready.
     * Default timeout 60 detik agar perangkat lambat tetap bisa berjalan.
     *
     * Tiga perbaikan untuk Termux / perangkat lambat:
     *
     * 1. DRAIN PIPES — Chromium menulis banyak output ke stderr saat startup.
     *    Jika pipe buffer penuh (~64 KB), Chromium akan terhenti menunggu pipe
     *    dikuras, sementara PHP menunggu debug port tersedia → deadlock klasik.
     *    Solusi: baca pipe di setiap iterasi polling.
     *
     * 2. DETEKSI CRASH — Jika Chromium keluar lebih awal (library hilang, OOM,
     *    dll.), langsung lempar BrowserCrashException alih-alih menunggu timeout
     *    penuh lalu melempar TimeoutException yang menyesatkan.
     *
     * 3. STDERR LOGGING — Kumpulkan output stderr dan sertakan di pesan error
     *    agar pengguna tahu kenapa Chromium gagal.
     */
    private function waitForBrowserReady(int $port, int $timeoutMs = 60000): void
    {
        $deadline  = microtime(true) + $timeoutMs / 1000;
        $stderrBuf = '';

        $this->logger->debug(
            "Waiting for Chromium to be ready (timeout: {$timeoutMs}ms) ..."
        );

        while (microtime(true) < $deadline) {
            // ── 1. Drain stdout/stderr pipes ─────────────────────────────────
            // Mencegah pipe-buffer deadlock: Chromium bisa macet menulis stderr
            // jika buffer penuh dan PHP tidak membacanya.
            if (isset($this->pipes[1]) && is_resource($this->pipes[1])) {
                $chunk = @fread($this->pipes[1], 65536);
                if ($chunk !== false && $chunk !== '') {
                    $this->logger->debug("[chromium stdout] " . rtrim($chunk));
                }
            }
            if (isset($this->pipes[2]) && is_resource($this->pipes[2])) {
                $chunk = @fread($this->pipes[2], 65536);
                if ($chunk !== false && $chunk !== '') {
                    $stderrBuf .= $chunk;
                    $this->logger->debug("[chromium stderr] " . rtrim($chunk));
                }
            }

            // ── 2. Deteksi crash — gagal cepat tanpa tunggu timeout penuh ───
            if (is_resource($this->chromiumProcess)) {
                $status = proc_get_status($this->chromiumProcess);
                if (isset($status['running']) && $status['running'] === false) {
                    $exitCode = $status['exitcode'] ?? -1;
                    $hint     = $stderrBuf !== ''
                        ? "\nOutput Chromium:\n" . substr($stderrBuf, -2000)
                        : "\nCek apakah Chromium terinstall dengan benar dan semua dependensi tersedia.";
                    throw new BrowserCrashException(
                        "Chromium process exited unexpectedly (exit code: {$exitCode}).{$hint}"
                    );
                }
            }

            // ── 3. Poll debug HTTP endpoint ───────────────────────────────────
            // Coba kedua alamat: IPv4 (127.0.0.1) dan IPv6 ([::1]).
            // Di Termux/Android, Chromium sering bind ke [::1] sementara
            // resolusi "localhost" mengarah ke 127.0.0.1 → polling gagal
            // meski Chromium sudah berjalan.
            $ctx   = stream_context_create(['http' => ['timeout' => 1]]);
            $hosts = ["127.0.0.1", "[::1]"];
            foreach ($hosts as $host) {
                $url  = "http://{$host}:{$port}/json/version";
                $body = @file_get_contents($url, false, $ctx);
                if ($body !== false) {
                    $data = json_decode($body, true);
                    if (is_array($data) && isset($data['Browser'])) {
                        // Simpan host yang bekerja — dipakai oleh createNewTarget, dll.
                        $this->debugHost = $host;
                        $this->logger->debug("Chromium ready ({$host}): " . ($data['Browser'] ?? ''));
                        return;
                    }
                }
            }

            usleep(300_000); // 300ms per poll — lebih hemat CPU di perangkat lambat
        }

        $hint = $stderrBuf !== ''
            ? "\nOutput Chromium:\n" . substr($stderrBuf, -1000)
            : " Coba naikkan startup_timeout: \$browser->launch(['startup_timeout' => 120000])";

        throw new TimeoutException(
            "Chromium tidak merespons dalam {$timeoutMs}ms.{$hint}",
            $timeoutMs
        );
    }

    private function buildArgs(int $port, bool $headless, string $userDataDir, array $options): array
    {
        $args = [
            "--remote-debugging-port={$port}",
            '--no-first-run',
            '--no-default-browser-check',
            '--disable-extensions',
            '--disable-popup-blocking',
            '--disable-translate',
            '--disable-background-networking',
            '--safebrowsing-disable-auto-update',
            '--password-store=basic',
            '--use-mock-keychain',
        ];

        if ($headless) {
            $args[] = '--headless=new';
        }

        if ($this->config->get('disable_gpu', true)) {
            $args[] = '--disable-gpu';
        }

        // Support env var XBROWSER_NO_SANDBOX=true (digunakan di Docker)
        $noSandboxEnv = filter_var(getenv('XBROWSER_NO_SANDBOX'), FILTER_VALIDATE_BOOLEAN);
        if ($options['no_sandbox'] ?? $this->config->get('no_sandbox', $noSandboxEnv)) {
            $args[] = '--no-sandbox';
            $args[] = '--disable-setuid-sandbox';
        }

        // ── Termux / Android ──────────────────────────────────────────────────
        // /dev/shm tidak tersedia di Android → Chromium crash saat startup.
        // --no-zygote diperlukan karena proses zygote sering gagal di Termux.
        // --log-level=3 menekan ratusan error dbus/inotify yang normal di Android
        //   (dbus tidak ada di Android, error-nya harmless tapi memenuhi log).
        // Flag ini aman di platform lain (diabaikan atau tidak berpengaruh).
        if ($this->isTermux()) {
            if (!in_array('--disable-dev-shm-usage', $args, true)) {
                $args[] = '--disable-dev-shm-usage';
            }
            if (!in_array('--no-zygote', $args, true)) {
                $args[] = '--no-zygote';
            }
            // Hanya tampilkan error fatal; sembunyikan dbus/inotify noise
            if (!in_array('--log-level=3', $args, true)) {
                $args[] = '--log-level=3';
            }
            $this->logger->debug("Termux detected: added --disable-dev-shm-usage --no-zygote --log-level=3");
        }

        // Allow explicit override via option or config (non-Termux juga bisa pakai ini)
        if (($options['disable_dev_shm'] ?? $this->config->get('disable_dev_shm', false))
            && !in_array('--disable-dev-shm-usage', $args, true)) {
            $args[] = '--disable-dev-shm-usage';
        }

        if ($userDataDir) {
            $args[] = "--user-data-dir={$userDataDir}";
        }

        $width  = (int) $this->config->get('window_width', 1280);
        $height = (int) $this->config->get('window_height', 800);
        $args[] = "--window-size={$width},{$height}";

        if (isset($options['proxy'])) {
            $args[] = "--proxy-server={$options['proxy']}";
        }

        $args[] = 'about:blank';

        return $args;
    }

    private function ensureLaunched(): void
    {
        if (!$this->launched) {
            $this->launch();
        }
    }

    /**
     * Deteksi apakah berjalan di lingkungan Termux (Android).
     * Cek TERMUX_VERSION env var atau keberadaan direktori Termux.
     */
    private function isTermux(): bool
    {
        return getenv('TERMUX_VERSION') !== false
            || isset($_SERVER['TERMUX_VERSION'])
            || is_dir('/data/data/com.termux');
    }
}
