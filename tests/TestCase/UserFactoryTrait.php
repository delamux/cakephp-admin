<?php
declare(strict_types=1);

namespace App\Test\TestCase;

use App\Model\Table\UsersTable;
use Cake\Datasource\EntityInterface;
use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry;
use Cake\Utility\Hash;
use CakeDC\Users\Model\Entity\User;

/**
 * Minimal user factory for tests (bypasses validation, explicit `created`).
 */
trait UserFactoryTrait
{
    private int $userSequence = 0;

    /**
     * @param array<string, mixed> $data Field overrides.
     */
    protected function createUser(array $data = []): User
    {
        $this->userSequence++;
        // Same wiring as Application::bootstrap() (the locator is reset between tests)
        UsersTable::register(TableRegistry::getTableLocator());
        $table = TableRegistry::getTableLocator()->get(UsersTable::ALIAS);
        /** @var \CakeDC\Users\Model\Entity\User $user */
        $user = $table->newEmptyEntity();
        $user->setAccess('*', true);
        $user = $table->patchEntity($user, $data + [
            'username' => 'user' . $this->userSequence,
            'email' => 'user' . $this->userSequence . '@example.test',
            'role' => 'user',
            'is_superuser' => false,
            'active' => true,
            'created' => DateTime::now(),
        ], ['validate' => false, 'accessibleFields' => ['*' => true]]);
        // Pre-hashed "Secret1234!" (skips the entity setter, which needs the plugin config)
        $user->set('password', password_hash('Secret1234!', PASSWORD_DEFAULT), ['setter' => false]);

        return $table->saveOrFail($user, ['checkRules' => false]);
    }

    /**
     * Signed-in session as right after a password login: the session clock starts now and the
     * password counts as just confirmed (sensitive actions do not ask for it; App\Account\SessionGuard).
     *
     * @param \Cake\Datasource\EntityInterface|array<string, mixed> $user Identity.
     * @param array<string, mixed> $extra More session data.
     */
    protected function signIn(EntityInterface|array $user, array $extra = []): void
    {
        $this->session(Hash::merge([
            'Auth' => $user,
            'App' => ['Session' => ['signedInAt' => time(), 'confirmedAt' => time()]],
        ], $extra));
    }
}
