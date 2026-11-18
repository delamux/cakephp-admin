<?php
declare(strict_types=1);

namespace App\Rbac;

use Cake\Cache\Cache;
use Closure;

/**
 * Cache of the RBAC rules built from the database. Cleared on every change of roles/permissions.
 */
class PermissionsCache
{
    public const KEY = 'app_rbac_permissions';
    public const CONFIG = '_cake_model_';

    /**
     * @param \Closure(): array<mixed> $build Rule builder.
     * @return array<mixed>
     */
    public static function remember(Closure $build): array
    {
        return (array)Cache::remember(self::KEY, $build, self::CONFIG);
    }

    /**
     * @return void
     */
    public static function clear(): void
    {
        Cache::delete(self::KEY, self::CONFIG);
    }
}
