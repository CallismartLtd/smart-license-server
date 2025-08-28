<?php
/**
 * Monetization Provider Collection Class file
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer
 * @subpackage Monetization
 * @since 1.0.0
 */

namespace SmartLicenseServer\Monetization;

defined( 'ABSPATH' ) || exit;

use Smart_License_Server\Monetization\Monetization_Provider_Interface;

/**
 * Collection of available monetization providers.
 * 
 * This class manages the registration and retrieval of different
 * monetization providers integrated with the Smart License Server.
 */
/**
 * Collection of available monetization providers.
 */
class Provider_Collection {
    /**
     * Registered providers.
     *
     * @var Monetization_Provider_Interface[]
     */
    protected $providers = [];

    /**
     * Register a new monetization provider.
     *
     * @param Monetization_Provider_Interface $provider
     * @return void
     */
    public function register_provider( Monetization_Provider_Interface $provider ) {
        $id = $provider->get_provider_id();

        $this->providers[ $id ] = $provider;
    }

    /**
     * Check if a provider is registered.
     *
     * @param string $provider_id
     * @return bool
     */
    public function has_provider( $provider_id ) {
        return isset( $this->providers[ $provider_id ] );
    }

    /**
     * Unregister a provider by its ID.
     * 
     * @param string $provider_id
     * @return bool True if the provider was found and removed, false otherwise.
     */
    public function unregister_provider( $provider_id ) {
        if ( isset( $this->providers[ $provider_id ] ) ) {
            unset( $this->providers[ $provider_id ] );
            return true;
        }
        return false;
    }

    /**
     * Get a registered provider by its ID.
     *
     * @param string $provider_id
     * @return Monetization_Provider_Interface|null
     */
    public function get_provider( $provider_id ) {
        return $this->providers[ $provider_id ] ?? null;
    }

    /**
     * Get all registered providers.
     *
     * @param bool $assoc Whether to preserve keys by provider_id.
     * @return Monetization_Provider_Interface[]
     */
    public function get_all_providers( $assoc = false ) {
        return $assoc ? $this->providers : array_values( $this->providers );
    }
}
