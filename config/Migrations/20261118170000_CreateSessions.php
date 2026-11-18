<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Sessions in PostgreSQL (CakePHP DatabaseSession, `Session.defaults = database`): they survive
 * redeploys and are shared by every app container. Schema of book.cakephp.org/5.x/development/sessions
 * (bytea for PostgreSQL); `expires` indexed for the garbage collector (`DELETE ... WHERE expires < now`).
 */
class CreateSessions extends BaseMigration
{
    /**
     * @return void
     */
    public function change(): void
    {
        $this->table('sessions', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'string', ['limit' => 128, 'null' => false])
            ->addColumn('data', 'binary', ['null' => true])
            ->addColumn('expires', 'integer', ['null' => true])
            ->addColumn('created', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('modified', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['expires'])
            ->create();
    }
}
