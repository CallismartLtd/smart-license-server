<?php
/**
 * REST API Provider interface file.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\RESTAPI
 */

namespace SmartLicenseServer\RESTAPI;

/**
 * Defines the contracts which a REST API Provider must implement.
 */
interface RESTProviderInterface {
    /**
     * Enforce secure HTTPS/TLS connection.
     * 
     * @return mixed
     */
    public function enforce_https( ...$params ) : mixed;

    /**
     * Register REST API routes
     */
    public function register_rest_routes() : void;

    /**
     * Authenticate the current principal/actor
     */
    public function authenticate();
}