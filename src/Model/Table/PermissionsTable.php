<?php
declare(strict_types=1);

namespace App\Model\Table;

use App\Model\Entity\Permission;
use Cake\ORM\Table;

/**
 * Catalogue of discovered controller actions.
 *
 * @extends \Cake\ORM\Table<array{Timestamp: \Cake\ORM\Behavior\TimestampBehavior}, \App\Model\Entity\Permission>
 * @property \Cake\ORM\Association\BelongsToMany<\App\Model\Table\RolesTable> $Roles
 * @method \Cake\Datasource\ResultSetInterface<int, \App\Model\Entity\Permission>|false saveMany(iterable<\App\Model\Entity\Permission> $entities, array<string, mixed> $options = [])
 * @method \Cake\Datasource\ResultSetInterface<int, \App\Model\Entity\Permission> saveManyOrFail(iterable<\App\Model\Entity\Permission> $entities, array<string, mixed> $options = [])
 * @method \Cake\Datasource\ResultSetInterface<int, \App\Model\Entity\Permission>|false deleteMany(iterable<\App\Model\Entity\Permission> $entities, array<string, mixed> $options = [])
 * @method \Cake\Datasource\ResultSetInterface<int, \App\Model\Entity\Permission> deleteManyOrFail(iterable<\App\Model\Entity\Permission> $entities, array<string, mixed> $options = [])
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 * @method \App\Model\Entity\Permission newEntity(array<string, mixed> $data, array<string, mixed> $options = [])
 * @method array<\App\Model\Entity\Permission> newEntities(array<array<string, mixed>> $data, array<string, mixed> $options = [])
 * @method \App\Model\Entity\Permission get(mixed $primaryKey, array<string, mixed>|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \App\Model\Entity\Permission findOrCreate(\Cake\ORM\Query\SelectQuery<\App\Model\Entity\Permission>|callable|array<string, mixed> $search, ?callable $callback = null, array<string, mixed> $options = [])
 * @method \App\Model\Entity\Permission patchEntity(\Cake\Datasource\EntityInterface $entity, array<string, mixed> $data, array<string, mixed> $options = [])
 * @method array<\App\Model\Entity\Permission> patchEntities(iterable<\App\Model\Entity\Permission> $entities, array<array<string, mixed>> $data, array<string, mixed> $options = [])
 */
class PermissionsTable extends Table
{
    /**
     * @param array<string, mixed> $config Config.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('permissions');
        $this->setEntityClass(Permission::class);
        $this->addBehavior('Timestamp');
        $this->belongsToMany('Roles', [
            'through' => 'RolesPermissions',
            'foreignKey' => 'permission_id',
            'targetForeignKey' => 'role_id',
        ]);
    }
}
