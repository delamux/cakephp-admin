<?php
declare(strict_types=1);

namespace App\Controller\Component;

use Cake\Controller\Component;
use Cake\Event\EventInterface;
use Cake\Routing\Router;

/**
 * htmx support (htmx.org; ideas from zunnu/cake-htmx, written here because that plugin is not
 * maintained):
 * - htmx requests (`HX-Request`) render with the `htmx` layout: the template plus the flash
 *   messages as toasts, so `hx-select` / `hx-target` can swap the part they need. Boosted
 *   navigation (`HX-Boosted`) keeps the full page, unless it swaps an element (`HX-Target`).
 * - `Vary: HX-Request` so caches never mix full pages and fragments.
 * - Helpers for response headers: trigger(), redirect(), refresh().
 *
 * @method \App\Controller\AppController getController()
 */
class HtmxComponent extends Component
{
    /**
     * @return bool The request was made by htmx.
     */
    public function isRequest(): bool
    {
        return $this->getController()->getRequest()->getHeaderLine('HX-Request') === 'true';
    }

    /**
     * @return bool Boosted link/form (hx-boost): a full page is expected.
     */
    public function isBoosted(): bool
    {
        return $this->getController()->getRequest()->getHeaderLine('HX-Boosted') === 'true';
    }

    /**
     * @return string|null Id of the element htmx will swap.
     */
    public function target(): ?string
    {
        return $this->getController()->getRequest()->getHeaderLine('HX-Target') ?: null;
    }

    /**
     * Triggers a client-side event after the swap (`HX-Trigger`).
     *
     * @param string $event Event name.
     * @param array<string, mixed>|null $detail Event detail.
     * @return void
     */
    public function trigger(string $event, ?array $detail = null): void
    {
        $controller = $this->getController();
        $controller->setResponse($controller->getResponse()
            ->withHeader('HX-Trigger', (string)json_encode([$event => $detail ?? true])));
    }

    /**
     * Full-page navigation from an htmx request (`HX-Redirect`).
     *
     * @param array<string, mixed>|string $url URL.
     * @return void
     */
    public function redirect(array|string $url): void
    {
        $controller = $this->getController();
        $controller->setResponse($controller->getResponse()->withHeader('HX-Redirect', Router::url($url)));
    }

    /**
     * Full reload of the current page (`HX-Refresh`).
     *
     * @return void
     */
    public function refresh(): void
    {
        $controller = $this->getController();
        $controller->setResponse($controller->getResponse()->withHeader('HX-Refresh', 'true'));
    }

    /**
     * @param \Cake\Event\EventInterface<\Cake\Controller\Controller> $event Event.
     * @return void
     */
    public function beforeRender(EventInterface $event): void
    {
        $controller = $this->getController();
        $controller->setResponse($controller->getResponse()->withAddedHeader('Vary', 'HX-Request'));
        if ($this->isRequest() && (!$this->isBoosted() || $this->target() !== null)) {
            $controller->viewBuilder()->setLayout('htmx');
        }
    }
}
