<?php
declare(strict_types=1);

namespace App\Controller;

use App\Controller\Component\OneTimePasswordAuthenticatorComponent;
use App\Navigation\SidebarMenu;
use App\Service\UserRoleManager;
use App\UseCase\Actor;
use App\View\Helper\UserPhotoHelper;
use Cake\Controller\Controller;
use Cake\Event\EventInterface;
use Cake\Http\Exception\UnauthorizedException;
use Cake\Validation\Validation;

/**
 * Application Controller.
 *
 * CakeDC/Users extends this controller too (its UsersController extends App\Controller\AppController),
 * so the theme, layouts and sidebar are applied to the plugin pages as well.
 * The AuthenticationComponent is loaded by CakeDC/Users (`Auth.AuthenticationComponent.load`).
 *
 * @property \Cake\Controller\Component\FormProtectionComponent $FormProtection
 * @property \App\Controller\Component\HtmxComponent $Htmx
 */
class AppController extends Controller
{
    /**
     * @inheritDoc
     */
    public function initialize(): void
    {
        parent::initialize();

        $this->loadComponent('Flash');
        // Form tampering protection (book.cakephp.org/5.x/controllers/components/form-protection):
        // forms built with FormHelper carry a token of their fields. CakeDC's controllers load it too.
        $this->loadComponent('FormProtection');
        // htmx: partial rendering for htmx requests (App\Controller\Component\HtmxComponent)
        $this->loadComponent('Htmx');
    }

    /**
     * App TOTP component for CakeDC's controllers and the self-delete guard of the CakeDC/Users CRUD.
     * The identity is already fresh here (App\Middleware\RefreshIdentityMiddleware runs before
     * authorization).
     *
     * @param \Cake\Event\EventInterface<\Cake\Controller\Controller> $event Event.
     * @return void
     */
    public function beforeFilter(EventInterface $event): void
    {
        parent::beforeFilter($event);

        // CakeDC loads its TOTP component in its controllers (SetupComponent): swap in the app's,
        // which rejects reused codes (App\Controller\Component\OneTimePasswordAuthenticatorComponent)
        $components = $this->components();
        if (
            $components->has('OneTimePasswordAuthenticator')
            && !$components->get('OneTimePasswordAuthenticator') instanceof OneTimePasswordAuthenticatorComponent
        ) {
            $components->unload('OneTimePasswordAuthenticator');
            $this->loadComponent(
                'OneTimePasswordAuthenticator',
                ['className' => OneTimePasswordAuthenticatorComponent::class],
            );
        }

        // Fixed account protections of the CakeDC/Users CRUD, whatever the role permissions say
        // (App\Service\UserRoleManager::isProtected()): superadmin accounts only for superadmins,
        // admin accounts not for other admins; and nobody deletes their own account, superadmins
        // included (they bypass the RBAC rules, so these cannot be rules).
        $params = (array)$this->request->getAttribute('params');
        $action = $params['action'] ?? null;
        $id = (string)($params['pass'][0] ?? '');
        if (
            ($params['plugin'] ?? null) !== 'CakeDC/Users'
            || ($params['controller'] ?? null) !== 'Users'
            || !in_array($action, ['edit', 'delete'], true)
            || $id === ''
        ) {
            return;
        }
        $actor = $this->request->getAttribute('identity')?->getOriginalData();
        if ($action === 'delete' && $actor !== null && $id === (string)$actor['id']) {
            $this->Flash->error(__('You cannot delete your own account.'));
        } else {
            $target = Validation::uuid($id)
                ? $this->fetchTable('CakeDC/Users.Users')->find()->where(['id' => $id])->first()
                : null;
            // Missing account: the CRUD action answers 404
            if ($target === null || !UserRoleManager::fromConfig()->isProtected($actor, $target)) {
                return;
            }
            $this->Flash->error(__('You don\'t have permission to manage this account.'));
        }
        $event->setResult($this->redirect(['plugin' => 'CakeDC/Users', 'controller' => 'Users', 'action' => 'index']));
    }

    /**
     * Theme, layout and sidebar data for every page.
     *
     * @param \Cake\Event\EventInterface<\Cake\Controller\Controller> $event Event.
     * @return void
     */
    public function beforeRender(EventInterface $event): void
    {
        parent::beforeRender($event);

        $builder = $this->viewBuilder();
        $builder->setTheme('BasecoatTheme');

        $identity = $this->request->getAttribute('identity');
        if ($identity === null) {
            // Login, register, password reset, login link...
            $builder->setLayout('auth');

            return;
        }

        $user = $identity->getOriginalData();
        $menu = new SidebarMenu();
        $this->set([
            'sidebarMenu' => $menu->items($user),
            'sidebarUser' => $menu->user($user) + ['avatar' => (new UserPhotoHelper($this->createView()))->url($user)],
            'currentUser' => $user,
            'isAdmin' => $menu->isAdmin($user),
            'canViewUsers' => UserRoleManager::canViewUsers($user),
            // Account-management rules for the templates (what to show / hide)
            'roleManager' => UserRoleManager::fromConfig(),
        ]);
    }

    /**
     * Who performs the use cases of this request (the logged-in user).
     *
     * @return \App\UseCase\Actor
     * @throws \Cake\Http\Exception\UnauthorizedException Without identity (RBAC normally prevents it).
     */
    protected function actor(): Actor
    {
        $identity = $this->request->getAttribute('identity');
        if ($identity === null) {
            throw new UnauthorizedException();
        }

        return Actor::fromUser($identity->getOriginalData());
    }
}
