<?php
/**
 * Users report (PDF).
 *
 * @var \App\View\AppView $this
 * @var array<\App\Model\Entity\User>|\Cake\Collection\CollectionInterface<int, \App\Model\Entity\User> $users
 * @var array<string, mixed> $summary
 * @var \Cake\I18n\DateTime $generatedAt
 */
$this->assign('title', __('Users report'));
?>
<h1><?= __('Users report') ?></h1>
<p class="muted"><?= __('Generated on {0}', h($generatedAt->i18nFormat('d MMM yyyy, HH:mm'))) ?></p>

<table class="kpis"><tr>
    <td><div class="kpi-label"><?= __('Total users') ?></div><div class="kpi-value"><?= $this->Number->format($summary['total']) ?></div></td>
    <td><div class="kpi-label"><?= __('Active') ?></div><div class="kpi-value"><?= $this->Number->format($summary['active']) ?></div></td>
    <td><div class="kpi-label"><?= __('Pending') ?></div><div class="kpi-value"><?= $this->Number->format($summary['pending']) ?></div></td>
    <td><div class="kpi-label"><?= __('Administrators') ?></div><div class="kpi-value"><?= $this->Number->format($summary['admins']) ?></div></td>
</tr></table>

<table class="data">
    <thead>
    <tr>
        <th><?= __('Username') ?></th>
        <th><?= __('Name') ?></th>
        <th><?= __('Email') ?></th>
        <th><?= __('Role') ?></th>
        <th><?= __('Status') ?></th>
        <th><?= __('Last login') ?></th>
        <th><?= __('Created') ?></th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($users as $user) : ?>
        <tr>
            <td><strong><?= h($user->username) ?></strong></td>
            <td><?= h(trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''))) ?: '<span class="muted">—</span>' ?></td>
            <td><?= h($user->email) ?></td>
            <td><span class="badge<?= $user->is_superuser ? ' badge-dark' : '' ?>"><?= h(\Cake\Core\Configure::read('App.roles.' . $user->role) ?? $user->role) ?></span></td>
            <td><?= $user->active ? '<span class="badge badge-ok">' . __('Active') . '</span>' : '<span class="badge badge-warn">' . __('Pending') . '</span>' ?></td>
            <td><?= $user->last_login ? h($user->last_login->i18nFormat('d MMM yyyy HH:mm')) : '<span class="muted">' . __('Never') . '</span>' ?></td>
            <td><?= h($user->created?->i18nFormat('d MMM yyyy')) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
