<?php
/**
 * Login second step: security key / passkey (CakeDC/Users `/webauthn2fa` override). Options come
 * from CakeDC (`webauthn2faAuthenticateOptions`); the response is verified by the app
 * (App\Controller\WebauthnLoginController) with the app's algorithms. JS: webroot/js/webauthn.js.
 *
 * @var \App\View\AppView $this
 */
$this->assign('title', __('Two-step verification'));
$query = $this->getRequest()->getQueryParams();

$body = '<div class="grid gap-6" data-webauthn-authenticate'
    . ' data-csrf="' . h((string)$this->getRequest()->getAttribute('csrfToken')) . '"'
    . ' data-options-url="' . h($this->Url->build(['action' => 'webauthn2faAuthenticateOptions', '?' => $query])) . '"'
    . ' data-action-url="' . h($this->Url->build(['plugin' => false, 'prefix' => false, 'controller' => 'WebauthnLogin', 'action' => 'authenticate', '?' => $query])) . '"'
    . ' data-error="' . h(__('The key could not be verified. Try again.')) . '"'
    . ' data-unsupported="' . h(__('This browser does not support security keys or passkeys. Sign in from another browser or ask an administrator to reset your two-step verification.')) . '">'
    . ($this->Flash->render('auth') ?? '') . ($this->Flash->render() ?? '')
    . '<p class="hidden text-sm text-destructive" data-webauthn-message role="alert"></p>'
    . $this->Button->render(__('Use my key'), ['type' => 'button', 'class' => 'w-full', 'icon' => 'shield-check', 'data-webauthn-start' => true, 'autofocus' => true])
    . '</div>';

echo $this->Card->render($body, [
    'title' => __('Two-step verification'),
    'description' => __('Use your security key, or the fingerprint, face or PIN of your device, to finish signing in.'),
    'headerOptions' => ['class' => 'text-center'],
    'footer' => '<p class="w-full text-center text-sm text-muted-foreground">'
        . __('Lost your key? Ask an administrator to reset your two-step verification.') . ' '
        . $this->Html->link(__('Back to sign in'), ['action' => 'login'], ['class' => 'font-medium text-foreground underline underline-offset-4'])
        . '</p>',
]);
echo $this->Html->script('webauthn', ['defer' => true]);
