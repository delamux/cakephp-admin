<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\DashboardStatsService;
use App\Test\TestCase\UserFactoryTrait;
use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;

/**
 * Business rules of the dashboard numbers.
 */
class DashboardStatsServiceTest extends TestCase
{
    use UserFactoryTrait;

    protected array $fixtures = ['app.Users'];

    private DashboardStatsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DashboardStatsService(TableRegistry::getTableLocator()->get('CakeDC/Users.Users'));
    }

    public function testSummaryCountsTotalActivePendingAndAdmins(): void
    {
        $this->createUser(['active' => true]);
        $this->createUser(['active' => false]);
        $this->createUser(['role' => 'admin']);
        $this->createUser(['role' => 'user', 'is_superuser' => true]);

        $summary = $this->service->summary();

        $this->assertSame(4, $summary['total']);
        $this->assertSame(3, $summary['active']);
        $this->assertSame(1, $summary['pending']);
        $this->assertSame(2, $summary['admins'], 'Admins = role admin OR superuser');
    }

    public function testNewUsersComparesLast30DaysWithPrevious30Days(): void
    {
        $now = DateTime::now();
        $this->createUser(['created' => $now->subDays(1)]);
        $this->createUser(['created' => $now->subDays(10)]);
        $this->createUser(['created' => $now->subDays(29)]);
        $this->createUser(['created' => $now->subDays(40)]);
        $this->createUser(['created' => $now->subDays(45)]);
        $this->createUser(['created' => $now->subDays(90)]);

        $summary = $this->service->summary();

        $this->assertSame(3, $summary['newUsers']);
        $this->assertSame(2, $summary['newUsersPrevious']);
        $this->assertSame(50.0, $summary['newUsersGrowth']);
    }

    public function testGrowthIsNullWhenThereIsNoPreviousPeriodToCompare(): void
    {
        $this->createUser(['created' => DateTime::now()->subDays(2)]);

        $this->assertNull($this->service->summary()['newUsersGrowth']);
    }

    public function testGrowthIsNegativeWhenSignupsDrop(): void
    {
        $now = DateTime::now();
        $this->createUser(['created' => $now->subDays(3)]);
        $this->createUser(['created' => $now->subDays(35)]);
        $this->createUser(['created' => $now->subDays(36)]);
        $this->createUser(['created' => $now->subDays(37)]);
        $this->createUser(['created' => $now->subDays(38)]);

        $this->assertSame(-75.0, $this->service->summary()['newUsersGrowth']);
    }

    public function testSignupsPerDayFillsEveryDayOldestFirstEndingToday(): void
    {
        $now = DateTime::now();
        $this->createUser(['created' => $now]);
        $this->createUser(['created' => $now]);
        $this->createUser(['created' => $now->subDays(2)]);
        $this->createUser(['created' => $now->subDays(20)]); // outside the 7 day window

        $series = $this->service->signupsPerDay(7);

        $this->assertCount(7, $series);
        $this->assertSame($now->subDays(6)->format('Y-m-d'), $series[0]['date']);
        $this->assertSame($now->format('Y-m-d'), $series[6]['date']);
        $this->assertSame([0, 0, 0, 0, 1, 0, 2], array_column($series, 'users'));
    }

    public function testAdministratorsAreAdminRoleOrSuperusersRegardlessOfSignupDate(): void
    {
        $now = DateTime::now();
        $this->createUser(['username' => 'old-admin', 'role' => 'admin', 'created' => $now->subDays(400)]);
        $this->createUser(['username' => 'super', 'role' => 'user', 'is_superuser' => true, 'created' => $now->subDays(10)]);
        $this->createUser(['username' => 'regular', 'role' => 'user', 'created' => $now]);

        $admins = $this->service->administrators();

        $this->assertSame(['super', 'old-admin'], array_map(fn($u) => $u->username, $admins));
    }

    public function testRecentUsersAreNewestFirstAndLimited(): void
    {
        $now = DateTime::now();
        $this->createUser(['username' => 'old', 'created' => $now->subDays(5)]);
        $this->createUser(['username' => 'newest', 'created' => $now]);
        $this->createUser(['username' => 'middle', 'created' => $now->subDays(1)]);

        $recent = $this->service->recentUsers(2);

        $this->assertSame(['newest', 'middle'], array_map(fn($u) => $u->username, $recent));
    }
}
