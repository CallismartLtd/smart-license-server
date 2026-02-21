<?php
/**
 * Smart License Server Environment Provider Interface file.
 * 
 * @author Callistus Nwachukwu
 */

namespace SmartLicenseServer\Environments;

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
     */
    public static function assets_url() : string;

    /**
     * Check key filesystem directories for read/write access.
     *
     * Uses a transient to avoid repeated expensive filesystem checks.
     *
     * @return void
     */
    public function check_filesystem_errors(): void;

    /**
     * Sets up custom routes.
     */
    public function route_register() :void;
}