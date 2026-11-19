<?php
declare(strict_types=1);

namespace App\Controller;

use App\UseCase\Queue\QueueMonitor;
use Cake\Http\Response;

/**
 * Background jobs screen (superadmin only, `Queue::*` in SUPERADMIN_ONLY): counters, failed jobs
 * with their error, retry / delete, active workers. The Queue plugin's own admin is not routed
 * (Bootstrap from a CDN, against the CSP). Rules in App\UseCase\Queue\QueueMonitor.
 */
class QueueController extends AppController
{
    /**
     * @param \App\UseCase\Queue\QueueMonitor $monitor Read model.
     * @return void
     */
    public function index(QueueMonitor $monitor): void
    {
        $this->set([
            'title' => __('Queue'),
            'counters' => $monitor->counters(),
            'failed' => $monitor->failedJobs(),
            'recent' => $monitor->recentJobs(),
            'workers' => $monitor->workers(),
        ]);
    }

    /**
     * @param \App\UseCase\Queue\QueueMonitor $monitor Use case.
     * @param string $id Job id.
     * @return \Cake\Http\Response|null
     */
    public function retry(QueueMonitor $monitor, string $id): ?Response
    {
        $this->request->allowMethod(['post']);
        $monitor->retry((int)$id)
            ? $this->Flash->success(__('Job {0} queued again.', $id))
            : $this->Flash->error(__('Job {0} is not a failed job.', $id));

        return $this->redirect(['action' => 'index']);
    }

    /**
     * @param \App\UseCase\Queue\QueueMonitor $monitor Use case.
     * @return \Cake\Http\Response|null
     */
    public function retryAll(QueueMonitor $monitor): ?Response
    {
        $this->request->allowMethod(['post']);
        $this->Flash->success(__('{0} failed jobs queued again.', $monitor->retryAll()));

        return $this->redirect(['action' => 'index']);
    }

    /**
     * @param \App\UseCase\Queue\QueueMonitor $monitor Use case.
     * @param string $id Job id.
     * @return \Cake\Http\Response|null
     */
    public function delete(QueueMonitor $monitor, string $id): ?Response
    {
        $this->request->allowMethod(['post']);
        $monitor->delete((int)$id)
            ? $this->Flash->success(__('Job {0} deleted.', $id))
            : $this->Flash->error(__('Only failed jobs can be deleted.'));

        return $this->redirect(['action' => 'index']);
    }
}
