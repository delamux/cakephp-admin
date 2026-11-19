# htmx

htmx 2 (self-hosted in production, pinned CDN in dev) with a custom integration inspired by `zunnu/cake-htmx`
(`App\Controller\Component\HtmxComponent`, loaded in `AppController`).

- **htmx requests** (`HX-Request`) are rendered with the `htmx` layout: just the template, and flash messages arrive
  as out-of-band **toasts** (`hx-swap-oob` into the toaster), even when `hx-select` picks another element. Navigation
  with `hx-boost` and no `hx-target` receives the full page. Always `Vary: HX-Request`.
- **CSRF** via header (`app.js`), **FormProtection** untouched (forms are still `FormHelper` forms) and **CSP**:
  `htmx-config` with `allowEval: false`, no injected styles and the page's nonce.
- If an htmx request requires login, `AppRedirectHandler` responds with `HX-Redirect` (full navigation).
- In the controller: `$this->Htmx->isRequest()`, `->trigger('event')`, `->redirect($url)`, `->refresh()`.

Common pattern: the form does `hx-post`, the controller redirects as usual and htmx follows the redirect and swaps
only the fragment:

```php
<?= $this->Form->create($entity, [
    'hx-post' => $this->Url->build(['action' => 'edit', $entity->id]),
    'hx-target' => '#my-block',
    'hx-select' => '#my-block',
    'hx-swap' => 'outerHTML',
]) ?>
```

In use: the users list (sorting, pagination and deletion with `hx-boost`), the permissions matrix (save with a toast)
and the two-step verification card (each step swaps only the card). JS that must re-bind after a swap listens for
`htmx:load` (like `webauthn.js`).
