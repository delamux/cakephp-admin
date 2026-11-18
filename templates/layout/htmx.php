<?php
/**
 * Layout of htmx requests (App\Controller\Component\HtmxComponent): only the template, plus the
 * flash messages as toasts swapped out-of-band into the page's toaster, so they show up even when
 * `hx-select` takes another element (basecoat-theme.js initializes them on `htmx:load`).
 *
 * @var \App\View\AppView $this
 */
$categories = ['flash/success' => 'success', 'flash/error' => 'error', 'flash/warning' => 'warning'];
$toasts = '';
foreach ((array)$this->getRequest()->getSession()->consume('Flash.flash') as $flash) {
    $element = (string)($flash['element'] ?? '');
    $toasts .= $this->Toast->render((string)($flash['message'] ?? ''), [
        'category' => $categories[$element] ?? 'info',
        'escape' => ($flash['params']['escape'] ?? true) !== false,
    ]);
}
if ($toasts !== '') {
    echo '<div hx-swap-oob="beforeend:#' . h((string)$this->Toast->getConfig('id')) . '">' . $toasts . '</div>';
}
echo $this->fetch('content');
