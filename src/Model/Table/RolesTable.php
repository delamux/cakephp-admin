<?php
declare(strict_types=1);

namespace App\Model\Table;

use App\Model\Entity\Role;
use Cake\Core\Configure;
use Cake\Datasource\EntityInterface;
use Cake\Event\EventInterface;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Validation\Validator;

/**
 * Roles stored in the database.
 *
 * Rules: slug lowercase and unique; protected roles (superadmin) cannot be renamed or deleted;
 * a role assigned to users cannot be deleted nor change its slug (users.role keeps the slug).
 *
 * @method \App\Model\Entity\Role newEntity(array<string, mixed> $data, array<string, mixed> $options = [])
 * @method \App\Model\Entity\Role patchEntity(\Cake\Datasource\EntityInterface $entity, array<string, mixed> $data, array<string, mixed> $options = [])
 * @method \Cake\ORM\Query\SelectQuery<\App\Model\Entity\Role> findBySlug(string $slug)
 * @extends \Cake\ORM\Table<array{Timestamp: \Cake\ORM\Behavior\TimestampBehavior}, \App\Model\Entity\Role>
 * @property \Cake\ORM\Association\BelongsToMany<\App\Model\Table\PermissionsTable> $Permissions
 * @method \Cake\Datasource\ResultSetInterface<int, \App\Model\Entity\Role>|false saveMany(iterable<\App\Model\Entity\Role> $entities, array<string, mixed> $options = [])
 * @method \Cake\Datasource\ResultSetInterface<int, \App\Model\Entity\Role> saveManyOrFail(iterable<\App\Model\Entity\Role> $entities, array<string, mixed> $options = [])
 * @method \Cake\Datasource\ResultSetInterface<int, \App\Model\Entity\Role>|false deleteMany(iterable<\App\Model\Entity\Role> $entities, array<string, mixed> $options = [])
 * @method \Cake\Datasource\ResultSetInterface<int, \App\Model\Entity\Role> deleteManyOrFail(iterable<\App\Model\Entity\Role> $entities, array<string, mixed> $options = [])
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 * @method array<\App\Model\Entity\Role> newEntities(array<array<string, mixed>> $data, array<string, mixed> $options = [])
 * @method \App\Model\Entity\Role get(mixed $primaryKey, array<string, mixed>|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \App\Model\Entity\Role findOrCreate(\Cake\ORM\Query\SelectQuery<\App\Model\Entity\Role>|callable|array<string, mixed> $search, ?callable $callback = null, array<string, mixed> $options = [])
 * @method array<\App\Model\Entity\Role> patchEntities(iterable<\App\Model\Entity\Role> $entities, array<array<string, mixed>> $data, array<string, mixed> $options = [])
 */
class RolesTable extends Table
{
    /**
     * @param array<string, mixed> $config Config.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('roles');
        $this->setDisplayField('name');
        $this->setEntityClass(Role::class);
        $this->addBehavior('Timestamp');
        $this->belongsToMany('Permissions', [
            'through' => 'RolesPermissions',
            'foreignKey' => 'role_id',
            'targetForeignKey' => 'permission_id',
        ]);
    }

    /**
     * @param \Cake\Validation\Validator $validator Validator.
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator): Validator
    {
        return $validator
            ->requirePresence('slug', 'create')
            ->notEmptyString('slug')
            ->regex('slug', '/^[a-z][a-z0-9_-]{1,49}$/', __('Use lowercase letters, numbers, "-" or "_" (2-50).'))
            ->requirePresence('name', 'create')
            ->notEmptyString('name')
            ->maxLength('name', 100);
    }

    /**
     * @param \Cake\ORM\RulesChecker $rules Rules.
     * @return \Cake\ORM\RulesChecker
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->isUnique(['slug'], __('This role already exists.')));

        $rules->addUpdate(
            fn(EntityInterface $role): bool => !$role->get('protected')
                || (!$role->isDirty('slug') && !$role->isDirty('name')),
            'protectedImmutable',
            ['errorField' => 'slug', 'message' => __('This role is protected and cannot be renamed.')],
        );
        $rules->addUpdate(
            fn(EntityInterface $role): bool => !$role->isDirty('slug')
                || !$this->isInUse((string)$role->getOriginal('slug')),
            'slugLockedWhenInUse',
            ['errorField' => 'slug', 'message' => __('Users have this role: its identifier cannot change.')],
        );

        $rules->addDelete(
            fn(EntityInterface $role): bool => !$role->get('protected'),
            'protectedNotDeletable',
            ['errorField' => 'slug', 'message' => __('This role is protected and cannot be deleted.')],
        );
        $rules->addDelete(
            fn(EntityInterface $role): bool => !$this->isInUse((string)$role->get('slug')),
            'notInUse',
            ['errorField' => 'slug', 'message' => __('Users have this role: reassign them before deleting it.')],
        );

        return $rules;
    }

    /**
     * New roles are never protected (only the migration creates the protected superadmin).
     *
     * @param \Cake\Event\EventInterface<\Cake\ORM\Table> $event Event.
     * @param \App\Model\Entity\Role $entity Role.
     * @return void
     */
    public function beforeSave(EventInterface $event, EntityInterface $entity): void
    {
        if ($entity->isNew()) {
            $entity->set('protected', false);
        }
    }

    /**
     * Role whitelist `slug => name`: protected roles first, then by creation.
     *
     * @return array<string, string>
     */
    public function slugList(): array
    {
        /** @var array<string, string> */
        return $this->find('list', keyField: 'slug', valueField: 'name')
            ->orderBy(['protected' => 'DESC', 'id' => 'ASC'])
            ->toArray();
    }

    /**
     * Are there users with this role?
     *
     * @param string $slug Role slug.
     * @return bool
     */
    public function isInUse(string $slug): bool
    {
        return TableRegistry::getTableLocator()->get((string)Configure::read('Users.table', 'Users'))
            ->exists(['role' => $slug]);
    }
}
