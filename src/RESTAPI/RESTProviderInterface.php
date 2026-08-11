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
     * @param mixed ...$params
     * @return mixed
     */
    public function enforce_https( ...$params ) : mixed;

    /**
     * Authenticate the current principal/actor
     */
    public function authenticate();

    /**
     * Get the REST namespace.
     * 
     * @return string
     */
    public function namespace() : string;

    /**
     * Get the current REST API version.
     * 
     * @return RESTVersionInterface
     */
    public function version_instance() : RESTVersionInterface;
}