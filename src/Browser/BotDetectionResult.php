<?php

declare(strict_types=1);

namespace Xbrowser\Browser;

class BotDetectionResult
{
    public function __construct(
        public readonly bool   $webdriverHidden,
        public readonly bool   $pluginsPresent,
        public readonly bool   $chromeObjectPresent,
        public readonly bool   $languagesPresent,
        public readonly bool   $userAgentClean,
        public readonly string $userAgent,
        public readonly array  $rawChecks
    ) {}

    public function isPassed(): bool
    {
        return $this->webdriverHidden
            && $this->pluginsPresent
            && $this->chromeObjectPresent
            && $this->languagesPresent
            && $this->userAgentClean;
    }

    public function score(): int
    {
        return array_sum([
            $this->webdriverHidden      ? 1 : 0,
            $this->pluginsPresent       ? 1 : 0,
            $this->chromeObjectPresent  ? 1 : 0,
            $this->languagesPresent     ? 1 : 0,
            $this->userAgentClean       ? 1 : 0,
        ]);
    }

    public function describe(): string
    {
        $lines = [
            ($this->webdriverHidden     ? '✓' : '✗') . ' navigator.webdriver hidden',
            ($this->pluginsPresent      ? '✓' : '✗') . ' navigator.plugins present (' . ($this->rawChecks['pluginCount'] ?? 0) . ' plugins)',
            ($this->chromeObjectPresent ? '✓' : '✗') . ' window.chrome object present',
            ($this->languagesPresent    ? '✓' : '✗') . ' navigator.languages present',
            ($this->userAgentClean      ? '✓' : '✗') . ' User-Agent clean (no "HeadlessChrome")',
        ];
        return implode("\n", $lines);
    }
}
