<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\DashboardStatsService;
use Cake\I18n\DateTime;
use CakePdf\View\PdfView;

/**
 * PDF reports (CakePdf + Dompdf). Access: admins only (config/permissions.php).
 */
class ReportsController extends AppController
{
    /**
     * @inheritDoc
     */
    public function initialize(): void
    {
        parent::initialize();
        $this->addViewClasses([PdfView::class]);
    }

    /**
     * GET /reports/users.pdf
     */
    public function users(): void
    {
        $usersTable = $this->fetchTable('CakeDC/Users.Users');
        $generatedAt = DateTime::now();

        $this->viewBuilder()->setOption('pdfConfig', [
            'title' => __('Users report'),
            'filename' => 'users-report-' . $generatedAt->format('Ymd-His') . '.pdf',
            'download' => (bool)$this->request->getQuery('download', false),
        ]);

        $this->set([
            'users' => $usersTable->find()->orderBy(['created' => 'DESC'])->all(),
            'summary' => (new DashboardStatsService($usersTable))->summary(),
            'generatedAt' => $generatedAt,
        ]);
    }
}
