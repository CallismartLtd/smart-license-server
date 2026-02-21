<?php
/**
 * Identity provider interface file.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Security\Context
 */

namespace SmartLicenseServer\Security\Context;

/**
 * Contracts that all identity service providers MUST implement.
 */
interface IdentityProviderInterface {
    /**
     * Authenticate the actor in this request.
     * 
     * @return Principal|null Must either return a valid principal object or null on failure.
     */
    public function authenticate() : ?Principal;
}