<?php
declare(strict_types=1);

namespace App\Account;

/**
 * Short browser summary for the activity log ("Chrome on macOS"). Good enough to recognise a
 * device, not a full User-Agent parser; browsers that copy others' tokens are matched first.
 */
final class UserAgent
{
    /**
     * Token => name, in match order (Edge and Opera also say "Chrome"; Chrome also says "Safari").
     */
    private const BROWSERS = [
        'Edg/' => 'Edge',
        'OPR/' => 'Opera',
        'Firefox/' => 'Firefox',
        'FxiOS/' => 'Firefox',
        'CriOS/' => 'Chrome',
        'Chrome/' => 'Chrome',
        'Safari/' => 'Safari',
    ];

    /**
     * Token => name, in match order (iOS says "like Mac OS X"; Android says "Linux").
     */
    private const SYSTEMS = [
        'iPhone' => 'iOS',
        'iPad' => 'iOS',
        'Android' => 'Android',
        'Windows' => 'Windows',
        'Mac OS X' => 'macOS',
        'CrOS' => 'ChromeOS',
        'Linux' => 'Linux',
    ];

    /**
     * @param string|null $userAgent User-Agent header.
     * @return string
     */
    public static function describe(?string $userAgent): string
    {
        $browser = self::match((string)$userAgent, self::BROWSERS);
        if ($browser === null) {
            return __('Unknown browser');
        }
        $system = self::match((string)$userAgent, self::SYSTEMS);

        return $system === null ? $browser : __('{0} on {1}', $browser, $system);
    }

    /**
     * @param string $userAgent User-Agent.
     * @param array<string, string> $tokens Token => name.
     * @return string|null
     */
    private static function match(string $userAgent, array $tokens): ?string
    {
        foreach ($tokens as $token => $name) {
            if (str_contains($userAgent, $token)) {
                return $name;
            }
        }

        return null;
    }
}
