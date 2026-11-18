<?php
declare(strict_types=1);

namespace App\Test\TestCase;

use App\Model\Table\UsersTable;
use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry;
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
}
