<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\DashboardStatsService;
use App\Service\UserRoleManager;

/**
 * Home. Staff (superadmin, admin, editor) get the user statistics, regular users a welcome card.
 */
class DashboardController extends AppController
{
    /**
     * Dashboard home.
     *
     * @return void
     */
    public function index(): void
    {
        $this->set([
            'title' => __('Dashboard'),
            'periodDays' => DashboardStatsService::PERIOD_DAYS,
            'summary' => null,
            'signups' => [],
            'recentUsers' => [],
            'administrators' => [],
        ]);

        $identity = $this->request->getAttribute('identity');
        if ($identity === null || !UserRoleManager::canViewUsers($identity->getOriginalData())) {
            return;
        }

        $stats = new DashboardStatsService($this->fetchTable('CakeDC/Users.Users'));
        $this->set([
            'summary' => $stats->summary(),
            'signups' => $stats->signupsPerDay(),
            'recentUsers' => $stats->recentUsers(8),
            'administrators' => $stats->administrators(5),
        ]);
    }
}
