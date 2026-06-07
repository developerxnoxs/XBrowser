<?php

declare(strict_types=1);

namespace Xbrowser\CDP;

class Protocol
{
    public static function navigate(string $url): array
    {
        return ['method' => 'Page.navigate', 'params' => ['url' => $url]];
    }

    public static function enable(string $domain): array
    {
        return ['method' => "{$domain}.enable", 'params' => []];
    }

    public static function getDocument(): array
    {
        return ['method' => 'DOM.getDocument', 'params' => ['depth' => -1, 'pierce' => true]];
    }

    public static function querySelector(int $nodeId, string $selector): array
    {
        return ['method' => 'DOM.querySelector', 'params' => ['nodeId' => $nodeId, 'selector' => $selector]];
    }

    public static function querySelectorAll(int $nodeId, string $selector): array
    {
        return ['method' => 'DOM.querySelectorAll', 'params' => ['nodeId' => $nodeId, 'selector' => $selector]];
    }

    public static function getOuterHTML(int $nodeId): array
    {
        return ['method' => 'DOM.getOuterHTML', 'params' => ['nodeId' => $nodeId]];
    }

    public static function getBoxModel(int $nodeId): array
    {
        return ['method' => 'DOM.getBoxModel', 'params' => ['nodeId' => $nodeId]];
    }

    public static function evaluate(string $expression, bool $returnByValue = true, bool $awaitPromise = true): array
    {
        return [
            'method' => 'Runtime.evaluate',
            'params' => [
                'expression'    => $expression,
                'returnByValue' => $returnByValue,
                'awaitPromise'  => $awaitPromise,
                'userGesture'   => true,
            ],
        ];
    }

    public static function callFunctionOn(string $objectId, string $functionDeclaration, array $args = []): array
    {
        return [
            'method' => 'Runtime.callFunctionOn',
            'params' => [
                'objectId'            => $objectId,
                'functionDeclaration' => $functionDeclaration,
                'arguments'           => array_map(fn($a) => ['value' => $a], $args),
                'returnByValue'       => true,
            ],
        ];
    }

    public static function dispatchMouseEvent(string $type, float $x, float $y, int $button = 0, int $clickCount = 0): array
    {
        return [
            'method' => 'Input.dispatchMouseEvent',
            'params' => [
                'type'       => $type,
                'x'          => $x,
                'y'          => $y,
                'button'     => match($button) { 0 => 'left', 1 => 'middle', 2 => 'right', default => 'none' },
                'clickCount' => $clickCount,
            ],
        ];
    }

    public static function dispatchKeyEvent(string $type, string $key, string $text = '', array $modifiers = []): array
    {
        $modifierFlags = 0;
        if (!empty($modifiers['alt']))   $modifierFlags |= 1;
        if (!empty($modifiers['ctrl']))  $modifierFlags |= 2;
        if (!empty($modifiers['meta']))  $modifierFlags |= 4;
        if (!empty($modifiers['shift'])) $modifierFlags |= 8;

        return [
            'method' => 'Input.dispatchKeyEvent',
            'params' => [
                'type'      => $type,
                'key'       => $key,
                'text'      => $text,
                'modifiers' => $modifierFlags,
            ],
        ];
    }

    public static function insertText(string $text): array
    {
        return ['method' => 'Input.insertText', 'params' => ['text' => $text]];
    }

    public static function captureScreenshot(string $format = 'png', int $quality = 90): array
    {
        return [
            'method' => 'Page.captureScreenshot',
            'params' => ['format' => $format, 'quality' => $quality],
        ];
    }

    public static function printToPDF(): array
    {
        return ['method' => 'Page.printToPDF', 'params' => ['printBackground' => true]];
    }

    /**
     * Network.enable — gunakan stdClass() bukan [] agar Chrome serialisasi
     * sebagai JSON object {} bukan array []. Chrome >=112 strict validasi params.
     */
    public static function networkEnable(
        int $maxTotalBufferSize    = 0,
        int $maxResourceBufferSize = 0,
        int $maxPostDataSize       = 0
    ): array {
        $params = new \stdClass();
        if ($maxTotalBufferSize    > 0) $params->maxTotalBufferSize    = $maxTotalBufferSize;
        if ($maxResourceBufferSize > 0) $params->maxResourceBufferSize = $maxResourceBufferSize;
        if ($maxPostDataSize       > 0) $params->maxPostDataSize       = $maxPostDataSize;

        return ['method' => 'Network.enable', 'params' => $params];
    }

    public static function getAllCookies(): array
    {
        return ['method' => 'Network.getAllCookies', 'params' => []];
    }

    public static function setCookies(array $cookies): array
    {
        return ['method' => 'Network.setCookies', 'params' => ['cookies' => $cookies]];
    }

    public static function clearBrowserCookies(): array
    {
        return ['method' => 'Network.clearBrowserCookies', 'params' => []];
    }

    public static function setExtraHTTPHeaders(array $headers): array
    {
        return ['method' => 'Network.setExtraHTTPHeaders', 'params' => ['headers' => $headers]];
    }

    public static function setUserAgentOverride(string $userAgent): array
    {
        return ['method' => 'Network.setUserAgentOverride', 'params' => ['userAgent' => $userAgent]];
    }

    public static function getResponseBody(string $requestId): array
    {
        return ['method' => 'Network.getResponseBody', 'params' => ['requestId' => $requestId]];
    }

    public static function waitForLoad(): array
    {
        return ['method' => 'Page.setLifecycleEventsEnabled', 'params' => ['enabled' => true]];
    }

    public static function reload(bool $ignoreCache = false): array
    {
        return ['method' => 'Page.reload', 'params' => ['ignoreCache' => $ignoreCache]];
    }

    public static function goBack(): array
    {
        return ['method' => 'Page.goBack', 'params' => []];
    }

    public static function goForward(): array
    {
        return ['method' => 'Page.goForward', 'params' => []];
    }

    public static function setViewport(int $width, int $height, float $deviceScaleFactor = 1.0): array
    {
        return [
            'method' => 'Emulation.setDeviceMetricsOverride',
            'params' => [
                'width'             => $width,
                'height'            => $height,
                'deviceScaleFactor' => $deviceScaleFactor,
                'mobile'            => false,
            ],
        ];
    }

    public static function focusNode(int $nodeId): array
    {
        return ['method' => 'DOM.focus', 'params' => ['nodeId' => $nodeId]];
    }

    public static function setAttributeValue(int $nodeId, string $name, string $value): array
    {
        return [
            'method' => 'DOM.setAttributeValue',
            'params' => ['nodeId' => $nodeId, 'name' => $name, 'value' => $value],
        ];
    }

    // ── Fetch domain (Request Interception) ──────────────────────────────────

    /**
     * Aktifkan Fetch domain untuk intercept request sebelum dikirim.
     *
     * @param array $patterns  Array of ['urlPattern' => '...', 'resourceType' => '...']
     *                         Kosong = intercept semua.
     */
    public static function fetchEnable(array $patterns = []): array
    {
        if (empty($patterns)) {
            $patterns = [['urlPattern' => '*']];
        }
        return ['method' => 'Fetch.enable', 'params' => ['patterns' => $patterns]];
    }

    public static function fetchDisable(): array
    {
        return ['method' => 'Fetch.disable', 'params' => new \stdClass()];
    }

    public static function fetchContinueRequest(string $requestId, array $overrides = []): array
    {
        return ['method' => 'Fetch.continueRequest', 'params' => array_merge(
            ['requestId' => $requestId],
            $overrides
        )];
    }

    public static function fetchFailRequest(string $requestId, string $errorReason = 'BlockedByClient'): array
    {
        return [
            'method' => 'Fetch.failRequest',
            'params' => ['requestId' => $requestId, 'errorReason' => $errorReason],
        ];
    }

    public static function fetchFulfillRequest(
        string $requestId,
        int    $responseCode,
        array  $responseHeaders = [],
        string $body            = '',
    ): array {
        return [
            'method' => 'Fetch.fulfillRequest',
            'params' => [
                'requestId'       => $requestId,
                'responseCode'    => $responseCode,
                'responseHeaders' => $responseHeaders,
                'body'            => base64_encode($body),
            ],
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────

    public static function createTarget(string $url = 'about:blank'): array
    {
        return ['method' => 'Target.createTarget', 'params' => ['url' => $url]];
    }

    public static function getTargets(): array
    {
        return ['method' => 'Target.getTargets', 'params' => []];
    }

    public static function attachToTarget(string $targetId): array
    {
        return ['method' => 'Target.attachToTarget', 'params' => ['targetId' => $targetId, 'flatten' => true]];
    }

    public static function closeTarget(string $targetId): array
    {
        return ['method' => 'Target.closeTarget', 'params' => ['targetId' => $targetId]];
    }
}
