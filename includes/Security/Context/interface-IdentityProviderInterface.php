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
use SmartLicenseServer\Security\Actors\User;

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
     * Reset user password
     * 
     * @param User $user The user object
     * @param string $new_pwd The new user password
     * @return bool
     */
    public function reset_password( User $user, string $new_pwd ) : bool;

    /**
     * Logout the current actor.
     * 
     * @return void
     */
    public function logout() : void;
}