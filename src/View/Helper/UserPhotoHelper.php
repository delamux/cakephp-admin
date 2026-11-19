<?php
declare(strict_types=1);

namespace App\View\Helper;

use ArrayAccess;
use Cake\Routing\Router;
use Cake\View\Helper;

/**
 * URL of a user's photo for `Avatar->render(..., ['src' => ...])`: the private image served by
 * App\Controller\UserPhotoController, with `?v=<file name>` so every new photo is a new URL
 * (long browser cache). Null when there is no photo (the avatar shows the initials).
 *
 * @extends \Cake\View\Helper<\Cake\View\View>
 */
class UserPhotoHelper extends Helper
{
    /**
     * @param \ArrayAccess<string, mixed>|array<string, mixed>|null $user User entity, identity or array.
     * @return string|null
     */
    public function url(ArrayAccess|array|null $user): ?string
    {
        $id = $user['id'] ?? null;
        $photo = $user['photo'] ?? null;
        if (!$id || !is_string($photo) || $photo === '') {
            return null;
        }

        return Router::url([
            'plugin' => false,
            'prefix' => false,
            'controller' => 'UserPhoto',
            'action' => 'show',
            (string)$id,
            '?' => ['v' => $photo],
        ]);
    }
}
