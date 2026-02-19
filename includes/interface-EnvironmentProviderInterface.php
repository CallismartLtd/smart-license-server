<?php
/**
 * Smart License Server Environment Provider Interface file
 * 
 * @author Callistus Nwachukwu
 */

namespace SmartLicenseServer;

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
}