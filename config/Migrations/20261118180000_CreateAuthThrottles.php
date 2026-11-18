<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Per-IP throttling counters in PostgreSQL (App\Http\AuthThrottle): shared by every app container
 * and kept across redeploys. One row per bucket and client (IP stored as a SHA-256 hash).
 */
class CreateAuthThrottles extends BaseMigration
{
    /**
     * @return void
     */
    public function change(): void
    {
        $this->table('auth_throttles', ['id' => false, 'primary_key' => ['bucket', 'ip_hash']])
            ->addColumn('bucket', 'string', ['limit' => 50, 'null' => false])
            ->addColumn('ip_hash', 'char', ['limit' => 64, 'null' => false])
            ->addColumn('hits', 'integer', ['null' => false, 'default' => 0])
            ->addColumn('window_ends', 'integer', ['null' => false])
            ->addIndex(['window_ends'])
            ->create();
    }
}
