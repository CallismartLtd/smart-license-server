<?php
/**
 * Identity provider interface file.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Security\Context
 */

namespace SmartLicenseServer\Security\Authentication\IdentityProviders;

use SmartLicenseServer\Security\Context\Principal;

/**
 * Contracts that all identity service providers MUST implement.
 */
interface IdentityProviderInterface {
    /**
     * Authenticate the actor.
     * 
     * This action includes but not limited to checking the actor's
     * session(s) API key(s), and other credentials. It is essentially
     * discovering the `current user/actor`.
     * 
     * @return Principal|null
     */
    public function authenticate() : ?Principal;
}