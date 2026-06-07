<?php

declare(strict_types=1);

namespace Xbrowser\Utils;

class ConfigManager
{
    private array $config;

    private array $defaults = [
        'chromium_path'         => '',
        'remote_debugging_port' => 9222,
        'timeout'               => 30000,
        'startup_timeout'       => 60000,   // timeout tunggu Chrome siap (ms)
        'headless'              => true,
        'stealth'               => true,
        'user_data_dir'         => '',
        'window_width'          => 1280,
        'window_height'         => 800,
        'verbose'               => false,
        'log_file'              => '',
        'plugin_dir'            => '',
        'disable_gpu'           => true,
        'no_sandbox'            => false,
        'disable_dev_shm'       => false,   // paksa --disable-dev-shm-usage (otomatis di Termux)
    ];

    /**
     * Ordered list of Chromium binary candidates, from most to least specific.
     * Covers: Linux, macOS, Windows WSL, Snap, Flatpak, NixOS, Termux (Android),
     * Replit, Docker, and any $PATH-resolvable name.
     */
    private const CHROMIUM_CANDIDATES = [
        // ── Termux (Android) ──────────────────────────────────────────────────
        '/data/data/com.termux/files/usr/bin/chromium-browser',
        '/data/data/com.termux/files/usr/bin/chromium',
        '/data/data/com.termux/files/usr/bin/google-chrome',
        '/data/data/com.termux/files/usr/bin/google-chrome-stable',

        // ── Standard Linux ────────────────────────────────────────────────────
        '/usr/bin/chromium',
        '/usr/bin/chromium-browser',
        '/usr/bin/google-chrome',
        '/usr/bin/google-chrome-stable',
        '/usr/bin/google-chrome-beta',
        '/usr/bin/brave-browser',

        // ── Local / opt installs ──────────────────────────────────────────────
        '/usr/local/bin/chromium',
        '/usr/local/bin/chromium-browser',
        '/usr/local/bin/google-chrome',
        '/opt/chromium/chromium',
        '/opt/chromium-browser/chromium-browser',
        '/opt/google/chrome/google-chrome',

        // ── Snap (Ubuntu) ─────────────────────────────────────────────────────
        '/snap/bin/chromium',
        '/snap/bin/chromium-browser',

        // ── Flatpak ───────────────────────────────────────────────────────────
        '/var/lib/flatpak/exports/bin/org.chromium.Chromium',
        '/var/lib/flatpak/exports/bin/com.google.Chrome',

        // ── NixOS / Nix user store ────────────────────────────────────────────
        '/nix/var/nix/profiles/default/bin/chromium',
        '/run/current-system/sw/bin/chromium',

        // ── macOS ─────────────────────────────────────────────────────────────
        '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
        '/Applications/Chromium.app/Contents/MacOS/Chromium',
        '/Applications/Brave Browser.app/Contents/MacOS/Brave Browser',

        // ── Windows WSL paths ─────────────────────────────────────────────────
        '/mnt/c/Program Files/Google/Chrome/Application/chrome.exe',
        '/mnt/c/Program Files (x86)/Google/Chrome/Application/chrome.exe',

        // ── Docker / CI common installs ───────────────────────────────────────
        '/headless-shell/headless-shell',
        '/usr/bin/google-chrome-unstable',
    ];

    public function __construct(string $configFile = '')
    {
        $this->config = $this->defaults;

        $this->config = array_merge($this->config, $this->loadFromEnv());

        if ($configFile && file_exists($configFile)) {
            $file = json_decode((string) file_get_contents($configFile), true) ?? [];
            $this->config = array_merge($this->config, $file);
        }

        $userConfig = $this->loadUserConfig();
        if ($userConfig) {
            $this->config = array_merge($this->config, $userConfig);
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->config[$key] = $value;
    }

    public function all(): array
    {
        return $this->config;
    }

    public function getChromiumPath(): string
    {
        if (!empty($this->config['chromium_path'])) {
            return $this->config['chromium_path'];
        }
        return $this->detectChromium();
    }

    // ─── Private ──────────────────────────────────────────────────────────────

    private function detectChromium(): string
    {
        // 1. Walk the static candidate list — cheapest check, no subprocess
        foreach (self::CHROMIUM_CANDIDATES as $path) {
            if (file_exists($path) && is_executable($path)) {
                return $path;
            }
        }

        // 2. Fall back to $PATH resolution via `which` (handles NixOS hashes,
        //    custom prefixes, and anything not in the static list above)
        $names = [
            'chromium',
            'chromium-browser',
            'google-chrome',
            'google-chrome-stable',
            'brave-browser',
        ];

        foreach ($names as $name) {
            $resolved = trim((string) shell_exec("which {$name} 2>/dev/null"));
            if ($resolved !== '' && is_executable($resolved)) {
                return $resolved;
            }
        }

        // 3. Termux-specific: pkg may install to non-standard $PREFIX
        $termuxPrefix = getenv('PREFIX') ?: '';
        if ($termuxPrefix !== '') {
            foreach (['chromium-browser', 'chromium', 'google-chrome'] as $name) {
                $path = $termuxPrefix . '/bin/' . $name;
                if (file_exists($path) && is_executable($path)) {
                    return $path;
                }
            }
        }

        throw new \RuntimeException(
            "Chromium not found.\n" .
            "  Linux:   sudo apt install chromium-browser\n" .
            "  macOS:   brew install --cask chromium\n" .
            "  Termux:  pkg install chromium\n" .
            "  Or set:  XBROWSER_CHROMIUM=/path/to/chromium\n" .
            "           ~/.xbrowser/config.json → { \"chromium_path\": \"...\" }"
        );
    }

    private function loadFromEnv(): array
    {
        $map = [
            'XBROWSER_CHROMIUM'          => 'chromium_path',
            'XBROWSER_PORT'              => 'remote_debugging_port',
            'XBROWSER_TIMEOUT'           => 'timeout',
            'XBROWSER_STARTUP_TIMEOUT'   => 'startup_timeout',
            'XBROWSER_VERBOSE'           => 'verbose',
            'XBROWSER_LOG_FILE'          => 'log_file',
            'XBROWSER_PLUGIN_DIR'        => 'plugin_dir',
            'XBROWSER_STEALTH'           => 'stealth',
            'XBROWSER_HEADLESS'          => 'headless',
            'XBROWSER_NO_SANDBOX'        => 'no_sandbox',
            'XBROWSER_DISABLE_DEV_SHM'   => 'disable_dev_shm',
        ];

        $result = [];
        foreach ($map as $env => $key) {
            $val = getenv($env);
            if ($val === false) {
                continue;
            }
            // Coerce numeric and boolean strings
            if (is_numeric($val)) {
                $result[$key] = (int) $val;
            } elseif (in_array(strtolower($val), ['true', '1', 'yes'], true)) {
                $result[$key] = true;
            } elseif (in_array(strtolower($val), ['false', '0', 'no'], true)) {
                $result[$key] = false;
            } else {
                $result[$key] = $val;
            }
        }

        return $result;
    }

    private function loadUserConfig(): array
    {
        // Honour $HOME; on Termux $HOME is usually /data/data/com.termux/files/home
        $home = $_SERVER['HOME'] ?? getenv('HOME') ?? '';
        if ($home === '') {
            return [];
        }
        $path = $home . '/.xbrowser/config.json';
        if (!file_exists($path)) {
            return [];
        }
        return json_decode((string) file_get_contents($path), true) ?? [];
    }
}
