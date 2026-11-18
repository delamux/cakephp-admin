<?php
declare(strict_types=1);

namespace App\Test\TestCase;

use App\Application;
use App\Command\PermissionsPurgeCommand;
use App\Command\PermissionsSyncCommand;
use App\UseCase\Permissions\PermissionMatrix;
use App\UseCase\Permissions\PurgePermissionHistory;
use App\UseCase\Permissions\SyncPermissions;
use App\UseCase\Permissions\UpdateRolePermissions;
use App\UseCase\Roles\ManageRoles;
use Cake\TestSuite\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * DDD-lite layering: controllers and commands delegate to use cases (src/UseCase) resolved by the
 * DI container; they never orchestrate audit/cache/config factories themselves.
 */
class ArchitectureTest extends TestCase
{
    /**
     * Thin delivery layer. AppController is the documented exception: its guards run in
     * beforeFilter for CakeDC's controllers, where there is no action injection.
     *
     * @return list<string>
     */
    private function deliveryFiles(): array
    {
        $files = array_merge(glob(APP . 'Controller/*.php') ?: [], glob(APP . 'Command/*.php') ?: []);

        return array_values(array_filter($files, fn(string $f): bool => basename($f) !== 'AppController.php'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function forbidden(): array
    {
        return [
            'audit log' => ['/PermissionAudit(Log)?\b(::|\s*\()/'],
            'RBAC cache' => ['/PermissionsCache::/'],
            'config factories' => ['/::fromConfig\(/'],
            'audit/permissions tables' => ['/fetchTable\(\'(PermissionAudits|RolesPermissions)\'\)->(save|delete|update)/'],
        ];
    }

    #[DataProvider('forbidden')]
    public function testDeliveryLayerDoesNotOrchestrate(string $pattern): void
    {
        $offenders = [];
        foreach ($this->deliveryFiles() as $file) {
            if (preg_match($pattern, (string)file_get_contents($file))) {
                $offenders[] = basename($file);
            }
        }

        $this->assertSame([], $offenders, 'Move it to a use case in src/UseCase');
    }

    public function testUseCasesAndCommandsResolveFromTheContainer(): void
    {
        $app = new Application(CONFIG);
        $app->bootstrap();
        $app->pluginBootstrap();
        $container = $app->getContainer();

        foreach (
            [
                PermissionMatrix::class, UpdateRolePermissions::class, SyncPermissions::class,
                PurgePermissionHistory::class, ManageRoles::class,
                PermissionsSyncCommand::class, PermissionsPurgeCommand::class,
            ] as $id
        ) {
            $this->assertInstanceOf($id, $container->get($id));
        }
    }
}
