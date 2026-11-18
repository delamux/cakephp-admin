<?php
declare(strict_types=1);

namespace App\Model\Table;

use App\Model\Entity\Permission;
use Cake\ORM\Table;

/**
 * Catalogue of discovered controller actions.
 *
 * @extends \Cake\ORM\Table<array{Timestamp: \Cake\ORM\Behavior\TimestampBehavior}>
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
