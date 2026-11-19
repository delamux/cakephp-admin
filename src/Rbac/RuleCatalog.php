<?php
declare(strict_types=1);

namespace App\Rbac;

use App\Rbac\Rule\Owner;
use App\Rbac\Rule\PolicyRule;
use Cake\Core\Configure;
use InvalidArgumentException;

/**
 * Rules a role permission can carry (roles_permissions.rule), by name. The database stores the
 * name; this catalogue turns it into the CakeDC `allowed` rule. Defined in code, chosen in the
 * permissions screen. `App.permissions.rules` (name => class) replaces the defaults.
 */
class RuleCatalog
{
    public const OWNER = 'owner';

    /**
     * Only positive rules ("allowed when…"). Account protections that must hold for every role
     * (superadmin/admin accounts, own account) are fixed in code: App\Service\UserRoleManager.
     */
    public const DEFAULT_RULES = [
        self::OWNER => Owner::class,
    ];

    /**
     * @param array<string, \App\Rbac\Rule\PolicyRule> $rules Name => rule.
     */
    public function __construct(private array $rules)
    {
    }

    /**
     * @return self
     */
    public static function fromConfig(): self
    {
        $rules = [];
        foreach ((array)Configure::read('App.permissions.rules', self::DEFAULT_RULES) as $name => $class) {
            $rule = new $class();
            if (!$rule instanceof PolicyRule) {
                throw new InvalidArgumentException(sprintf('Rule `%s` must implement %s.', $name, PolicyRule::class));
            }
            $rules[(string)$name] = $rule;
        }

        return new self($rules);
    }

    /**
     * @param string $name Rule name.
     * @return \App\Rbac\Rule\PolicyRule|null
     */
    public function get(string $name): ?PolicyRule
    {
        return $this->rules[$name] ?? null;
    }

    /**
     * Rules that can decide the action (name => label), catalogue order.
     *
     * @param array<string, mixed> $action `plugin`, `prefix`, `controller`, `action`.
     * @return array<string, string>
     */
    public function applicable(array $action): array
    {
        $labels = [];
        foreach ($this->rules as $name => $rule) {
            if ($rule->appliesTo($action)) {
                $labels[$name] = $rule->label();
            }
        }

        return $labels;
    }

    /**
     * @param string $name Rule name.
     * @param array<string, mixed> $action Action.
     * @return bool
     */
    public function isValid(string $name, array $action): bool
    {
        return ($this->rules[$name] ?? null)?->appliesTo($action) ?? false;
    }
}
