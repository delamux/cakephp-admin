<?php
declare(strict_types=1);

namespace App\Mailer;

use Cake\Mailer\Mailer;

/**
 * Base mailer for the application emails: app layout (templates/layout/email) + inlined CSS.
 *
 * Extend it and send multipart emails:
 *   $this->setEmailFormat('both')->viewBuilder()->setTemplate('welcome');
 */
class AppMailer extends Mailer
{
    use InlineCssTrait;
}
