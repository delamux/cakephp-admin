<?php
declare(strict_types=1);

namespace App\UseCase;

use ArrayAccess;

/**
 * Value object: who performs a use case (a logged-in user or the console).
 */
final readonly class Actor
{
    public const CONSOLE = 'console';

    /**
     * @param string|null $id User id (null = console).
     * @param string $username Display name stored in the audit log.
     */
    public function __construct(public ?string $id, public string $username)
    {
    }

    /**
     * @param \ArrayAccess<string, mixed>|array<string, mixed> $user Identity data.
     * @return self
     */
    public static function fromUser(ArrayAccess|array $user): self
    {
        $id = isset($user['id']) ? (string)$user['id'] : null;
        $username = isset($user['username']) && $user['username'] !== '' ? (string)$user['username'] : null;

        return new self($id, $username ?? $id ?? 'unknown');
    }

    /**
     * @return self
     */
    public static function console(): self
    {
        return new self(null, self::CONSOLE);
    }

    /**
     * @return bool
     */
    public function isConsole(): bool
    {
        return $this->id === null && $this->username === self::CONSOLE;
    }
}
