<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Table\UsersTable;
use App\Test\TestCase\UserFactoryTrait;
use Cake\TestSuite\TestCase;
use CakeDC\Users\Webauthn\Base64Utility;

/**
 * Two-factor state of a user: TOTP (`secret` + `secret_verified`) and WebAuthn keys
 * (`additional_data.webauthn_credentials`, `webauthn_passkey` flag). Never mass-assigned.
 */
class UsersTwoFactorTest extends TestCase
{
    use UserFactoryTrait;

    protected array $fixtures = ['app.Users'];

    private UsersTable $users;

    protected function setUp(): void
    {
        parent::setUp();
        /** @var \App\Model\Table\UsersTable $users */
        $users = $this->fetchTable('Users');
        $this->users = $users;
    }

    private function withKey(string $id, bool $passkey, string $credentialId = 'cred-1'): void
    {
        $data = ['webauthn_credentials' => [Base64Utility::basicEncode($credentialId) => ['id' => 'x']], 'other' => 1];
        if ($passkey) {
            $data['webauthn_passkey'] = true;
        }
        $this->users->updateQuery()->set(['additional_data' => $data], ['additional_data' => 'json'])
            ->where(['id' => $id])->execute();
    }

    public function testMethodsReflectTotpAndKeysIndependently(): void
    {
        $id = (string)$this->createUser()->id;
        $this->assertSame([], $this->users->twoFactorMethods($id));

        $this->users->updateQuery()->set(['secret' => 'ABCDEFGHIJKLMNOP'])->where(['id' => $id])->execute();
        $this->assertSame([], $this->users->twoFactorMethods($id), 'An unconfirmed secret is not active');

        $this->users->enableTotp($id, 'ABCDEFGHIJKLMNOP');
        $this->withKey($id, true);
        $this->assertSame(['totp', 'webauthn'], $this->users->twoFactorMethods($id));

        $this->users->disableTotp($id);
        $this->assertSame(['webauthn'], $this->users->twoFactorMethods($id), 'Disabling one keeps the other');

        $this->users->disableWebauthn($id);
        $this->assertSame([], $this->users->twoFactorMethods($id));
        $row = $this->users->find()->select(['additional_data'])->where(['id' => $id])->disableHydration()->firstOrFail();
        $this->assertSame(['other' => 1], $row['additional_data'], 'Other additional data is kept');
    }

    public function testResetRemovesBothMethods(): void
    {
        $id = (string)$this->createUser()->id;
        $this->users->enableTotp($id, 'ABCDEFGHIJKLMNOP');
        $this->withKey($id, true);

        $this->users->disableTwoFactor($id);

        $this->assertSame([], $this->users->twoFactorMethods($id));
    }

    public function testOnlyFlaggedKeysAreLegacyFreePasskeys(): void
    {
        $legacy = (string)$this->createUser()->id;
        $passkey = (string)$this->createUser()->id;
        $none = (string)$this->createUser()->id;
        $this->withKey($legacy, false, 'legacy-cred');
        $this->withKey($passkey, false, 'pk-cred');
        $this->users->markPasskey($passkey);

        $this->assertTrue($this->users->isLegacyWebauthnKey($legacy));
        $this->assertFalse($this->users->isLegacyWebauthnKey($passkey));
        $this->assertFalse($this->users->isLegacyWebauthnKey($none), 'No key is not a legacy key');

        $owner = fn(string $handle, string $cred) => $this->users->find('passkeyOwner', userHandle: $handle, credentialId: $cred)->first();
        $this->assertSame($passkey, $owner($passkey, 'pk-cred')?->get('id'));
        $this->assertNull($owner($passkey, 'other-cred'), 'Credential must belong to the user');
        $this->assertNull($owner($legacy, 'legacy-cred'), 'Legacy keys cannot log in without a password');
    }

    public function testTotpStepsAreClaimedOnceAndOnlyForward(): void
    {
        $id = (string)$this->createUser()->id;
        $this->users->enableTotp($id, 'ABCDEFGHIJKLMNOP');

        $this->assertTrue($this->users->claimTotpStep($id, 1000), 'First use of a window');
        $this->assertFalse($this->users->claimTotpStep($id, 1000), 'Same window again: reused code');
        $this->assertFalse($this->users->claimTotpStep($id, 999), 'Older window (clock drift tolerance)');
        $this->assertTrue($this->users->claimTotpStep($id, 1001), 'Next window');

        $this->users->disableTotp($id);
        $this->users->enableTotp($id, 'QRSTUVWXYZ234567');
        $this->assertTrue($this->users->claimTotpStep($id, 500), 'A new secret starts from scratch');
    }

    public function testTwoFactorFieldsAreNotMassAssignable(): void
    {
        // Loaded like a form would (the factory's entity is created with every field accessible)
        $user = $this->users->get($this->createUser()->id);

        $this->users->patchEntity($user, ['secret' => 'X', 'secret_verified' => true, 'totp_last_step' => 1, 'additional_data' => ['webauthn_passkey' => true]]);

        $this->assertFalse($user->isDirty('secret'));
        $this->assertFalse($user->isDirty('secret_verified'));
        $this->assertFalse($user->isDirty('additional_data'));
        $this->assertFalse($user->isDirty('totp_last_step'));
    }
}
