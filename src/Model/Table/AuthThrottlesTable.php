<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;

/**
 * Per-IP throttling counters (`auth_throttles`). Written by App\Http\AuthThrottle with an atomic
 * SQL upsert; this class exists so the table can be used through the ORM (fixtures, inspection)
 * while fallback Table classes are disabled.
 */
class AuthThrottlesTable extends Table
{
    /**
     * @param array<string, mixed> $config Config.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('auth_throttles');
        $this->setPrimaryKey(['bucket', 'ip_hash']);
    }
}
