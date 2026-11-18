<?php
declare(strict_types=1);

namespace App\Test\TestCase\Mailer;

use App\Mailer\CssInliner;
use Cake\TestSuite\TestCase;

/**
 * Email compatibility rules: CSS must be inlined, responsive/dark-mode rules kept in <head>.
 */
class CssInlinerTest extends TestCase
{
    private const HTML = <<<'HTML'
<!DOCTYPE html>
<html><head><style>
.btn { background-color: #18181b; color: #ffffff; }
p { margin: 0 0 16px; }
@media (prefers-color-scheme: dark) { .card { background-color: #09090b !important; } }
@media only screen and (max-width: 620px) { .container { width: 100% !important; } }
</style></head>
<body><table class="container"><tr><td class="card"><p>Hi</p><a class="btn" href="https://example.test">Go</a></td></tr></table></body></html>
HTML;

    public function testCssRulesAreMovedToStyleAttributes(): void
    {
        $html = (new CssInliner())->inline(self::HTML);

        $this->assertMatchesRegularExpression('/<a class="btn"[^>]*style="[^"]*background-color: #18181b/', $html);
        $this->assertMatchesRegularExpression('/<p style="margin: 0 0 16px;?">/', $html);
    }

    public function testMediaQueriesArePreservedForDarkModeAndMobile(): void
    {
        $html = (new CssInliner())->inline(self::HTML);

        $this->assertStringContainsString('prefers-color-scheme: dark', $html);
        $this->assertStringContainsString('max-width: 620px', $html);
    }

    public function testDoctypeIsKept(): void
    {
        $this->assertStringStartsWith('<!DOCTYPE html>', ltrim((new CssInliner())->inline(self::HTML)));
    }

    public function testEmptyBodyIsReturnedUntouched(): void
    {
        $this->assertSame('', (new CssInliner())->inline(''));
    }
}
