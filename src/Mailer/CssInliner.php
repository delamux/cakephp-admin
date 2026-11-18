<?php
declare(strict_types=1);

namespace App\Mailer;

use Pelago\Emogrifier\CssInliner as Emogrifier;
use Pelago\Emogrifier\HtmlProcessor\CssToAttributeConverter;

/**
 * Makes HTML emails compatible with as many clients as possible (Outlook, Gmail, Apple Mail...):
 * - every CSS rule of the <style> blocks is copied into `style` attributes,
 * - rules that cannot be inlined (@media: dark mode, mobile) stay in a <style> block in <head>,
 * - visual CSS is mirrored as legacy HTML attributes (bgcolor, align, width...) for Outlook.
 */
class CssInliner
{
    /**
     * Inline the CSS of an HTML email.
     *
     * @param string $html Full HTML document.
     * @return string
     */
    public function inline(string $html): string
    {
        if (trim($html) === '') {
            return $html;
        }

        $inliner = Emogrifier::fromHtml($html)->inlineCss();

        return CssToAttributeConverter::fromDomDocument($inliner->getDomDocument())
            ->convertCssToVisualAttributes()
            ->render();
    }
}
