<?php
declare(strict_types=1);

namespace App\Test\TestCase\Mailer;

use App\Mailer\UsersMailer;
use App\Test\TestCase\UserFactoryTrait;
use Cake\Core\Configure;
use Cake\TestSuite\EmailTrait;
use Cake\TestSuite\TestCase;
use Cake\TestSuite\TestEmailTransport;

/**
 * Account emails use the app layout, are multipart and have their CSS inlined.
 */
class UsersMailerTest extends TestCase
{
    use EmailTrait;
    use UserFactoryTrait;

    protected array $fixtures = ['app.Users'];

    protected function setUp(): void
    {
        parent::setUp();
        Configure::write('Users.controller', 'CakeDC/Users.Users');
        $this->loadPlugins(['CakeDC/Users']);
    }

    public function testResetPasswordEmailIsInlinedMultipartAndBranded(): void
    {
        $user = $this->createUser(['first_name' => 'Jane', 'token' => 'abc123']);

        (new UsersMailer())->send('resetPassword', [$user]);

        $this->assertMailCount(1);
        $this->assertMailSentTo($user->email);
        // HTML part: app layout + inlined CSS on the call-to-action button
        $this->assertMailContainsHtml(Configure::read('BasecoatTheme.brand.name'));
        $this->assertMailContainsHtml('abc123');
        $this->assertMailContains('class="button"');
        $this->assertMatchesRegularExpression('/class="button"[^>]*style=["\'][^>]*background-color: #18181b/', TestEmailTransport::getMessages()[0]->getBodyHtml());
        // Text part for clients without HTML
        $this->assertMailContainsText('abc123');
    }
}
