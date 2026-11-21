<?php
declare(strict_types=1);

namespace App\Model\Entity;

use App\Rbac\ActionDiscovery;
use Cake\ORM\Entity;

/**
 * A discovered controller action (`bin/cake permissions sync`).
 *
 * @property int $id
 * @property string $plugin '' for the app
 * @property string $prefix '' when no routing prefix
 * @property string $controller
 * @property string $action
 * @property bool $orphan The action no longer exists in the code
 * @property array<\App\Model\Entity\Role> $roles
 * @property \Cake\I18n\DateTime $created
 * @property \Cake\I18n\DateTime $modified
 * @property \Cake\ORM\Entity $_joinData
 */
class Permission extends Entity
{
    /**
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'plugin' => true,
        'prefix' => true,
        'controller' => true,
        'action' => true,
        'orphan' => true,
        'roles' => true,
    ];

    /**
     * `Plugin.Prefix/Controller::action`.
     *
     * @return string
     */
    public function key(): string
    {
        return ActionDiscovery::key($this->toArray());
    }
}
