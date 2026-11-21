<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;

/**
 * Allow-list role ↔ permission.
 *
 * @extends \Cake\ORM\Table<array{}, \Cake\ORM\Entity>
 * @property \Cake\ORM\Association\BelongsTo<\App\Model\Table\RolesTable> $Roles
 * @property \Cake\ORM\Association\BelongsTo<\App\Model\Table\PermissionsTable> $Permissions
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
