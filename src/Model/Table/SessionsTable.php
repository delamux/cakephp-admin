<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;

/**
 * PHP sessions (`sessions`), written by App\Http\Session\AppDatabaseSession: the serialized data plus
 * who it belongs to, from where and with which browser. `expires` is a Unix timestamp.
 */
class SessionsTable extends Table
{
    /**
     * @param array<string, mixed> $config Config.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('sessions');
        $this->setPrimaryKey('id');
    }
}
