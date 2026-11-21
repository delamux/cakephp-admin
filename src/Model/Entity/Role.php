<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * Role (slug = users.role).
 *
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property bool $protected
 * @property array<\App\Model\Entity\Permission> $permissions
 * @property \Cake\I18n\DateTime $created
 * @property \Cake\I18n\DateTime $modified
 * @property \Cake\ORM\Entity $_joinData
 */
class Role extends Entity
{
    /**
     * `protected` is never mass-assignable (only the migration sets it, for superadmin).
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'slug' => true,
        'name' => true,
        'permissions' => true,
        'protected' => false,
    ];
}
