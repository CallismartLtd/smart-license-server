<?php
/**
 * Smart License Server Environment Provider Interface file.
 * 
 * @author Callistus Nwachukwu
 */

namespace SmartLicenseServer\Environments;

use SmartLicenseServer\Core\URL;
use SmartLicenseServer\Database\Adapters\Database;

/**
 * Defines the contracts every environment adapter most follow to fully load this application.
 */
interface EnvironmentProviderInterface {
    /**
     * Load the monetization providers
     */
    public function load_monetization_providers();

    /**
     * Get the website URL.
     */
    public static function url() : string;

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
}