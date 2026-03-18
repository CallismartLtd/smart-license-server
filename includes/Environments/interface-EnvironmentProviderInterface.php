<?php
/**
 * Smart License Server Environment Provider Interface file.
 * 
 * @author Callistus Nwachukwu
 */

namespace SmartLicenseServer\Environments;

use SmartLicenseServer\Background\Queue\JobQueue;
use SmartLicenseServer\Background\Workers\QueueWorker;
use SmartLicenseServer\Cache\Cache;
use SmartLicenseServer\Core\URL;
use SmartLicenseServer\Database\Database;
use SmartLicenseServer\Email\Mailer;
use SmartLicenseServer\FileSystem\FileSystem;
use SmartLicenseServer\SettingsAPI\Settings;

/**
 * Defines the contracts every environment adapter most follow to fully load this application.
 */
interface EnvironmentProviderInterface {
    /**
     * Load the monetization providers
     */
    public function load_monetization_providers();

    /**
     * Get the current website URL.
     * 
     * @param string $path Optional path.
     * @param array $qv     Optional query param
     */
    public static function url( string $path = '', array $qv = [] ) : URL;

    /**
     * Get the URL for the assets directory.
     * 
     * @param string $path
     */
    public static function assets_url( string $path = '' ) : URL;

    /**
     * Check key filesystem directories for read/write access.
     *
     * @return void
     */
    public function check_filesystem_errors(): void;

    /**
     * Sets up custom routes.
     */
    public function route_register() : void;

    /**
     * Get the instance of the environment provider class.
     * 
     * @return static
     */
    public static function instance() : static;

    /**
     * Get the database API instance.
     */
    public function database() : Database;

    /**
     * Get the filesystem API instance.
     */
    public function filesystem() : FileSystem;

    /**
     * Get the cache instance API instance.
     */
    public function cache() : Cache;
    /**
     * Get the settings API instance.
     */
    public function settings() : Settings;

    /**
     * Get the mailing API instance.
     */
    public function mailer() : Mailer;

    /**
     * Get the job queue instance.
     */
    public function job_queue(): JobQueue;

    /**
     * Get the background job worker instance.
     */
    public function queue_worker(): QueueWorker;
}