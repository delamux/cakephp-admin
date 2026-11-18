<?php
/**
 * Login (CakeDC/Users override, BasecoatTheme `auth` layout).
 *
 * @var \App\View\AppView $this
 */
use Cake\Core\Configure;

$this->assign('title', __('Sign in'));
$registration = (bool)Configure::read('Users.Registration.active');
$resetPassword = (bool)Configure::read('Users.Email.required');
$loginLink = $resetPassword && Configure::read('OneTimeLogin.enabled');

ob_start();
echo $this->Form->create(null, ['class' => 'form grid gap-6']);
echo $this->Flash->render('auth');
// `webauthn` in autocomplete: the browser offers saved passkeys in the field (conditional UI)
echo $this->Form->control('username', ['label' => __('Username or email'), 'required' => true, 'autofocus' => true, 'autocomplete' => 'username webauthn']);
echo $this->Form->control('password', ['label' => __('Password'), 'required' => true, 'autocomplete' => 'current-password']);
if ($resetPassword) {
    echo '<div class="-mt-4 text-right">' . $this->Html->link(__('Forgot your password?'), ['action' => 'requestResetPassword'], ['class' => 'text-sm text-muted-foreground underline-offset-4 hover:underline']) . '</div>';
}
if (Configure::read('Users.reCaptcha.login')) {
    echo $this->User->addReCaptcha();
}
if (Configure::read('Users.RememberMe.active')) {
    echo $this->Form->control((string)Configure::read('Users.Key.Data.rememberMe'), [
        'type' => 'checkbox',
        'label' => __('Remember me'),
        'checked' => (bool)Configure::read('Users.RememberMe.checked'),
    ]);
}
echo '<div class="grid gap-3">';
echo $this->Form->button(__('Sign in'), ['class' => 'w-full']);
if (Configure::read('Webauthn2fa.enabled')) {
    $query = $this->getRequest()->getQueryParams();
    echo '<div class="grid gap-2" data-passkey-login hidden'
        . ' data-csrf="' . h((string)$this->getRequest()->getAttribute('csrfToken')) . '"'
        . ' data-options-url="' . h($this->Url->build(['plugin' => false, 'prefix' => false, 'controller' => 'PasskeyLogin', 'action' => 'options'])) . '"'
        . ' data-action-url="' . h($this->Url->build(['plugin' => false, 'prefix' => false, 'controller' => 'PasskeyLogin', 'action' => 'login', '?' => array_intersect_key($query, ['redirect' => true])])) . '"'
        . ' data-error="' . h(__('Could not sign in with the passkey. Try again or sign in with your password.')) . '">'
        . $this->Button->render(__('Sign in with a passkey'), ['type' => 'button', 'variant' => 'outline', 'class' => 'w-full', 'icon' => 'key-round', 'data-passkey-start' => true])
        . '<p class="hidden text-sm text-destructive" data-webauthn-message role="alert"></p>'
        . '</div>';
}
if ($loginLink) {
    echo $this->Button->link(__('Email me a sign-in link'), ['action' => 'requestLoginLink'], ['variant' => 'outline', 'class' => 'w-full', 'icon' => 'mail']);
}
echo implode(' ', $this->User->socialLoginList());
echo '</div>';
echo $this->Form->end();
$body = (string)ob_get_clean();

echo $this->Card->render($body, [
    'title' => __('Welcome back'),
    'description' => __('Sign in to your account to continue'),
    'headerOptions' => ['class' => 'text-center'],
    'footer' => $registration
        ? '<p class="w-full text-center text-sm text-muted-foreground">' . __('Don\'t have an account?') . ' ' . $this->Html->link(__('Sign up'), ['action' => 'register'], ['class' => 'font-medium text-foreground underline underline-offset-4']) . '</p>'
        : null,
]);
echo $this->Html->script('webauthn', ['defer' => true]);
