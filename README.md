# Xbrowser

A terminal-based browser powered by Chrome DevTools Protocol (CDP) and Headless Chromium. Browse, interact with, and automate modern web pages — including JavaScript-heavy sites like React, Vue, Next.js — entirely from your terminal.

```
  ██╗  ██╗██████╗ ██████╗  ██████╗ ██╗    ██╗███████╗███████╗██████╗
  ╚██╗██╔╝██╔══██╗██╔══██╗██╔═══██╗██║    ██║██╔════╝██╔════╝██╔══██╗
   ╚███╔╝ ██████╔╝██████╔╝██║   ██║██║ █╗ ██║███████╗█████╗  ██████╔╝
   ██╔██╗ ██╔══██╗██╔══██╗██║   ██║██║███╗██║╚════██║██╔══╝  ██╔══██╗
  ██╔╝ ██╗██████╔╝██║  ██║╚██████╔╝╚███╔███╔╝███████║███████╗██║  ██║
  ╚═╝  ╚═╝╚═════╝ ╚═╝  ╚═╝ ╚═════╝  ╚══╝╚══╝ ╚══════╝╚══════╝╚═╝  ╚═╝
```

---

## Requirements

- PHP 8.4+
- Composer
- Chromium or Google Chrome installed
- POSIX-compatible OS (Linux, macOS)

---

## Installation

```bash
git clone https://github.com/your-org/xbrowser.git
cd xbrowser
composer install
chmod +x bin/Xbrowser
```

Optionally link globally:

```bash
sudo ln -s "$(pwd)/bin/Xbrowser" /usr/local/bin/Xbrowser
```

---

## Quick Start

```bash
# Open a URL and render it in the terminal
Xbrowser open https://example.com

# Start the interactive shell
Xbrowser shell
```

---

## CLI Commands

### Open a URL

```bash
Xbrowser open https://github.com
```

Launches Chromium, loads the page (including JavaScript), and renders the result in your terminal.

---

### Click an Element

```bash
Xbrowser click https://example.com "#login"
```

---

### Type into an Element

```bash
Xbrowser type https://example.com "#email" "user@example.com"
```

---

### Evaluate JavaScript

```bash
Xbrowser eval https://example.com "document.title"
Xbrowser eval https://example.com "document.querySelectorAll('a').length"
```

---

### Take a Screenshot

```bash
Xbrowser screenshot https://example.com page.png
Xbrowser screenshot https://example.com page.jpg   # JPEG format
```

---

### Dump Raw HTML

```bash
Xbrowser html https://example.com
Xbrowser html https://example.com > page.html
```

---

### Inspect Network Traffic

```bash
Xbrowser network https://example.com
```

Output shows all requests, their methods, response status codes, and URLs.

---

### Session Management

Save the current cookies, localStorage, and URL:

```bash
Xbrowser save-session https://github.com mysession
```

Restore a session later:

```bash
Xbrowser load-session mysession
```

Sessions are stored in `~/.xbrowser/sessions/`.

---

### Record Actions

```bash
Xbrowser record
```

This opens an interactive recorder. Every action you take (open, click, type, etc.) is captured and output as a PHP automation script:

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

Starts an interactive REPL-style browser shell:

```
Xbrowser v1.0.0 — Terminal Browser
Type 'help' for available commands.

[Xbrowser] > open https://github.com
[GitHub · Build and ship software on a single, collaborative platform] > render
[GitHub · ...] > click a[href="/login"]
[GitHub · ...] > type #login_field myuser
[GitHub · ...] > type #password mysecret
[GitHub · ...] > submit
[GitHub · ...] > screenshot dashboard.png
[GitHub · ...] > save-session github-session
[GitHub · ...] > quit
```

### Shell Commands

| Command                  | Description                          |
|--------------------------|--------------------------------------|
| `open <url>`             | Navigate to a URL                    |
| `render`                 | Render current page to terminal      |
| `html`                   | Dump raw HTML                        |
| `click <selector>`       | Click a CSS selector                 |
| `type <selector> <text>` | Type into an element                 |
| `eval <expression>`      | Evaluate JavaScript                  |
| `screenshot <file>`      | Take a screenshot                    |
| `submit [selector]`      | Submit a form                        |
| `back`                   | Go back                              |
| `forward`                | Go forward                           |
| `reload`                 | Reload the page                      |
| `wait <selector>`        | Wait for an element to appear        |
| `cookies`                | Show current cookies                 |
| `title`                  | Show page title                      |
| `url`                    | Show current URL                     |
| `save-session <name>`    | Save browser session                 |
| `load-session <name>`    | Load a saved session                 |
| `network`                | Show network request summary         |
| `clear`                  | Clear the terminal                   |
| `help`                   | Show command list                    |
| `quit` / `exit`          | Close and exit                       |

---

## Automation API

Xbrowser can be used as a PHP library for browser automation — similar to Playwright or Selenium.

```php
<?php

require 'vendor/autoload.php';

use Xbrowser\Browser\BrowserFactory;

$browser = BrowserFactory::create(['verbose' => true]);
$browser->launch();

$page = $browser->newPage();
$page->goto('https://example.com');

// Query elements
$heading = $page->query('h1');

// Click
$page->click('#login-button');

// Type text
$page->type('#email', 'admin@example.com');
$page->type('#password', 'secret');

// Submit form
$page->submit('#login-form');

// Wait for navigation
$page->waitForNavigation();

// Wait for element
$page->waitForSelector('.dashboard-header');

// Evaluate JavaScript
$title    = $page->evaluate('document.title');
$linkCount = $page->evaluate('document.querySelectorAll("a").length');

// Take screenshot
$page->screenshot('dashboard.png');

// Get page HTML
$html = $page->html();

// Render to terminal
echo $page->render();

$browser->close();
```

---

## Scraping Example

```php
<?php

require 'vendor/autoload.php';

use Xbrowser\Browser\BrowserFactory;

$browser = BrowserFactory::create();
$browser->launch();

$page = $browser->newPage();
$page->goto('https://news.ycombinator.com');

$titles = $page->evaluate(<<<JS
    Array.from(document.querySelectorAll('.titleline > a'))
        .slice(0, 10)
        .map(el => ({ title: el.textContent, href: el.href }))
JS);

foreach ($titles as $item) {
    echo "• " . $item['title'] . "\n";
    echo "  " . $item['href'] . "\n\n";
}

$browser->close();
```

---

## Session Management API

```php
<?php

use Xbrowser\Browser\BrowserFactory;
use Xbrowser\Utils\SessionManager;

$browser  = BrowserFactory::create();
$sessions = new SessionManager();

$browser->launch();
$page = $browser->newPage();

// Load a saved session (cookies + URL)
$page->loadSession('my-session', $sessions);

// Save the current session
$page->saveSession('my-session', $sessions);

$browser->close();
```

---

## Network Inspection API

```php
<?php

use Xbrowser\Browser\BrowserFactory;

$browser = BrowserFactory::create();
$browser->launch();

$page      = $browser->newPage();
$inspector = $page->getNetworkInspector();

$page->goto('https://example.com');

foreach ($inspector->getRequests() as $req) {
    echo "{$req->method} {$req->url}\n";
}

foreach ($inspector->getResponses() as $resp) {
    echo "{$resp->statusCode} {$resp->url}\n";
}

$summary = $inspector->summary();
echo "Total: {$summary['total']} | OK: {$summary['succeeded']} | Failed: {$summary['failed']}\n";

$browser->close();
```

---

## Event System

```php
<?php

use Xbrowser\Browser\BrowserFactory;
use Xbrowser\Events\PageLoadedEvent;
use Xbrowser\Events\ClickEvent;

$browser    = BrowserFactory::create();
$dispatcher = $browser->getDispatcher();

$dispatcher->on('page.loaded', function (PageLoadedEvent $event): void {
    echo "Page loaded: " . $event->getUrl() . "\n";
});

$dispatcher->on('element.clicked', function (ClickEvent $event): void {
    echo "Clicked: " . $event->getSelector() . "\n";
});

$browser->launch();
$page = $browser->newPage();
$page->goto('https://example.com');
$page->click('a');

$browser->close();
```

### Available Events

| Event Name            | Class                     | When fired                   |
|-----------------------|---------------------------|------------------------------|
| `page.loaded`         | `PageLoadedEvent`         | Page fully loaded            |
| `page.navigation`     | `NavigationEvent`         | URL changes                  |
| `element.clicked`     | `ClickEvent`              | `$page->click()` called      |
| `dom.updated`         | `DomUpdatedEvent`         | DOM mutation detected        |
| `javascript.executed` | `JavaScriptExecutedEvent` | `$page->evaluate()` called   |
| `network.request`     | `NetworkRequestEvent`     | Network request sent         |
| `*`                   | —                         | All events (wildcard)        |

---

## Plugin Development

Create a directory under `plugins/` with a `plugin.php` file that returns a `PluginInterface` instance:

```
plugins/
└── my-plugin/
    └── plugin.php
```

```php
<?php
// plugins/my-plugin/plugin.php

use Xbrowser\Browser\Browser;
use Xbrowser\Plugin\PluginInterface;

return new class implements PluginInterface
{
    public function getName(): string    { return 'my-plugin'; }
    public function getVersion(): string { return '1.0.0'; }
    public function getDescription(): string { return 'My custom plugin'; }

    public function register(Browser $browser): void
    {
        $browser->getDispatcher()->on('page.loaded', function ($event): void {
            echo "[my-plugin] Page loaded: " . $event->getPayload()['url'] . "\n";
        });
    }

    public function getCommands(): array
    {
        return [
            'my-command' => function (array $args): void {
                echo "Hello from my-plugin!\n";
            },
        ];
    }
};
```

Plugins are auto-discovered from the `plugins/` directory on startup.

---

## Configuration

Xbrowser reads configuration from `~/.xbrowser/config.json`:

```json
{
    "chromium_path": "/usr/bin/chromium",
    "remote_debugging_port": 9222,
    "timeout": 30000,
    "headless": true,
    "disable_gpu": true,
    "no_sandbox": false,
    "window_width": 1280,
    "window_height": 800,
    "verbose": false,
    "log_file": ""
}
```

### Environment Variables

| Variable            | Description                     |
|---------------------|---------------------------------|
| `XBROWSER_CHROMIUM` | Path to Chromium binary         |
| `XBROWSER_PORT`     | Remote debugging port           |
| `XBROWSER_TIMEOUT`  | Default timeout in ms           |
| `XBROWSER_VERBOSE`  | Enable verbose logging          |
| `XBROWSER_LOG_FILE` | Path to log file                |

---

## Terminal Renderer

HTML elements are mapped to terminal text:

| HTML Element | Terminal Output           |
|--------------|---------------------------|
| `<h1>`       | `# HEADING`               |
| `<h2>`       | `## HEADING`              |
| `<h3>`       | `### HEADING`             |
| `<a>`        | `[Link Text](url)`        |
| `<button>`   | `[ Button ]`              |
| `<input>`    | `[__placeholder______]`   |
| `<textarea>` | Bordered multi-line box   |
| `<ul>/<li>`  | `• Bullet item`           |
| `<ol>/<li>`  | `1. Numbered item`        |
| `<table>`    | ASCII bordered table      |
| `<img>`      | `[IMAGE: alt text]`       |
| `<hr>`       | `────────────────────`    |
| `<pre>`      | Bordered code block       |
| `<blockquote>` | `▌ Quoted text`         |
| `<strong>`   | Bold text (ANSI)          |
| `<code>`     | `` `inline code` ``       |

---

## Running Tests

```bash
# Run all tests
composer test

# Run with verbose output
./vendor/bin/phpunit --testdox

# Run specific suite
./vendor/bin/phpunit --testsuite Unit

# Generate HTML coverage report
composer test:coverage
```

---

## Project Structure

```
Xbrowser/
├── bin/
│   └── Xbrowser              ← CLI entry point
├── src/
│   ├── Browser/
│   │   ├── Browser.php        ← Core browser controller
│   │   ├── BrowserFactory.php ← DI factory
│   │   └── Page.php           ← Page automation API
│   ├── CDP/
│   │   ├── Client.php         ← CDP message client
│   │   ├── Protocol.php       ← CDP command builders
│   │   └── WebSocketClient.php← Pure PHP WebSocket (RFC 6455)
│   ├── DOM/
│   │   ├── DOMManager.php     ← DOM queries via CDP
│   │   └── Element.php        ← DOM element wrapper
│   ├── Renderer/
│   │   ├── HtmlParser.php     ← Pure PHP HTML parser
│   │   └── TerminalRenderer.php← HTML → terminal text
│   ├── Commands/
│   │   ├── Application.php    ← CLI router
│   │   ├── OpenCommand.php
│   │   ├── ShellCommand.php   ← Interactive shell
│   │   ├── RecordCommand.php  ← Action recorder
│   │   └── ...
│   ├── Networking/
│   │   ├── NetworkInspector.php
│   │   ├── NetworkRequest.php
│   │   └── NetworkResponse.php
│   ├── Events/
│   │   ├── EventDispatcher.php
│   │   ├── PageLoadedEvent.php
│   │   └── ...
│   ├── Exceptions/
│   │   ├── TimeoutException.php
│   │   ├── SelectorNotFoundException.php
│   │   └── ...
│   ├── Plugin/
│   │   ├── PluginInterface.php
│   │   └── PluginManager.php
│   └── Utils/
│       ├── Logger.php
│       ├── SessionManager.php
│       ├── ConfigManager.php
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
│   ├── github_login.php
│   └── scraping.php
├── composer.json
├── phpunit.xml
└── README.md
```

---

## Troubleshooting

### Chromium not found

Set the path explicitly:

```bash
export XBROWSER_CHROMIUM=/usr/bin/chromium-browser
# or in ~/.xbrowser/config.json:
{ "chromium_path": "/usr/bin/chromium" }
```

### Sandbox errors on Linux

If you see `No usable sandbox!`:

```json
{ "no_sandbox": true }
```

Or in config: `XBROWSER_NOSANDBOX=1`

### Timeout errors

Increase the global timeout:

```bash
export XBROWSER_TIMEOUT=60000
```

### Connection refused (port 9222)

Another Chromium instance may be using port 9222. Change the port:

```json
{ "remote_debugging_port": 9333 }
```

### Page renders blank

Some SPAs need extra time for JavaScript to execute. The browser already waits for `document.readyState === 'complete'`, but you can also use:

```php
$page->waitForSelector('.my-app-root');
```

---

## Architecture Decisions

- **Pure PHP WebSocket** — RFC 6455 implementation with no external dependencies. Frame encoding/decoding and the handshake are all custom.
- **CDP over WebSocket** — All browser control uses the Chrome DevTools Protocol, making Chromium the JS engine. No custom JS engine.
- **HTML Parser** — A hand-rolled recursive descent parser handles real-world HTML well enough for terminal rendering without requiring `ext-dom` or `libxml`.
- **SOLID OOP** — Each class has a single responsibility. `Browser` manages process lifecycle, `Page` exposes the automation API, `DOMManager` handles selectors, `TerminalRenderer` handles output.
- **Event-driven** — An `EventDispatcher` with `on()`, `once()`, `off()` lets plugins and user code react to browser lifecycle events.

---

## License

MIT — free for personal and commercial use. Contributions welcome.
