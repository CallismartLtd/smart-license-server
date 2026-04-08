<?php
/**
 * Smart License Server Environment Provider Interface file.
 * 
 * @author Callistus Nwachukwu
 */

namespace SmartLicenseServer\Environments;

use SmartLicenseServer\Background\Queue\JobQueue;
use SmartLicenseServer\Background\Schedule\Scheduler;
use SmartLicenseServer\Background\Workers\QueueWorker;
use SmartLicenseServer\Cache\Cache;
use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\URL;
use SmartLicenseServer\Database\Database;
use SmartLicenseServer\Email\EmailProvidersRegistry;
use SmartLicenseServer\Email\Mailer;
use SmartLicenseServer\FileSystem\FileSystem;
use SmartLicenseServer\Http\HttpClient;
use SmartLicenseServer\Monetization\MonetizationRegistry;
use SmartLicenseServer\RESTAPI\RESTProviderInterface;
use SmartLicenseServer\SettingsAPI\Settings;
use SmartLicenseServer\Templates\TemplateLocator;

/**
 * Defines the contracts every environment adapter most follow to fully load this application.
 */
interface EnvironmentProviderInterface {

    /**
     * Get the website URL.
     * 
     * @param string $path Optional path.
     * @param array $qv     Optional query param
     */
    public static function url( string $path = '', array $qv = [] ) : URL;

    /**
     * Get the website admin URL.
     * 
     * @param string $path Optional path.
     * @param array $qv     Optional query param
     */
    public static function adminUrl( string $path = '', array $qv = [] ) : URL;

    /**
     * Get the website REST API URL.
     * 
     * @param string $path Optional path.
     * @param array $qv     Optional query param
     */
    public static function restAPIUrl( string $path = '', array $qv = [] ) : URL;

    /**
     * Get the REST API provider instance
     * 
     * @return RESTProviderInterface
     */
    public function restProvider() : RESTProviderInterface;

    /**
     * Get the URL for the assets directory.
     * 
     * @param string $path
     */
    public static function assets_url( string $path = '', array $params = [] ) : URL;

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

    /**
     * Get the task scheduler API instance.
     */
    public function scheduler(): Scheduler;

    /**
     * Get the current request object.
     * 
     * @return Request
     */
    public function request() : Request;

    /**
     * Get the global http client.
     */
    public function httpClient() : HttpClient;

    /**
     * Get the monetization provider registry.
     * 
     * @return MonetizationRegistry
     */
    public function monetizationRegistry() : MonetizationRegistry;

    /**
     * Get the email provider registry.
     * 
     * @return EmailProvidersRegistry
     */
    public function emailProviders() : EmailProvidersRegistry;

    /**
     * Get the template locator instance.
     * 
     * @return TemplateLocator
     */
    public function templateLocator() : TemplateLocator;
}