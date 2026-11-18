<?php
/**
 * New user (CakeDC/Users override, admins only).
 *
 * @var \App\View\AppView $this
 * @var string $tableAlias
 */
$user = ${$tableAlias};
$this->assign('title', __('New user'));
$this->start('breadcrumb');
echo $this->Breadcrumb->render([__('Users') => ['action' => 'index'], __('New user')]);
$this->end();
?>
<div class="px-4 lg:px-6">
    <div class="max-w-2xl">
        <?= $this->element('Users/user_form', ['user' => $user, 'title' => __('Account details'), 'withPassword' => true]) ?>
    </div>
</div>
