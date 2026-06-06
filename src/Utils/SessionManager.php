<?php

declare(strict_types=1);

namespace Xbrowser\Utils;

class SessionManager
{
    private string $sessionDir;

    public function __construct(string $sessionDir = '')
    {
        $this->sessionDir = $sessionDir ?: $this->defaultDir();
        $this->ensureDir($this->sessionDir);
    }

    public function save(string $name, array $data): void
    {
        $path = $this->sessionPath($name);
        $payload = [
            'version'   => '1.0',
            'savedAt'   => date('c'),
            'cookies'   => $data['cookies'] ?? [],
            'localStorage' => $data['localStorage'] ?? [],
            'sessionStorage' => $data['sessionStorage'] ?? [],
            'url'       => $data['url'] ?? '',
        ];
        file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    public function load(string $name): array
    {
        $path = $this->sessionPath($name);
        if (!file_exists($path)) {
            throw new \RuntimeException("Session '{$name}' not found.");
        }
        $raw = file_get_contents($path);
        return json_decode($raw, true) ?? [];
    }

    public function delete(string $name): void
    {
        $path = $this->sessionPath($name);
        if (file_exists($path)) {
            unlink($path);
        }
    }

    public function list(): array
    {
        $files = glob($this->sessionDir . '/*.json') ?: [];
        return array_map(fn(string $f) => pathinfo($f, PATHINFO_FILENAME), $files);
    }

    public function exists(string $name): bool
    {
        return file_exists($this->sessionPath($name));
    }

    private function sessionPath(string $name): string
    {
        $safe = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $name);
        return $this->sessionDir . '/' . $safe . '.json';
    }

    private function defaultDir(): string
    {
        $home = $_SERVER['HOME'] ?? sys_get_temp_dir();
        return $home . '/.xbrowser/sessions';
    }

    private function ensureDir(string $dir): void
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0700, true);
        }
    }
}
