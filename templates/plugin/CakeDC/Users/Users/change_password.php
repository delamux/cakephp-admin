<?php
/**
 * Change password (CakeDC/Users override). Used logged-in (dashboard layout)
 * and from the reset-password link (auth layout).
 *
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\User $user
 * @var bool $validatePassword
 */
use Cake\Core\Configure;

$this->assign('title', __('Change password'));
$loggedIn = $this->request->getAttribute('identity') !== null;

ob_start();
echo $this->Form->create($user, ['class' => 'form grid gap-5']);
echo $this->Flash->render('auth');
if ($validatePassword) {
    echo $this->Form->control('current_password', ['type' => 'password', 'required' => true, 'label' => __('Current password'), 'autocomplete' => 'current-password']);
}
echo $this->Form->control('password', ['type' => 'password', 'required' => true, 'id' => 'new-password', 'label' => __('New password'), 'autocomplete' => 'new-password']);
if (Configure::read('Users.passwordMeter.enabled')) {
    echo $this->User->addPasswordMeter();
}
echo $this->Form->control('password_confirm', ['type' => 'password', 'required' => true, 'label' => __('Confirm password'), 'autocomplete' => 'new-password']);
echo '<div class="flex gap-2">' . $this->Form->button(__('Update password'), ['id' => 'btn-submit', 'class' => $loggedIn ? '' : 'w-full']) . '</div>';
echo $this->Form->end();
$body = (string)ob_get_clean();

$card = $this->Card->render($body, [
    'title' => __('Change password'),
    'description' => __('Use at least 8 characters. A long passphrase is best.'),
    'headerOptions' => ['class' => $loggedIn ? '' : 'text-center'],
]);

echo $loggedIn ? '<div class="px-4 lg:px-6"><div class="max-w-xl">' . $card . '</div></div>' : $card;
