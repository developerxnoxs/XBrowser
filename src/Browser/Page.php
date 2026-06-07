<?php

declare(strict_types=1);

namespace Xbrowser\Browser;

use Xbrowser\CDP\Client;
use Xbrowser\CDP\Protocol;
use Xbrowser\DOM\DOMManager;
use Xbrowser\DOM\Element;
use Xbrowser\Events\ClickEvent;
use Xbrowser\Events\DomUpdatedEvent;
use Xbrowser\Events\EventDispatcher;
use Xbrowser\Events\JavaScriptExecutedEvent;
use Xbrowser\Events\NavigationEvent;
use Xbrowser\Events\PageLoadedEvent;
use Xbrowser\Exceptions\JavaScriptException;
use Xbrowser\Exceptions\SelectorNotFoundException;
use Xbrowser\Exceptions\TimeoutException;
use Xbrowser\Networking\NetworkCapture;
use Xbrowser\Networking\NetworkInspector;
use Xbrowser\Networking\RequestInterceptor;
use Xbrowser\Renderer\TerminalRenderer;
use Xbrowser\Utils\Logger;
use Xbrowser\Utils\SessionManager;
use Xbrowser\Utils\UrlValidator;

class Page
{
    private string $currentUrl = '';
    private string $currentTitle = '';
    private DOMManager $dom;
    private NetworkInspector $network;
    private TerminalRenderer $renderer;
    private array $recordedActions = [];
    private bool $recording = false;

    public function __construct(
        private readonly Client $cdp,
        private readonly EventDispatcher $dispatcher,
        private readonly Logger $logger,
        private readonly int $defaultTimeout = 30000
    ) {
        $this->dom      = new DOMManager($cdp);
        $this->network  = new NetworkInspector($cdp);
        $this->renderer = new TerminalRenderer();

        $this->setupCdpDomains();
    }

    public function goto(string $url, int $timeoutMs = 0): void
    {
        $timeout   = $timeoutMs ?: $this->defaultTimeout;
        $url       = UrlValidator::validate($url);
        $fromUrl   = $this->currentUrl;

        $this->logger->info("Navigating to: {$url}");
        $this->cdp->send(Protocol::navigate($url), $timeout);
        $this->waitForLoad($timeout);

        $this->currentUrl = $url;
        $this->dom->refresh();

        $this->currentTitle = $this->evaluate('document.title');

        if ($fromUrl) {
            $this->dispatcher->emit(new NavigationEvent($fromUrl, $url));
        }

        $this->dispatcher->emit(new PageLoadedEvent($url, $this->currentTitle));
        $this->logger->success("Page loaded: {$this->currentTitle}");

        if ($this->recording) {
            $this->recordedActions[] = "\$page->goto('" . addslashes($url) . "');";
        }
    }

    public function click(string $selector, int $timeoutMs = 0): void
    {
        $this->logger->debug("Clicking: {$selector}");
        $element = $this->query($selector);
        $center  = $this->dom->getElementCenter($element->getNodeId());

        // Physical mouse simulation (trusted event)
        $this->cdp->send(Protocol::dispatchMouseEvent('mouseMoved',   $center['x'], $center['y']));
        $this->cdp->send(Protocol::dispatchMouseEvent('mousePressed', $center['x'], $center['y'], 0, 1));
        $this->cdp->send(Protocol::dispatchMouseEvent('mouseReleased',$center['x'], $center['y'], 0, 1));

        // Also fire JS .click() so React/Vue onClick handlers always fire
        $sel = addslashes($selector);
        $this->evaluate("document.querySelector('{$sel}')?.click()");

        $this->dispatcher->emit(new ClickEvent($selector, $center['x'], $center['y']));
        $this->logger->debug("Clicked element at ({$center['x']}, {$center['y']})");

        if ($this->recording) {
            $this->recordedActions[] = "\$page->click('" . addslashes($selector) . "');";
        }
    }

    /**
     * Type text into an input field.
     *
     * Uses the native HTMLInputElement value setter so React / Vue controlled
     * inputs register the change in their internal state — plain `.value = x`
     * or CDP `Input.insertText` bypass the framework's event system and leave
     * the form state empty on submit.
     */
    public function type(string $selector, string $text, int $timeoutMs = 0): void
    {
        $element = $this->query($selector);
        $center  = $this->dom->getElementCenter($element->getNodeId());

        // 1. Click to focus (triggers framework onFocus)
        $this->cdp->send(Protocol::dispatchMouseEvent('mousePressed', $center['x'], $center['y'], 0, 1));
        $this->cdp->send(Protocol::dispatchMouseEvent('mouseReleased',$center['x'], $center['y'], 0, 1));

        // 2. Clear any autofill value using Select-All + Delete key
        $this->cdp->send(Protocol::dispatchKeyEvent('keyDown', 'a', 'a', ['ctrl' => true]));
        $this->cdp->send(Protocol::dispatchKeyEvent('keyUp',   'a', 'a', ['ctrl' => true]));
        $this->cdp->send(Protocol::dispatchKeyEvent('keyDown', 'Delete', ''));
        $this->cdp->send(Protocol::dispatchKeyEvent('keyUp',   'Delete', ''));

        // 3. Set value via the native prototype setter — this bypasses React's
        //    immutable controlled-input guard while still marking the fiber dirty
        $sel  = addslashes($selector);
        $val  = addslashes($text);
        $this->evaluate(<<<JS
            (() => {
                const el = document.querySelector('{$sel}');
                if (!el) return;
                // Clear autofill residue
                el.value = '';
                // Native setter: sets the value and marks the React fiber as dirty
                const proto  = Object.getPrototypeOf(el);
                const desc   = Object.getOwnPropertyDescriptor(proto, 'value')
                            ?? Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value');
                if (desc?.set) {
                    desc.set.call(el, '{$val}');
                } else {
                    el.value = '{$val}';
                }
                // Dispatch every event React / Vue / Angular listen to
                ['focus','input','change','blur'].forEach(type => {
                    el.dispatchEvent(new Event(type, { bubbles: true, cancelable: true }));
                });
            })()
        JS);

        $this->logger->debug("Typed into {$selector}");

        if ($this->recording) {
            $escaped = addslashes($text);
            $this->recordedActions[] = "\$page->type('" . addslashes($selector) . "', '{$escaped}');";
        }
    }

    public function evaluate(string $expression, int $timeoutMs = 0): mixed
    {
        $timeout = $timeoutMs ?: $this->defaultTimeout;
        $result  = $this->cdp->send(Protocol::evaluate($expression, true, true), $timeout);

        if (isset($result['error'])) {
            $msg = $result['error']['message'] ?? 'Unknown JS error';
            $event = new JavaScriptExecutedEvent($expression, null, true);
            $this->dispatcher->emit($event);
            throw new JavaScriptException("Evaluation failed", $msg);
        }

        $value = $result['result']['value'] ?? null;
        $event = new JavaScriptExecutedEvent($expression, $value);
        $this->dispatcher->emit($event);

        return $value;
    }

    public function query(string $selector): Element
    {
        return $this->dom->querySelector($selector);
    }

    public function queryAll(string $selector): array
    {
        return $this->dom->querySelectorAll($selector);
    }

    public function waitForSelector(string $selector, int $timeoutMs = 0): Element
    {
        $timeout  = $timeoutMs ?: $this->defaultTimeout;
        $deadline = microtime(true) + $timeout / 1000;

        $this->logger->debug("Waiting for selector: {$selector}");

        while (microtime(true) < $deadline) {
            try {
                $element = $this->dom->querySelector($selector);
                $this->logger->debug("Selector found: {$selector}");
                return $element;
            } catch (SelectorNotFoundException) {
                $this->cdp->pump(50);
                usleep(100_000);
            }
        }

        throw new TimeoutException("waitForSelector({$selector})", $timeout);
    }

    public function waitForNavigation(int $timeoutMs = 0): void
    {
        $this->waitForLoad($timeoutMs ?: $this->defaultTimeout);
        $this->dom->refresh();
        $this->currentUrl   = (string) $this->evaluate('window.location.href');
        $this->currentTitle = (string) $this->evaluate('document.title');
    }

    public function waitForLoad(int $timeoutMs = 30000): void
    {
        $deadline = microtime(true) + $timeoutMs / 1000;
        $loaded   = false;

        $this->cdp->onEvent('Page.loadEventFired', function () use (&$loaded): void {
            $loaded = true;
        });

        while (!$loaded && microtime(true) < $deadline) {
            $this->cdp->pump(100);
            usleep(50_000);

            try {
                $state = $this->evaluate("document.readyState");
                if ($state === 'complete') {
                    $loaded = true;
                }
            } catch (\Exception) {}
        }
    }

    public function screenshot(string $outputPath, string $format = 'png'): void
    {
        $this->logger->info("Taking screenshot...");
        $result = $this->cdp->send(Protocol::captureScreenshot($format));

        if (empty($result['data'])) {
            throw new \RuntimeException("Screenshot failed: no data returned");
        }

        $dir = dirname($outputPath);
        if ($dir && !is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($outputPath, base64_decode($result['data']));
        $this->logger->success("Screenshot saved to: {$outputPath}");
    }

    public function html(): string
    {
        return $this->dom->getOuterHTML();
    }

    public function render(): string
    {
        $html = $this->html();
        return $this->renderer->render($html);
    }

    public function submit(string $selector = 'form'): void
    {
        $this->evaluate("document.querySelector('" . addslashes($selector) . "').submit()");
        $this->waitForNavigation();

        if ($this->recording) {
            $this->recordedActions[] = "\$page->submit('" . addslashes($selector) . "');";
        }
    }

    public function reload(bool $ignoreCache = false): void
    {
        $this->cdp->send(Protocol::reload($ignoreCache));
        $this->waitForLoad();
        $this->dom->refresh();
    }

    public function goBack(): void
    {
        $this->cdp->send(Protocol::goBack());
        $this->waitForNavigation();
    }

    public function goForward(): void
    {
        $this->cdp->send(Protocol::goForward());
        $this->waitForNavigation();
    }

    public function getTitle(): string
    {
        return $this->currentTitle;
    }

    public function getUrl(): string
    {
        return $this->currentUrl;
    }

    public function getCookies(): array
    {
        $result = $this->cdp->send(Protocol::getAllCookies());
        return $result['cookies'] ?? [];
    }

    public function setCookies(array $cookies): void
    {
        $this->cdp->send(Protocol::setCookies($cookies));
    }

    public function clearCookies(): void
    {
        $this->cdp->send(Protocol::clearBrowserCookies());
    }

    public function getLocalStorage(): array
    {
        $json = $this->evaluate('JSON.stringify(window.localStorage)');
        return json_decode((string) $json, true) ?? [];
    }

    public function getSessionStorage(): array
    {
        $json = $this->evaluate('JSON.stringify(window.sessionStorage)');
        return json_decode((string) $json, true) ?? [];
    }

    public function saveSession(string $name, SessionManager $sessions): void
    {
        $sessions->save($name, [
            'url'            => $this->currentUrl,
            'cookies'        => $this->getCookies(),
            'localStorage'   => $this->getLocalStorage(),
            'sessionStorage' => $this->getSessionStorage(),
        ]);
        $this->logger->success("Session saved: {$name}");
    }

    public function loadSession(string $name, SessionManager $sessions): void
    {
        $data = $sessions->load($name);

        if (!empty($data['cookies'])) {
            $this->setCookies($data['cookies']);
        }

        if (!empty($data['url'])) {
            $this->goto($data['url']);
        }

        $this->logger->success("Session loaded: {$name}");
    }

    public function startRecording(): void
    {
        $this->recording       = true;
        $this->recordedActions = [];
        $this->logger->info("Recording started");
    }

    public function stopRecording(): string
    {
        $this->recording = false;
        $script = $this->generateScript();
        $this->logger->info("Recording stopped. " . count($this->recordedActions) . " actions recorded.");
        return $script;
    }

    public function generateScript(): string
    {
        $lines   = ["<?php", "", "require 'vendor/autoload.php';", "", "use Xbrowser\Browser\Browser;", "", "\$browser = new Browser();", "\$page = \$browser->newPage();", ""];
        $lines   = array_merge($lines, $this->recordedActions);
        $lines[] = "";
        $lines[] = "\$browser->close();";
        return implode(PHP_EOL, $lines);
    }

    public function getRecordedActions(): array
    {
        return $this->recordedActions;
    }

    public function getNetworkInspector(): NetworkInspector
    {
        $this->network->enable();
        return $this->network;
    }

    /**
     * Buat & aktifkan RequestInterceptor — blokir, modifikasi, atau mock request
     * sebelum dikirim ke server (menggunakan CDP Fetch domain).
     *
     * Berbeda dari startCapture() yang hanya mengamati traffic (pasif),
     * intercept() menghentikan setiap request dan menunggu keputusan handler.
     *
     * Contoh:
     *   $interceptor = $page->intercept();
     *
     *   // Blokir gambar dan iklan
     *   $interceptor->blockResourceTypes(['Image', 'Media'])->blockAds();
     *
     *   // Atau handler manual
     *   $interceptor->onRequest(function ($req) {
     *       if (str_contains($req->url, '/ads/')) {
     *           $req->abort();
     *       } else {
     *           $req->continue(['headers' => array_merge($req->headers, ['X-Bot' => '0'])]);
     *       }
     *   });
     *
     *   $page->goto('https://example.com');
     *   $interceptor->disable(); // matikan setelah selesai
     */
    public function intercept(array $patterns = []): RequestInterceptor
    {
        $interceptor = new RequestInterceptor($this->cdp);

        if (!empty($patterns)) {
            foreach ($patterns as $pattern) {
                if (is_string($pattern)) {
                    $interceptor->addPattern($pattern);
                } elseif (is_array($pattern)) {
                    $interceptor->addPattern(
                        $pattern['urlPattern']   ?? '*',
                        $pattern['resourceType'] ?? null
                    );
                }
            }
        }

        $interceptor->enable();
        return $interceptor;
    }

    /**
     * Buat & aktifkan NetworkCapture — intercept penuh request/response/body.
     *
     * Setara dengan blok CDP di intercept.js (Puppeteer):
     *   Network.requestWillBeSent + responseReceived + loadingFinished
     *
     * Contoh:
     *   $capture = $page->startCapture();
     *   $capture->filterDomain('facebook.com')
     *           ->scanCredentials(['email@', 'pass=']);
     *   $page->goto('https://m.facebook.com/login/');
     *   $capture->fetchPendingBodies();
     *   $capture->saveJson('result.json');
     */
    public function startCapture(): NetworkCapture
    {
        $capture = new NetworkCapture($this->cdp);
        $capture->enable();
        return $capture;
    }

    public function setViewport(int $width, int $height): void
    {
        $this->cdp->send(Protocol::setViewport($width, $height));
    }

    public function setExtraHeaders(array $headers): void
    {
        $this->cdp->send(Protocol::setExtraHTTPHeaders($headers));
    }

    public function setUserAgent(string $userAgent): void
    {
        $this->cdp->send(Protocol::setUserAgentOverride($userAgent));
    }

    public function cdpPump(int $timeoutMs = 100): void
    {
        $this->cdp->pump($timeoutMs);
    }

    public function checkBotDetection(): BotDetectionResult
    {
        $checks = [];

        $checks['webdriver']    = $this->evaluate('navigator.webdriver');
        $checks['pluginCount']  = (int) $this->evaluate('navigator.plugins.length');
        $checks['hasChrome']    = $this->evaluate('typeof window.chrome !== "undefined"');
        $checks['languages']    = $this->evaluate('navigator.languages.length');
        $checks['userAgent']    = $this->evaluate('navigator.userAgent');
        $checks['hardwareConcurrency'] = $this->evaluate('navigator.hardwareConcurrency');
        $checks['deviceMemory'] = $this->evaluate('navigator.deviceMemory ?? "not supported"');

        return new BotDetectionResult(
            webdriverHidden:     $checks['webdriver'] === null || $checks['webdriver'] === false,
            pluginsPresent:      ($checks['pluginCount'] ?? 0) > 0,
            chromeObjectPresent: $checks['hasChrome'] === true,
            languagesPresent:    ($checks['languages'] ?? 0) > 0,
            userAgentClean:      !str_contains((string)($checks['userAgent'] ?? ''), 'HeadlessChrome'),
            userAgent:           (string)($checks['userAgent'] ?? ''),
            rawChecks:           $checks
        );
    }

    private function setupCdpDomains(): void
    {
        $this->cdp->send(Protocol::enable('Page'));
        $this->cdp->send(Protocol::enable('DOM'));
        $this->cdp->send(Protocol::enable('Runtime'));
        $this->cdp->send(Protocol::waitForLoad());
    }
}
