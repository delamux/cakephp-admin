<?php
declare(strict_types=1);

namespace App\Rbac\Rule;

use CakeDC\Auth\Rbac\Rules\Rule;

/**
 * A rule of the catalogue (App\Rbac\RuleCatalog): the `allowed` of a CakeDC RBAC rule plus where it
 * can be chosen in the permissions screen.
 */
interface PolicyRule extends Rule
{
    /**
     * Shown in the permissions screen.
     *
     * @return string
     */
    public function label(): string;

    /**
     * Can this rule decide the action? (e.g. "its me" needs an account id in the URL)
     *
     * @param array<string, mixed> $action `plugin`, `prefix`, `controller`, `action` ('' when empty).
     * @return bool
     */
    public function appliesTo(array $action): bool;
}
