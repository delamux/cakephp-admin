# htmx

htmx 2 (autoalojado en producción, CDN fijado en dev) con una integración propia inspirada en `zunnu/cake-htmx`
(`App\Controller\Component\HtmxComponent`, cargado en `AppController`).

- **Peticiones htmx** (`HX-Request`) se renderizan con el layout `htmx`: solo la plantilla, y los mensajes flash
  llegan como **toasts** fuera de banda (`hx-swap-oob` al toaster), aunque `hx-select` tome otro elemento. La
  navegación con `hx-boost` sin `hx-target` recibe la página completa. Siempre `Vary: HX-Request`.
- **CSRF** por cabecera (`app.js`), **FormProtection** intacto (los formularios siguen siendo de `FormHelper`) y
  **CSP**: `htmx-config` con `allowEval: false`, sin estilos inyectados y con el nonce de la página.
- Si una petición htmx necesita login, `AppRedirectHandler` responde con `HX-Redirect` (navegación completa).
- En el controlador: `$this->Htmx->isRequest()`, `->trigger('evento')`, `->redirect($url)`, `->refresh()`.

Patrón habitual: el formulario hace `hx-post`, el controlador redirige como siempre y htmx sigue la redirección y
cambia solo el fragmento:

```php
<?= $this->Form->create($entity, [
    'hx-post' => $this->Url->build(['action' => 'edit', $entity->id]),
    'hx-target' => '#mi-bloque',
    'hx-select' => '#mi-bloque',
    'hx-swap' => 'outerHTML',
]) ?>
```

En uso: listado de usuarios (orden, paginación y borrado con `hx-boost`), matriz de permisos (guardar con toast) y
tarjeta de verificación en dos pasos (cada paso cambia solo la tarjeta). El JS que deba reengancharse tras un swap
escucha `htmx:load` (como `webauthn.js`).
