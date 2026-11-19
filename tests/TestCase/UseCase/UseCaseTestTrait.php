<?php
declare(strict_types=1);

namespace App\Test\TestCase\UseCase;

use App\Rbac\PermissionAuditLog;
use App\Rbac\PermissionsSynchronizer;
use App\Service\UserRoleManager;
use App\UseCase\Actor;
use App\UseCase\Permissions\PermissionMatrix;

/**
 * Builds the use cases with explicit dependencies (config/users.php is not loaded in unit tests).
 */
trait UseCaseTestTrait
{
    protected function synchronizer(): PermissionsSynchronizer
    {
        // The app defaults (config/users.php)
        return new PermissionsSynchronizer((require CONFIG . 'users.php')['App.permissions']['defaults']);
    }

    protected function matrix(): PermissionMatrix
    {
        // System rules of config/permissions.php
        return new PermissionMatrix($this->synchronizer(), UserRoleManager::fromConfig());
    }

    protected function auditLog(): PermissionAuditLog
    {
        return new PermissionAuditLog();
    }

    protected function root(): Actor
    {
        return new Actor('0b6f2a4e-1c3d-4e5f-8a9b-0c1d2e3f4a5b', 'root');
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function audits(): array
    {
        return $this->fetchTable('PermissionAudits')->find()->orderBy(['id' => 'ASC'])
            ->disableHydration()->all()->toList();
    }
}
