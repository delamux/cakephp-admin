<?php
declare(strict_types=1);

namespace App\UseCase\Users;

use App\Account\ActivityLog;
use App\Model\Table\UsersTable;
use App\Service\UserRoleManager;
use App\Storage\ImageProcessor;
use App\Storage\Uploads;
use ArrayAccess;
use Cake\Core\Configure;
use Cake\Datasource\EntityInterface;
use Cake\ORM\TableRegistry;
use Psr\Http\Message\UploadedFileInterface;

/**
 * Change or remove a user's photo. Own photo always; another user's only with admin access and
 * never a superadmin's unless the actor is one (UserRoleManager::canManage()). The image is
 * validated by its content and re-encoded (App\Storage\ImageProcessor); the previous file is
 * deleted; the change goes to the account activity (with who did it).
 */
class ChangeUserPhoto
{
    private UsersTable $users;

    private ActivityLog $activity;

    private UserRoleManager $roles;

    /**
     * @param \App\Model\Table\UsersTable|null $users Users table.
     * @param \App\Account\ActivityLog|null $activity Activity log.
     * @param \App\Service\UserRoleManager|null $roles Role rules.
     */
    public function __construct(
        ?UsersTable $users = null,
        ?ActivityLog $activity = null,
        ?UserRoleManager $roles = null,
    ) {
        /** @var \App\Model\Table\UsersTable $table */
        $table = $users ?? TableRegistry::getTableLocator()->get((string)Configure::read('Users.table', 'Users'));
        $this->users = $table;
        $this->activity = $activity ?? new ActivityLog();
        $this->roles = $roles ?? UserRoleManager::fromConfig();
    }

    /**
     * @param \ArrayAccess<string, mixed>|array<string, mixed> $actor Signed-in user.
     * @param string $userId Target user.
     * @return bool
     */
    public function allowed(ArrayAccess|array $actor, string $userId): bool
    {
        if ((string)($actor['id'] ?? '') === $userId) {
            return true;
        }
        $target = $this->users->find()->select(['id', 'role', 'is_superuser'])->where(['id' => $userId])->first();

        return $target instanceof EntityInterface && $this->roles->canManage($actor, $target);
    }

    /**
     * @param \ArrayAccess<string, mixed>|array<string, mixed> $actor Signed-in user.
     * @param string $userId Target user.
     * @param \Psr\Http\Message\UploadedFileInterface|null $file Upload (`photo` field).
     * @return string|null Error for the user, null when the photo was changed.
     */
    public function change(ArrayAccess|array $actor, string $userId, ?UploadedFileInterface $file): ?string
    {
        if (!$this->allowed($actor, $userId)) {
            return __('You cannot change the photo of this user.');
        }
        if ($file === null || $file->getError() === UPLOAD_ERR_NO_FILE) {
            return __('Choose a photo first.');
        }
        $error = ImageProcessor::validate($file);
        if ($error !== null) {
            return $error;
        }

        $user = $this->users->get($userId);
        [$oldDir, $oldFile] = [$user->get('photo_dir'), $user->get('photo')];
        $this->users->patchEntity($user, ['photo' => $file], [
            'accessibleFields' => ['photo' => true],
            'validate' => false,
        ]);
        $saved = $this->users->save($user, ['checkRules' => false]);
        ImageProcessor::cleanup();
        if (!$saved) {
            return __('The photo could not be saved. Try again.');
        }
        if ($oldFile && $oldFile !== $user->get('photo')) {
            Uploads::delete($oldDir, $oldFile);
        }
        $this->activity->record($userId, ActivityLog::PHOTO_CHANGED, actorId: (string)($actor['id'] ?? ''));

        return null;
    }

    /**
     * @param \ArrayAccess<string, mixed>|array<string, mixed> $actor Signed-in user.
     * @param string $userId Target user.
     * @return string|null Error for the user, null when removed (or there was none).
     */
    public function remove(ArrayAccess|array $actor, string $userId): ?string
    {
        if (!$this->allowed($actor, $userId)) {
            return __('You cannot change the photo of this user.');
        }
        $user = $this->users->get($userId);
        if (!$user->get('photo')) {
            return null;
        }
        Uploads::delete($user->get('photo_dir'), $user->get('photo'));
        // Direct update: the Upload behavior only acts on uploaded files
        $this->users->updateQuery()->set(['photo' => null, 'photo_dir' => null])->where(['id' => $userId])->execute();
        $this->activity->record($userId, ActivityLog::PHOTO_REMOVED, actorId: (string)($actor['id'] ?? ''));

        return null;
    }

    /**
     * @param string $userId User.
     * @return array{path: string, file: string}|null The stored photo, if any.
     */
    public function stored(string $userId): ?array
    {
        $user = $this->users->find()->select(['photo', 'photo_dir'])->where(['id' => $userId])->first();
        if (!$user instanceof EntityInterface) {
            return null;
        }
        $path = Uploads::path($user->get('photo_dir'), $user->get('photo'));

        return $path === null ? null : ['path' => $path, 'file' => (string)$user->get('photo')];
    }
}
