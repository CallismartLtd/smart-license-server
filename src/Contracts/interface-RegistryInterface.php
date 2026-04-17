<?php
/**
 * The interface for Registry contracts.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Contracts
 */
namespace SmartLicenseServer\Contracts;

/**
 * The registry interface defines the methods for managing a registry of service providers.
 */
interface RegistryInterface {
    /**
     * Add a service class string to the registry.
     * 
     * @param class-string<ServiceProviderInterface> $serviceClass The class string of 
     * the service to add.
     */
    public function add( string $serviceClass ): self;

    /**
     * Get an instance of a service from the registry.
     * 
     * @param string $id The ID of the service to retrieve.
     * @return class-string An instance of the requested service.
     */
    public function get( string $id ) : ?string;

    /**
     * Check if a service exists in the registry.
     * 
     * @param string $id The ID of the service to check.
     * @return bool True if the service exists, false otherwise.
     */
    public function has( string $id ): bool;

    /**
     * Remove a service from the registry.
     * 
     * @param string $id The ID of the service to remove.
     * @return bool True if the service was removed, false otherwise.
     */
    public function remove( string $id ): bool;

    /**
     * Get all registered services.
     * 
     * @return array An associative array of all registered services, keyed by their IDs.
     */
    public function all(): array;

}