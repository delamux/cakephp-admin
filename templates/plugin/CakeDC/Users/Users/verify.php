<?php
/**
 * Login second step: TOTP code (CakeDC/Users override, BasecoatTheme `auth` layout). Enrolment is
 * done from the profile (App\Controller\TwoFactorController): the opt-in checkers only send users
 * with a verified secret here, so no QR is shown during login.
 *
 * @var \App\View\AppView $this
 */
$this->assign('title', __('Two-step verification'));

ob_start();
echo $this->Form->create(null, ['class' => 'form grid gap-6']);
echo $this->Flash->render('auth');
echo $this->Flash->render();
echo $this->Form->control('code', [
    'label' => __('Code'),
    'required' => true,
    'autofocus' => true,
    'inputmode' => 'numeric',
    'autocomplete' => 'one-time-code',
    'pattern' => '[0-9 ]*',
    'maxlength' => 8,
]);
echo $this->Form->button(__('Verify'), ['class' => 'w-full']);
echo $this->Form->end();
$body = (string)ob_get_clean();

echo $this->Card->render($body, [
    'title' => __('Two-step verification'),
    'description' => __('Type the 6-digit code from your authenticator app.'),
    'headerOptions' => ['class' => 'text-center'],
    'footer' => '<p class="w-full text-center text-sm text-muted-foreground">'
        . __('Lost your device? Ask an administrator to reset your two-step verification.') . ' '
        . $this->Html->link(__('Back to sign in'), ['action' => 'login'], ['class' => 'font-medium text-foreground underline underline-offset-4'])
        . '</p>',
]);
