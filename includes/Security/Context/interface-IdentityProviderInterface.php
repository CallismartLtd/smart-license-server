<?php
/**
 * Identity provider interface file.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Security\Context
 */

namespace SmartLicenseServer\Security\Context;

use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Exceptions\RequestException;

/**
 * Contracts that all identity service providers MUST implement.
 */
interface IdentityProviderInterface {
    /**
     * Authenticate the actor.
     * 
     * @return Principal|null Must either return a valid principal object or null on failure.
     */
    public function authenticate() : ?Principal;

    /**
     * Logon a human user using email and password
     * 
     * @param string $email     The user email.
     * @param string $pwd       The user password
     * @param bool   $remember  Optionally remember the user(default: false).
     * @return RequestException|Principal
     */
    public function logon( string $email, string $pwd, bool $remember = false ) : RequestException|Principal;

    /**
     * Signup the human user.
     * 
     * @param Request $request
     * @return RequestException|Principal
     */
    public function signup( Request $request ) : RequestException|Principal;

    /**
     * Get password
     * 
     * @param string $email The user email
     * @return void
     */
    public function forgot_password( string $email ) : void;
}