<?php
declare(strict_types=1);

namespace App\Test\TestCase\Account;

use App\Account\UserAgent;
use Cake\TestSuite\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Browser summary of the activity log ("Chrome on macOS"): good enough to recognise a device,
 * not a full parser. Browsers that copy others' tokens (Edge, Opera, iOS) are checked first.
 */
class UserAgentTest extends TestCase
{
    /**
     * @return array<string, array{string|null, string}>
     */
    public static function agents(): array
    {
        return [
            'chrome mac' => ['Mozilla/5.0 (Macintosh; Intel Mac OS X 14_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0 Safari/537.36', 'Chrome on macOS'],
            'edge windows' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0 Safari/537.36 Edg/131.0', 'Edge on Windows'],
            'safari iphone' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 18_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.1 Mobile/15E148 Safari/604.1', 'Safari on iOS'],
            'firefox linux' => ['Mozilla/5.0 (X11; Linux x86_64; rv:133.0) Gecko/20100101 Firefox/133.0', 'Firefox on Linux'],
            'chrome android' => ['Mozilla/5.0 (Linux; Android 15) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0 Mobile Safari/537.36', 'Chrome on Android'],
            'cli tool' => ['curl/8.7.1', 'Unknown browser'],
            'none' => [null, 'Unknown browser'],
        ];
    }

    #[DataProvider('agents')]
    public function testDescribe(?string $agent, string $expected): void
    {
        $this->assertSame($expected, UserAgent::describe($agent));
    }
}
