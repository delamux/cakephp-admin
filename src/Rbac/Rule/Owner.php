<?php
declare(strict_types=1);

namespace App\Rbac\Rule;

use App\Rbac\ActionDiscovery;
use App\Rbac\PermissionsSynchronizer;
use ArrayAccess;
use Cake\Core\App;
use Cake\Core\Configure;
use Cake\Database\Exception\DatabaseException;
use Cake\ORM\Association\BelongsTo;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Utility\Hash;
use Cake\Utility\Inflector;
use Closure;
use InvalidArgumentException;
use PDOException;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionMethod;
use ReflectionNamedType;
use Throwable;

/**
 * Only what the logged-in user owns. The record is the first passed parameter of the URL:
 *
 * - Account actions (`App.permissions.accountActions`, e.g. `/users/edit/<id>`): the account is the user.
 * - Other actions: `created_by` of the record is the user; a record without `created_by` belongs to
 *   whoever created its parent, following `belongsTo` associations (a sales quote line belongs to
 *   the creator of its sales quote header). Table = the controller's default table.
 *
 * Offered for account actions and for actions that receive an id on a table with an owner.
 */
class Owner implements PolicyRule
{
    public const FIELD = 'created_by';

    /**
     * Actions whose first passed parameter is a user account.
     */
    public const ACCOUNT_ACTIONS = [
        'CakeDC/Users.Users::view',
        'CakeDC/Users.Users::edit',
        'CakeDC/Users.Users::delete',
        'CakeDC/Users.Users::resetOneTimePasswordAuthenticator',
        'UserRoles::edit',
    ];

    /**
     * How many `belongsTo` hops are followed to find `created_by`.
     */
    private const MAX_DEPTH = 3;

    /**
     * @param \Closure(array<string, mixed>): bool|null $takesId Does the action receive a record id?
     *   (default: its controller method declares a scalar parameter)
     * @param list<string>|null $accountActions Patterns (default `App.permissions.accountActions`).
     */
    public function __construct(private ?Closure $takesId = null, private ?array $accountActions = null)
    {
    }

    /**
     * @inheritDoc
     */
    public function label(): string
    {
        return __('Owner only');
    }

    /**
     * @inheritDoc
     */
    public function appliesTo(array $action): bool
    {
        if ($this->isAccountAction($action)) {
            return true;
        }
        if (!($this->takesId ?? self::methodTakesId(...))($action)) {
            return false;
        }
        $table = $this->table($action);

        return $table !== null && $this->ownerPath($table) !== null;
    }

    /**
     * @param \ArrayAccess<string, mixed>|array<string, mixed> $user Logged-in user.
     * @param string $role Role.
     * @param \Psr\Http\Message\ServerRequestInterface $request Request.
     * @return bool
     */
    public function allowed(array|ArrayAccess $user, string $role, ServerRequestInterface $request): bool
    {
        $params = (array)$request->getAttribute('params');
        $id = Hash::get($params, 'pass.0');
        $userId = $user['id'] ?? null;
        if (!is_scalar($id) || (string)$id === '' || !is_scalar($userId) || (string)$userId === '') {
            return false;
        }
        $action = [
            'plugin' => (string)($params['plugin'] ?? ''),
            'prefix' => (string)($params['prefix'] ?? ''),
            'controller' => (string)($params['controller'] ?? ''),
            'action' => (string)($params['action'] ?? ''),
        ];
        if ($this->isAccountAction($action)) {
            return (string)$id === (string)$userId;
        }

        $table = $this->table($action);
        $path = $table !== null ? $this->ownerPath($table) : null;
        if ($table === null || $path === null) {
            return false;
        }
        try {
            return $this->creatorOf($table, $id, $path) === (string)$userId;
        } catch (DatabaseException | PDOException | InvalidArgumentException) {
            // Invalid id for the column type: nothing to own
            return false;
        }
    }

    /**
     * Where `created_by` is: `['created_by']` on the table itself, `['Parents', …, 'created_by']`
     * through `belongsTo` associations, or null when the table has no owner.
     *
     * @param \Cake\ORM\Table $table Table.
     * @param int $depth Hops left.
     * @return list<string>|null
     */
    public function ownerPath(Table $table, int $depth = self::MAX_DEPTH): ?array
    {
        if ($table->getSchema()->hasColumn(self::FIELD)) {
            return [self::FIELD];
        }
        if ($depth === 0) {
            return null;
        }
        foreach ($table->associations() as $association) {
            if (!$association instanceof BelongsTo) {
                continue;
            }
            $path = $this->ownerPath($association->getTarget(), $depth - 1);
            if ($path !== null) {
                return [$association->getName(), ...$path];
            }
        }

        return null;
    }

    /**
     * @param \Cake\ORM\Table $table Table of the record.
     * @param string|int|float|bool $id Record id.
     * @param list<string> $path Owner path (App\Rbac\Rule\Owner::ownerPath()).
     * @return string|null `created_by` at the end of the path.
     */
    private function creatorOf(Table $table, string|int|float|bool $id, array $path): ?string
    {
        foreach ($path as $step) {
            $field = $step === self::FIELD ? self::FIELD : $table->getAssociation($step)->getForeignKey();
            $primaryKey = $table->getPrimaryKey();
            if (!is_string($field) || !is_string($primaryKey)) {
                // Composite keys: not supported
                return null;
            }
            $row = $table->find()
                ->select([$field])
                ->where([$table->aliasField($primaryKey) => $id])
                ->disableHydration()
                ->first();
            if ($row === null || $row[$field] === null) {
                return null;
            }
            if ($step === self::FIELD) {
                return (string)$row[$field];
            }
            $id = $row[$field];
            $table = $table->getAssociation($step)->getTarget();
        }

        return null;
    }

    /**
     * @param array<string, mixed> $action Action.
     * @return bool
     */
    private function isAccountAction(array $action): bool
    {
        /** @var list<string> $patterns */
        $patterns = $this->accountActions ?? (array)Configure::read('App.permissions.accountActions', self::ACCOUNT_ACTIONS);

        return PermissionsSynchronizer::matches(ActionDiscovery::key($action), $patterns);
    }

    /**
     * Default table of the controller, when its database table exists.
     *
     * @param array<string, mixed> $action Action.
     * @return \Cake\ORM\Table|null
     */
    private function table(array $action): ?Table
    {
        $plugin = (string)($action['plugin'] ?? '');
        $alias = ($plugin !== '' ? $plugin . '.' : '') . $action['controller'];
        $locator = TableRegistry::getTableLocator();
        try {
            $table = $locator->exists($alias) ? $locator->get($alias) : null;
            if ($table === null) {
                $tables = $locator->get($alias)->getConnection()->getSchemaCollection()->listTables();
                if (!in_array(Inflector::tableize((string)$action['controller']), $tables, true)) {
                    $locator->remove($alias);

                    return null;
                }
                $table = $locator->get($alias);
            }
            $table->getSchema();

            return $table;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Does the controller method of the action declare a scalar parameter (the record id)?
     * Injected services (class-typed parameters) do not count.
     *
     * @param array<string, mixed> $action Action.
     * @return bool
     */
    private static function methodTakesId(array $action): bool
    {
        $plugin = (string)($action['plugin'] ?? '');
        $prefix = (string)($action['prefix'] ?? '');
        $class = App::className(
            ($plugin !== '' ? $plugin . '.' : '') . ($prefix !== '' ? $prefix . '/' : '') . $action['controller'],
            'Controller',
            'Controller',
        );
        if ($class === null || !method_exists($class, (string)$action['action'])) {
            return false;
        }
        foreach ((new ReflectionMethod($class, (string)$action['action']))->getParameters() as $parameter) {
            $type = $parameter->getType();
            if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
                return true;
            }
        }

        return false;
    }
}
