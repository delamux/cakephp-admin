<?php
/**
 * "Photo" card: upload a new one / remove it (App\Controller\UserPhotoController).
 *
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\User $user
 * @var bool $own Own profile (no id in the URLs)
 */
$own ??= true;
$url = fn(string $action): array => ['plugin' => false, 'prefix' => false, 'controller' => 'UserPhoto', 'action' => $action]
    + ($own ? [] : [(string)$user->id]);
$name = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: (string)$user->username;

$body = '<div class="flex flex-col gap-4 sm:flex-row sm:items-center">'
    . $this->Avatar->render($name, array_filter(['src' => $this->UserPhoto->url($user), 'size' => 'lg', 'class' => 'size-16 text-lg']))
    . $this->Form->create(null, ['type' => 'file', 'url' => $url('update'), 'class' => 'grid flex-1 gap-2'])
    . $this->Form->control('photo', [
        'type' => 'file',
        'label' => __('New photo'),
        'accept' => 'image/jpeg,image/png,image/webp',
        'required' => true,
    ])
    . '<p class="text-sm text-muted-foreground">' . __('JPEG, PNG or WebP, up to 2 MB. It is cropped to a square.') . '</p>'
    . '<div>' . $this->Form->button(__('Upload photo'), ['type' => 'submit']) . '</div>'
    . $this->Form->end()
    . '</div>';

echo $this->Card->render($body, [
    'title' => __('Photo'),
    'description' => $own ? __('Shown next to your name across the app.') : __('Shown next to this user\'s name across the app.'),
    'footer' => $user->photo
        ? $this->Form->postButton(__('Remove photo'), $url('delete'), ['variant' => 'outline', 'size' => 'sm', 'data-confirm' => __('Remove the photo?')])
        : null,
]);
