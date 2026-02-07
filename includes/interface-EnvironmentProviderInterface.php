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
     * Auto registers the monetization providers
     */
    public function auto_register_monetization_providers();

    /**
     * Database upgrade request parser
     */
    // public static function parse_database_migration_request();
}