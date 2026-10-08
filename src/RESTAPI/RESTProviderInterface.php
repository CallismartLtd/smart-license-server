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
     * Path segment every REST namespace is mounted under, in every
     * environment: version "v1" is served at <rest root>/smliser/v1.
     *
     * @var string
     */
    public const PREFIX = 'smliser';

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
     * Get available rest API namespaces.
     *
     * @return string[]
     */
    public function namespaces() : array;

    /**
     * Get all available REST versions
     *
     * @return RESTVersionInterface[]
     */
    public function version_instances() : array;
}