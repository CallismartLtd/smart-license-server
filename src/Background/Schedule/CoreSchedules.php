<?php
/**
 * Core Schedules class file.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Background\Schedule
 */
declare( strict_types = 1 );
namespace SmartLicenseServer\Background\Schedule;

use SmartLicenseServer\Background\Jobs\Analytics\PruneAnalyticsLogsJob;
use SmartLicenseServer\Background\Jobs\Analytics\PruneLicenseActivityLogsJob;
use SmartLicenseServer\Background\Jobs\Apps\CleanTrashedAppsJob;
use SmartLicenseServer\Background\Jobs\Licenses\ExpireLicensesJob;
use SmartLicenseServer\Background\Jobs\Licenses\NotifyExpiringLicensesJob;
use SmartLicenseServer\Background\Jobs\Licenses\PruneLicenseMetaJob;
use SmartLicenseServer\Background\Jobs\Monetization\CleanExpiredTokensJob;
use SmartLicenseServer\Background\Queue\JobDTO;
use SmartLicenseServer\Background\Queue\JobQueue;

/**
 * Core background schedule.
 * 
 * Registers all core automated tasks.
 */
final class CoreSchedules {
    public function __construct(
        protected JobQueue $job_queue
    ) {}

    /**
     * Callback to prune analytics log entry weekly.
     */
    public function pruneAnalyticsJob() : void {
        $this->job_queue->dispatch(
            JobDTO::make(
                job_class : PruneAnalyticsLogsJob::class,
                payload   : [],
                queue     : JobDTO::QUEUE_LOW,
            )
        );
    }

    /**
     * Callback to prune license activity log entry nightly.
     */
    public function pruneLicenseActivityJob() : void {
        $this->job_queue->dispatch(
            JobDTO::make(
                job_class : PruneLicenseActivityLogsJob::class,
                payload   : [],
                queue     : JobDTO::QUEUE_LOW,
            )
        );
    }

    /**
     * Callback to mark licenses that has past end date as expired and notify licensees.
     */
    public function markExpiredLicenses() : void {
        $this->job_queue->dispatch(
            JobDTO::make(
                job_class : ExpireLicensesJob::class,
                payload   : [ 'batch_size' => 100 ],
                queue     : JobDTO::QUEUE_LOW,
            )
        );
    }

    /**
     * Send 7-day expiry reminder to licensees.
     */
    public function sendExpiringLicense7Days() : void {
        $this->job_queue->dispatch(
            JobDTO::make(
                job_class : NotifyExpiringLicensesJob::class,
                payload   : [ 'days_before' => 7, 'batch_size' => 100 ],
                queue     : JobDTO::QUEUE_LOW,
            )
        );
    }            
    
    /**
     * Send 3-day expiry reminder to licensees.
     */
    public function sendExpiringLicense3Days() : void {
        $this->job_queue->dispatch(
            JobDTO::make(
                job_class : NotifyExpiringLicensesJob::class,
                payload   : [ 'days_before' => 3, 'batch_size' => 100 ],
                queue     : JobDTO::QUEUE_LOW,
            )
        );
    }

    /**
     * Prune orphaned license meta rows weekly.
     */
    public function pruneOphanedLicenseMeta() : void {
        $this->job_queue->dispatch(
            JobDTO::make(
                job_class : PruneLicenseMetaJob::class,
                payload   : [],
                queue     : JobDTO::QUEUE_LOW,
            )
        );
    }

    /**
     * Clean expired download tokens every 4 hours.
     */
    public function pruneExpiredDownloadtoken() : void {
        $this->job_queue->dispatch(
            JobDTO::make(
                job_class : CleanExpiredTokensJob::class,
                payload   : [],
                queue     : JobDTO::QUEUE_LOW,
            )
        );
    }

    /**
     * Permanently delete trashed apps older than 30 days — weekly.
     */
    public function deleteTrashedApps() : void {
        $this->job_queue->dispatch(
            JobDTO::make(
                job_class : CleanTrashedAppsJob::class,
                payload   : [ 'days_in_trash' => 30, 'batch_size' => 50 ],
                queue     : JobDTO::QUEUE_LOW,
            )
        );
    }

    /**
     * Release stale running jobs every 15 minutes.
     */
    public function releaseStaleJobs() : void {
        $this->job_queue->release_stale_running_jobs();
    }

    /**
     * Purge completed jobs older than 7 days, nightly.
     */
    public function purgeCompletedJobs() : void {
        $this->job_queue->purge_completed_jobs( 7 );
    }


}