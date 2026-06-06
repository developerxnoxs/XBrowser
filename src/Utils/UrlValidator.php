<?php

declare(strict_types=1);

namespace Xbrowser\Utils;

use Xbrowser\Exceptions\InvalidUrlException;

class UrlValidator
{
    public static function validate(string $url): string
    {
        if (str_starts_with($url, 'about:') || str_starts_with($url, 'chrome:')) {
            return $url;
        }

        if (!str_contains($url, '://')) {
            $url = 'https://' . $url;
        }

        $parsed = parse_url($url);
        if ($parsed === false) {
            throw new InvalidUrlException($url);
        }

        $scheme = $parsed['scheme'] ?? '';
        if (!in_array($scheme, ['http', 'https', 'file', 'data'], true)) {
            throw new InvalidUrlException($url);
        }

        if (in_array($scheme, ['http', 'https'], true) && empty($parsed['host'])) {
            throw new InvalidUrlException($url);
        }

        return $url;
    }

    public static function isValid(string $url): bool
    {
        try {
            self::validate($url);
            return true;
        } catch (InvalidUrlException) {
            return false;
        }
    }
}
