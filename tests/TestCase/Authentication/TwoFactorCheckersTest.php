<?php
declare(strict_types=1);

namespace App\Test\TestCase\Authentication;

use App\Authentication\OptionalOneTimePasswordChecker;
use App\Authentication\OptionalWebauthn2faChecker;
use App\Model\Table\UsersTable;
use App\Test\TestCase\UserFactoryTrait;
use Cake\Core\Configure;
use Cake\TestSuite\TestCase;

/**
 * 2FA is opt-in: the login asks for a second factor only to users who enabled one. With both
 * methods the TOTP code is asked (the key is only the second step when it is the only method).
 */
class TwoFactorCheckersTest extends TestCase
{
    use UserFactoryTrait;

    protected array $fixtures = ['app.Users'];

    private UsersTable $users;

    protected function setUp(): void
    {
        parent::setUp();
        Configure::write('OneTimePasswordAuthenticator.login', true);
        Configure::write('Webauthn2fa.enabled', true);
        /** @var \App\Model\Table\UsersTable $users */
        $users = $this->fetchTable('Users');
        $this->users = $users;
    }

    /**
     * @return array{totp: bool, key: bool}
     */
    private function asked(?string $id): array
    {
        $user = $id === null ? null : ['id' => $id];

        return [
            'totp' => (new OptionalOneTimePasswordChecker())->isRequired($user),
            'key' => (new OptionalWebauthn2faChecker())->isRequired($user),
        ];
    }

    private function addKey(string $id): void
    {
        $this->users->updateQuery()->set(['additional_data' => ['webauthn_credentials' => ['k' => []]]], ['additional_data' => 'json'])
            ->where(['id' => $id])->execute();
    }

    public function testNothingIsAskedWithoutTwoFactorOrUser(): void
    {
        $this->assertSame(['totp' => false, 'key' => false], $this->asked((string)$this->createUser()->id));
        $this->assertSame(['totp' => false, 'key' => false], $this->asked(null));
    }

    public function testEachMethodAsksItsOwnStepAndTotpWinsWithBoth(): void
    {
        $totp = (string)$this->createUser()->id;
        $key = (string)$this->createUser()->id;
        $both = (string)$this->createUser()->id;
        $this->users->enableTotp($totp, 'ABCDEFGHIJKLMNOP');
        $this->addKey($key);
        $this->users->enableTotp($both, 'ABCDEFGHIJKLMNOP');
        $this->addKey($both);

        $this->assertSame(['totp' => true, 'key' => false], $this->asked($totp));
        $this->assertSame(['totp' => false, 'key' => true], $this->asked($key));
        $this->assertSame(['totp' => true, 'key' => false], $this->asked($both));
    }

    public function testGlobalSwitchOffAsksNothing(): void
    {
        $id = (string)$this->createUser()->id;
        $this->users->enableTotp($id, 'ABCDEFGHIJKLMNOP');
        Configure::write('OneTimePasswordAuthenticator.login', false);
        Configure::write('Webauthn2fa.enabled', false);

        $this->assertSame(['totp' => false, 'key' => false], $this->asked($id));
    }
}
