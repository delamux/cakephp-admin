<?php
declare(strict_types=1);

namespace App\Mailer;

/**
 * Inlines the CSS of the HTML part before the body is encoded and wrapped,
 * so `style` attributes and URLs are never split by line wrapping.
 *
 * @mixin \Cake\Mailer\Mailer
 */
trait InlineCssTrait
{
    /**
     * @param string $content Content.
     * @return $this
     */
    public function render(string $content = '')
    {
        $message = $this->getMessage();
        $body = $this->getRenderer()->render($content, $message->getBodyTypes());

        if (isset($body['html'])) {
            $body['html'] = (new CssInliner())->inline($body['html']);
        }
        $message->setBody($body);

        return $this;
    }
}
