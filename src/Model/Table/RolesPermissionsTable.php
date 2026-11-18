<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;

/**
 * Allow-list role ↔ permission.
 */
class RolesPermissionsTable extends Table
{
    /**
     * @param array<string, mixed> $config Config.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('roles_permissions');
        $this->belongsTo('Roles');
        $this->belongsTo('Permissions');
    }
}
