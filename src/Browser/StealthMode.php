<?php

declare(strict_types=1);

namespace Xbrowser\Browser;

use Xbrowser\CDP\Client;
use Xbrowser\CDP\Protocol;

/**
 * Stealth mode patches to evade bot detection.
 *
 * Applies CDP-level overrides to hide Chromium's headless/automation
 * fingerprints before any page loads. Modeled after puppeteer-extra-plugin-stealth.
 */
class StealthMode
{
    private const USER_AGENT = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36';

    private const STEALTH_SCRIPT = <<<'JS'
// 1. Hide navigator.webdriver
Object.defineProperty(navigator, 'webdriver', {
    get: () => undefined,
    configurable: true
});

// 2. Fake Chrome object (missing in headless)
window.chrome = {
    app: {
        isInstalled: false,
        InstallState: { DISABLED: 'disabled', INSTALLED: 'installed', NOT_INSTALLED: 'not_installed' },
        RunningState: { CANNOT_RUN: 'cannot_run', READY_TO_RUN: 'ready_to_run', RUNNING: 'running' }
    },
    runtime: {
        OnInstalledReason: {},
        OnRestartRequiredReason: {},
        PlatformArch: {},
        PlatformNaclArch: {},
        PlatformOs: {},
        RequestUpdateCheckStatus: {}
    },
    loadTimes: function() {},
    csi: function() {}
};

// 3. Fix permissions (headless returns different results)
const originalQuery = window.navigator.permissions.query;
window.navigator.permissions.query = (parameters) => (
    parameters.name === 'notifications'
        ? Promise.resolve({ state: Notification.permission })
        : originalQuery(parameters)
);

// 4. Fake plugins (headless has 0 plugins — detected by Facebook)
const plugins = [
    { name: 'Chrome PDF Plugin',        filename: 'internal-pdf-viewer',   description: 'Portable Document Format' },
    { name: 'Chrome PDF Viewer',        filename: 'mhjfbmdgcfjbbpaeojofohoefgiehjai', description: '' },
    { name: 'Native Client',            filename: 'internal-nacl-plugin',  description: '' },
];

Object.defineProperty(navigator, 'plugins', {
    get: () => {
        const arr = plugins.map((p, i) => {
            const plugin = { name: p.name, filename: p.filename, description: p.description, length: 1 };
            plugin[0] = { type: 'application/x-google-chrome-pdf', suffixes: 'pdf', description: p.description, enabledPlugin: plugin };
            return plugin;
        });
        arr.item = (i) => arr[i];
        arr.namedItem = (name) => arr.find(p => p.name === name);
        arr.refresh = () => {};
        arr.length = arr.length;
        return arr;
    },
    configurable: true
});

// 5. Fake mimeTypes
Object.defineProperty(navigator, 'mimeTypes', {
    get: () => {
        const arr = [
            { type: 'application/pdf', suffixes: 'pdf', description: '', enabledPlugin: {} },
            { type: 'application/x-google-chrome-pdf', suffixes: 'pdf', description: '', enabledPlugin: {} }
        ];
        arr.item = (i) => arr[i];
        arr.namedItem = (t) => arr.find(m => m.type === t);
        arr.length = arr.length;
        return arr;
    }
});

// 6. Realistic language settings
Object.defineProperty(navigator, 'languages', {
    get: () => ['en-US', 'en'],
    configurable: true
});

// 7. Fix iframe contentWindow.navigator.webdriver
const iframeProto = HTMLIFrameElement.prototype;
const getContentWindow = Object.getOwnPropertyDescriptor(iframeProto, 'contentWindow');
if (getContentWindow) {
    Object.defineProperty(iframeProto, 'contentWindow', {
        get: function() {
            const win = getContentWindow.get.call(this);
            if (!win) return win;
            try {
                Object.defineProperty(win.navigator, 'webdriver', { get: () => undefined });
            } catch (e) {}
            return win;
        }
    });
}

// 8. Pass headless detection tests
Object.defineProperty(navigator, 'hardwareConcurrency', { get: () => 8 });
Object.defineProperty(navigator, 'deviceMemory',        { get: () => 8 });
Object.defineProperty(navigator, 'maxTouchPoints',      { get: () => 0 });
Object.defineProperty(screen, 'colorDepth',             { get: () => 24 });
Object.defineProperty(screen, 'pixelDepth',             { get: () => 24 });
JS;

    public function __construct(private readonly Client $cdp) {}

    public function apply(): void
    {
        // Override user agent to remove "HeadlessChrome"
        $this->cdp->send(Protocol::setUserAgentOverride(self::USER_AGENT));

        // Inject stealth script on every new document (before page JS runs)
        $this->cdp->send([
            'method' => 'Page.addScriptToEvaluateOnNewDocument',
            'params' => ['source' => self::STEALTH_SCRIPT],
        ]);

        // Override viewport to look like a real browser
        $this->cdp->send(Protocol::setViewport(1366, 768, 1.0));

        // Enable extra headers that real browsers send
        $this->cdp->send(Protocol::setExtraHTTPHeaders([
            'Accept-Language' => 'en-US,en;q=0.9',
            'sec-ch-ua'       => '"Chromium";v="138", "Google Chrome";v="138"',
            'sec-ch-ua-mobile' => '?0',
            'sec-ch-ua-platform' => '"Linux"',
        ]));
    }

    public static function getUserAgent(): string
    {
        return self::USER_AGENT;
    }
}
