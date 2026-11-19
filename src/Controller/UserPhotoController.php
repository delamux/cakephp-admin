<?php
declare(strict_types=1);

namespace App\Controller;

use App\UseCase\Users\ChangeUserPhoto;
use Cake\Core\Configure;
use Cake\Http\Exception\NotFoundException;
use Cake\Http\Response;
use Psr\Http\Message\UploadedFileInterface;

/**
 * User photo: upload / remove (own from the profile, others' from the user edit page) and serve it
 * (private storage: only to signed-in users). Rules in App\UseCase\Users\ChangeUserPhoto.
 * Any signed-in role reaches these actions (system rule in config/permissions.php); who may change
 * whose photo is decided by the use case.
 *
 * @property \Authentication\Controller\Component\AuthenticationComponent $Authentication
 */
class UserPhotoController extends AppController
{
    /**
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();
        // Same config CakeDC/Users loads in its controllers, for setIdentity() after an own change
        $config = (array)Configure::read('Auth.AuthenticationComponent');
        unset($config['config'], $config['load']);
        $this->loadComponent('Authentication.Authentication', $config);
    }

    /**
     * @param \App\UseCase\Users\ChangeUserPhoto $photos Use case.
     * @param string|null $id User (default: own).
     * @return \Cake\Http\Response|null
     */
    public function update(ChangeUserPhoto $photos, ?string $id = null): ?Response
    {
        $this->request->allowMethod(['post', 'put']);
        $id ??= $this->ownId();
        $file = $this->request->getData('photo');
        $error = $photos->change($this->identity(), $id, $file instanceof UploadedFileInterface ? $file : null);

        return $this->back($id, $error, __('Photo updated.'));
    }

    /**
     * @param \App\UseCase\Users\ChangeUserPhoto $photos Use case.
     * @param string|null $id User (default: own).
     * @return \Cake\Http\Response|null
     */
    public function delete(ChangeUserPhoto $photos, ?string $id = null): ?Response
    {
        $this->request->allowMethod(['post', 'delete']);
        $id ??= $this->ownId();

        return $this->back($id, $photos->remove($this->identity(), $id), __('Photo removed.'));
    }

    /**
     * The image (WebP). URLs carry `?v=<file name>` (App\View\Helper\UserPhotoHelper), so it can be
     * cached for long: a new photo is a new URL.
     *
     * @param \App\UseCase\Users\ChangeUserPhoto $photos Use case.
     * @param string $id User.
     * @return \Cake\Http\Response
     */
    public function show(ChangeUserPhoto $photos, string $id): Response
    {
        $stored = $photos->stored($id);
        if ($stored === null) {
            throw new NotFoundException();
        }

        return $this->response
            ->withFile($stored['path'])
            ->withType('webp')
            ->withHeader('Cache-Control', 'private, max-age=31536000, immutable');
    }

    /**
     * @return \ArrayAccess<string, mixed>|array<string, mixed>
     */
    private function identity(): mixed
    {
        return $this->request->getAttribute('identity')?->getOriginalData() ?? [];
    }

    /**
     * @return string
     */
    private function ownId(): string
    {
        return (string)($this->identity()['id'] ?? '');
    }

    /**
     * @param string $id User.
     * @param string|null $error Error, null on success.
     * @param string $success Success message.
     * @return \Cake\Http\Response|null
     */
    private function back(string $id, ?string $error, string $success): ?Response
    {
        $error === null ? $this->Flash->success($success) : $this->Flash->error($error);
        if ($id !== $this->ownId()) {
            return $this->redirect(['plugin' => 'CakeDC/Users', 'controller' => 'Users', 'action' => 'edit', $id]);
        }
        if ($error === null) {
            // The session keeps the whole user: refresh it so the new photo shows everywhere
            $users = $this->fetchTable((string)Configure::read('Users.table', 'Users'));
            $this->Authentication->setIdentity($users->get($id));
        }

        return $this->redirect(['plugin' => 'CakeDC/Users', 'controller' => 'Users', 'action' => 'profile']);
    }
}
