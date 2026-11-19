<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * User photo (josegonzalez/cakephp-upload): file name (`<uuid>.webp`) and its directory
 * (`users/<id>/photo/`) relative to the private uploads root. `avatar` (CakeDC) stays for social logins.
 */
class AddPhotoToUsers extends BaseMigration
{
    /**
     * @return void
     */
    public function change(): void
    {
        $this->table('users')
            ->addColumn('photo', 'string', ['limit' => 255, 'null' => true, 'default' => null])
            ->addColumn('photo_dir', 'string', ['limit' => 255, 'null' => true, 'default' => null])
            ->update();
    }
}
