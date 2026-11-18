<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Role $role
 * @var bool $slugLocked
 */
$this->assign('title', (string)$this->get('title'));
$this->start('breadcrumb');
echo $this->Breadcrumb->render([__('Roles') => ['action' => 'index'], h($role->name)]);
$this->end();
?>
<div class="px-4 lg:px-6">
    <div class="max-w-xl"><?= $this->element('Roles/form', ['role' => $role, 'slugLocked' => $slugLocked]) ?></div>
</div>
