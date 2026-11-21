<?php
declare(strict_types=1);

namespace App\Model\Table;

use App\Model\Entity\User;
use App\Service\UserRoleManager;
use App\Storage\Uploads;
use ArrayObject;
use Cake\Collection\CollectionInterface;
use Cake\Core\Configure;
use Cake\Datasource\EntityInterface;
use Cake\Event\EventInterface;
use Cake\I18n\DateTime;
use Cake\ORM\Locator\LocatorInterface;
use Cake\ORM\Query\SelectQuery;
use CakeDC\Users\Model\Table\UsersTable as BaseUsersTable;
use CakeDC\Users\Webauthn\Base64Utility;

/**
 * CakeDC/Users table with the app rule "role `superadmin` ⇔ `is_superuser`" applied on every save
 * (CRUD, role changes, registration, `bin/cake users add_superuser`), and the two-factor state:
 * TOTP (`secret` + `secret_verified`) and WebAuthn keys (`additional_data.webauthn_credentials`,
 * in CakeDC's format, plus the app's `webauthn_passkey` flag). 2FA columns are only written with
 * `updateQuery()`, never from a form.
 *
 * CakeDC resolves its table by the alias `CakeDC/Users.Users` (its CRUD uses `fetchTable()`), so
 * the alias is pointed to this class with register().
 *
 * @property \Cake\ORM\Association\HasMany<\CakeDC\Users\Model\Table\SocialAccountsTable> $SocialAccounts
 * @method \App\Model\Entity\User newEmptyEntity()
 * @method \App\Model\Entity\User newEntity(array<string, mixed> $data, array<string, mixed> $options = [])
 * @method array<\App\Model\Entity\User> newEntities(array<array<string, mixed>> $data, array<string, mixed> $options = [])
 * @method \App\Model\Entity\User get(mixed $primaryKey, array<string, mixed>|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \App\Model\Entity\User findOrCreate(\Cake\ORM\Query\SelectQuery<\App\Model\Entity\User>|callable|array<string, mixed> $search, ?callable $callback = null, array<string, mixed> $options = [])
 * @method \App\Model\Entity\User patchEntity(\Cake\Datasource\EntityInterface $entity, array<string, mixed> $data, array<string, mixed> $options = [])
 * @method array<\App\Model\Entity\User> patchEntities(iterable<\App\Model\Entity\User> $entities, array<array<string, mixed>> $data, array<string, mixed> $options = [])
 * @method \App\Model\Entity\User|false save(\Cake\Datasource\EntityInterface $entity, array<string, mixed> $options = [])
 * @method \App\Model\Entity\User saveOrFail(\Cake\Datasource\EntityInterface $entity, array<string, mixed> $options = [])
 * @method \Cake\Datasource\ResultSetInterface<int, \App\Model\Entity\User>|false saveMany(iterable<\App\Model\Entity\User> $entities, array<string, mixed> $options = [])
 * @method \Cake\Datasource\ResultSetInterface<int, \App\Model\Entity\User> saveManyOrFail(iterable<\App\Model\Entity\User> $entities, array<string, mixed> $options = [])
 * @method \Cake\Datasource\ResultSetInterface<int, \App\Model\Entity\User>|false deleteMany(iterable<\App\Model\Entity\User> $entities, array<string, mixed> $options = [])
 * @method \Cake\Datasource\ResultSetInterface<int, \App\Model\Entity\User> deleteManyOrFail(iterable<\App\Model\Entity\User> $entities, array<string, mixed> $options = [])
 * @mixin \Josegonzalez\Upload\Model\Behavior\UploadBehavior
 */
class UsersTable extends BaseUsersTable
{
    public const ALIAS = 'CakeDC/Users.Users';

    /**
     * Points the CakeDC alias to this class. Call it before the table is first used.
     *
     * @param \Cake\ORM\Locator\LocatorInterface $locator Table locator.
     * @return void
     */
    public static function register(LocatorInterface $locator): void
    {
        if (!$locator->exists(self::ALIAS)) {
            $locator->setConfig(self::ALIAS, ['className' => self::class]);
        }
    }

    /**
     * @param array<string, mixed> $config Table config.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);
        // CakeDC's entity (password hashing, hidden fields) with the 2FA columns not mass-assignable
        $this->setEntityClass(User::class);
        // Photo: users/<id>/photo/<uuid>.webp in the private uploads (App\Storage\Uploads). Written
        // only by App\UseCase\Users\ChangeUserPhoto (`photo` is not mass-assignable).
        Uploads::attachImage($this, 'photo');
    }

    /**
     * Deleting a user deletes all its files (`users/<id>/`).
     *
     * @param \Cake\Event\EventInterface<\Cake\ORM\Table> $event Event.
     * @param \App\Model\Entity\User $entity User.
     * @param \ArrayObject<string, mixed> $options Options.
     * @return void
     */
    public function afterDelete(EventInterface $event, EntityInterface $entity, ArrayObject $options): void
    {
        Uploads::deleteRecord($this->getTable(), (string)$entity->get('id'));
    }

    /**
     * Keeps the superadmin role and the superuser flag consistent.
     *
     * @param \Cake\Event\EventInterface<\Cake\ORM\Table> $event Event.
     * @param \App\Model\Entity\User $entity User.
     * @param \ArrayObject<string, mixed> $options Options.
     * @return void
     */
    public function beforeSave(EventInterface $event, EntityInterface $entity, ArrayObject $options): void
    {
        UserRoleManager::syncSuperadmin($entity);
    }

    /**
     * @param string $id User id.
     * @return list<string> Active methods: `totp` (verified secret) and/or `webauthn` (registered key).
     */
    public function twoFactorMethods(string $id): array
    {
        $row = $this->find()
            ->select(['secret', 'secret_verified', 'additional_data'])
            ->where([$this->aliasField('id') => $id])
            ->disableHydration()
            ->first();
        if (!is_array($row)) {
            return [];
        }
        $methods = [];
        if (!empty($row['secret_verified']) && !empty($row['secret'])) {
            $methods[] = 'totp';
        }
        if (is_array($row['additional_data'] ?? null) && !empty($row['additional_data']['webauthn_credentials'])) {
            $methods[] = 'webauthn';
        }

        return $methods;
    }

    /**
     * Enables TOTP with a secret already checked against a code typed by the user.
     *
     * @param string $id User id.
     * @param string $secret TOTP secret.
     * @param int|null $usedStep Window of the confirmation code (it cannot be used again to log in).
     * @return void
     */
    public function enableTotp(string $id, string $secret, ?int $usedStep = null): void
    {
        $this->updateQuery()
            ->set(
                ['secret' => $secret, 'secret_verified' => true, 'totp_last_step' => $usedStep],
                ['secret_verified' => 'boolean', 'totp_last_step' => 'biginteger'],
            )
            ->where(['id' => $id])
            ->execute();
    }

    /**
     * Claims a TOTP time window (30 s step) for the user: true only the first time and only if it
     * is later than the last accepted one, so a code cannot be reused (even concurrently: it is a
     * single conditional UPDATE).
     *
     * @param string $id User id.
     * @param int $step Window of the code that was just verified.
     * @return bool
     */
    public function claimTotpStep(string $id, int $step): bool
    {
        return $this->updateQuery()
            ->set(['totp_last_step' => $step], ['totp_last_step' => 'biginteger'])
            ->where([
                'id' => $id,
                'OR' => ['totp_last_step IS' => null, 'totp_last_step <' => $step],
            ])
            ->execute()
            ->rowCount() === 1;
    }

    /**
     * Removes the authenticator app (the key, if any, stays).
     *
     * @param string $id User id.
     * @return void
     */
    public function disableTotp(string $id): void
    {
        $this->updateQuery()
            ->set(
                ['secret' => null, 'secret_verified' => false, 'totp_last_step' => null],
                ['secret_verified' => 'boolean', 'totp_last_step' => 'biginteger'],
            )
            ->where(['id' => $id])
            ->execute();
    }

    /**
     * Removes the WebAuthn keys and the passkey flag, keeping the rest of `additional_data`.
     *
     * @param string $id User id.
     * @return void
     */
    public function disableWebauthn(string $id): void
    {
        $data = $this->additionalData($id);
        unset($data['webauthn_credentials'], $data['webauthn_passkey']);
        $this->writeAdditionalData($id, $data === [] ? null : $data);
    }

    /**
     * Removes both methods (reset by a superadmin or from the console).
     *
     * @param string $id User id.
     * @return void
     */
    public function disableTwoFactor(string $id): void
    {
        $this->disableTotp($id);
        $this->disableWebauthn($id);
    }

    /**
     * Flags the user's key as a passkey (discoverable, user verification required: registered with
     * App\Webauthn\RegisterAdapter). Only flagged keys can sign in without a password.
     *
     * @param string $id User id.
     * @return void
     */
    public function markPasskey(string $id): void
    {
        $data = $this->additionalData($id);
        $data['webauthn_passkey'] = true;
        $this->writeAdditionalData($id, $data);
    }

    /**
     * A key registered without the passkey flag: valid as second factor, not for passwordless login.
     *
     * @param string $id User id.
     * @return bool
     */
    public function isLegacyWebauthnKey(string $id): bool
    {
        $data = $this->additionalData($id);

        return !empty($data['webauthn_credentials']) && empty($data['webauthn_passkey']);
    }

    /**
     * Owner of a passkey for passwordless login: the user whose id is the `userHandle` returned by
     * the browser, if they have a flagged passkey with that credential id (stored base64url without
     * padding, like CakeDC's repository).
     *
     * @param \Cake\ORM\Query\SelectQuery<\App\Model\Entity\User> $query Query.
     * @param string $userHandle User id returned by the authenticator.
     * @param string $credentialId Binary credential id.
     * @return \Cake\ORM\Query\SelectQuery<\App\Model\Entity\User>
     */
    public function findPasskeyOwner(SelectQuery $query, string $userHandle, string $credentialId): SelectQuery
    {
        $key = Base64Utility::basicEncode($credentialId);

        return $query
            ->where([$this->aliasField('id') => $userHandle])
            ->formatResults(fn(CollectionInterface $users) => $users->filter(
                function (EntityInterface $user) use ($key): bool {
                    $data = $user->get('additional_data');

                    return is_array($data)
                        && !empty($data['webauthn_passkey'])
                        && isset($data['webauthn_credentials'][$key]);
                },
            ));
    }

    /**
     * Records the last login for sign-ins that skip CakeDC's login form (passkeys).
     *
     * @param string $id User id.
     * @return void
     */
    public function recordLogin(string $id): void
    {
        if (!Configure::read('Users.Login.updateLastLogin', true)) {
            return;
        }
        $field = (string)Configure::read('Users.Login.lastLoginField', 'last_login');
        $this->updateQuery()
            ->set([$field => DateTime::now()], [$field => 'datetime'])
            ->where(['id' => $id])
            ->execute();
    }

    /**
     * @param string $id User id.
     * @return array<string, mixed>
     */
    private function additionalData(string $id): array
    {
        $row = $this->find()
            ->select(['additional_data'])
            ->where([$this->aliasField('id') => $id])
            ->disableHydration()
            ->first();

        return is_array($row) && is_array($row['additional_data'] ?? null) ? $row['additional_data'] : [];
    }

    /**
     * @param string $id User id.
     * @param array<string, mixed>|null $data Additional data.
     * @return void
     */
    private function writeAdditionalData(string $id, ?array $data): void
    {
        $this->updateQuery()
            ->set(['additional_data' => $data], ['additional_data' => 'json'])
            ->where(['id' => $id])
            ->execute();
    }
}
