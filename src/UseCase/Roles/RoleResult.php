<?php
declare(strict_types=1);

namespace App\UseCase\Roles;

use Cake\Datasource\EntityInterface;

/**
 * Outcome of a roles use case: the entity (with its errors) and the first error message.
 */
final readonly class RoleResult
{
    /**
     * @param bool $ok Succeeded.
     * @param \Cake\Datasource\EntityInterface $role Role (with validation/rule errors on failure).
     * @param string|null $error First error message.
     */
    public function __construct(public bool $ok, public EntityInterface $role, public ?string $error = null)
    {
    }

    /**
     * @param bool $ok Succeeded.
     * @param \Cake\Datasource\EntityInterface $role Role.
     * @return self
     */
    public static function of(bool $ok, EntityInterface $role): self
    {
        $error = null;
        foreach ($role->getErrors() as $errors) {
            foreach ((array)$errors as $message) {
                $error ??= is_string($message) ? $message : null;
            }
        }

        return new self($ok, $role, $ok ? null : $error);
    }
}
