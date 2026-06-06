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
    private ?Page $currentPage = null;
    private bool  $launched    = false;
    private int   $port        = 9222;
    private bool  $stealth     = true;

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

        $this->port   = (int)  ($options['port']    ?? $this->config->get('remote_debugging_port', 9222));
        $headless     = (bool) ($options['headless'] ?? $this->config->get('headless', true));
        $chromium     = $options['chromium'] ?? $this->config->getChromiumPath();
        $userDataDir  = $options['userDataDir'] ?? $this->config->get('user_data_dir', '');
        $this->stealth = (bool) ($options['stealth'] ?? $this->config->get('stealth', true));

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
        $this->waitForBrowserReady($this->port);

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
        $targetInfo = $this->createNewTarget();
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
     */
    private function createNewTarget(int $timeoutMs = 5000): array
    {
        // Chromium 112+ requires PUT for /json/new
        $url = "http://localhost:{$this->port}/json/new";
        $ctx = stream_context_create([
            'http' => [
                'method'  => 'PUT',
                'timeout' => $timeoutMs / 1000,
                'header'  => 'Content-Length: 0',
                'content' => '',
            ],
        ]);
        $body = @file_get_contents($url, false, $ctx);

        if ($body !== false) {
            $data = json_decode($body, true);
            if (is_array($data) && !empty($data['webSocketDebuggerUrl'])) {
                return $data;
            }
        }

        // Fallback: find an existing "page" type target from /json/list
        return $this->findOrWaitForPageTarget($timeoutMs);
    }

    /**
     * Poll /json until a target of type "page" appears.
     */
    private function findOrWaitForPageTarget(int $timeoutMs = 5000): array
    {
        $deadline = microtime(true) + $timeoutMs / 1000;
        $ctx      = stream_context_create(['http' => ['timeout' => 2]]);

        while (microtime(true) < $deadline) {
            $url  = "http://localhost:{$this->port}/json";
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
     */
    private function waitForBrowserReady(int $port, int $timeoutMs = 15000): void
    {
        $deadline = microtime(true) + $timeoutMs / 1000;

        while (microtime(true) < $deadline) {
            $url  = "http://localhost:{$port}/json/version";
            $ctx  = stream_context_create(['http' => ['timeout' => 1]]);
            $body = @file_get_contents($url, false, $ctx);

            if ($body !== false) {
                $data = json_decode($body, true);
                if (is_array($data) && isset($data['Browser'])) {
                    $this->logger->debug("Chromium ready: " . ($data['Browser'] ?? ''));
                    return;
                }
            }

            usleep(200_000);
        }

        throw new TimeoutException("Waiting for Chromium to be ready", $timeoutMs);
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

        if ($options['no_sandbox'] ?? $this->config->get('no_sandbox', false)) {
            $args[] = '--no-sandbox';
            $args[] = '--disable-setuid-sandbox';
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
}
