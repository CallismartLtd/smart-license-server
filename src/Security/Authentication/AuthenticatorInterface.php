<?php
/**
 * Authentication contract file.
 * 
 * @author Callistus Nwachukwu.
 * @package SmartLicenseServer
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Security\Authentication;

/**
 * Contract the all authenticators should implement.
 */
interface AuthenticatorInterface {
    /**
     * Performs the actual authentication and return an authenticated actor or null.
     * 
     * @return AuthenticationResult
     */
    public function authenticate() : AuthenticationResult;

}