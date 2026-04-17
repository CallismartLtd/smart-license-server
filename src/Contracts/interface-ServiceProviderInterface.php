<?php
/**
 * Service providers interface file.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Contracts
 */
namespace SmartLicenseServer\Contracts;

/**
 * Defines the contracts all service providers and adapters must implement.
 */
interface ServiceProviderInterface {
    /**
     * Get the provider/adapter unique ID.
     *
     * @return string
     */
    public static function get_id() : string;

    /**
     * Get the provider name (human-readable).
     *
     * @return string
     */
    public static function get_name() : string;
}