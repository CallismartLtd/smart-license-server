<?php
/**
 * User authentication class file.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Security\Authentication
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Security\Authentication;

use SensitiveParameter;
use SmartLicenseServer\Security\Actors\User;
use SmartLicenseServer\Security\Context\ContextServiceProvider;

/**
 * Handles a human user authentication flow.
 * 
 * A human user can authenticate, act for self or on behalf of another
 * resource owner within the capabilities of the role assigned to them.
 * @see \SmartLicenseServer\Security\Actors\User
 */
class UserAuthenticator implements AuthenticatorInterface {

    /**
     * Class constructor
     * 
     * @param string $email     The user email.
     * @param string $password  The user password.
     */
    public function __construct(
        protected string $email,
        #[SensitiveParameter] protected string $password
    ) {}

    /**
     * {@inheritdoc}
     */
    public function authenticate() : AuthenticationResult {
        if ( empty( $this->email ) ) {
            return AuthenticationResult::failure(
                AuthenticationResult::STATUS_MISSING_CREDENTIALS,
                'empty_email',
                'Email address was not provided.'
            );
        }

        // Email only login.
        if ( ! \is_email( $this->email ) ) {
            return AuthenticationResult::failure(
                AuthenticationResult::STATUS_INVALID_CREDENTIALS,
                'invalid_email',
                'Please provide a valid email address.'
            );
        }

        if ( empty( $this->password ) ) {
            return AuthenticationResult::failure(
                AuthenticationResult::STATUS_MISSING_CREDENTIALS,
                'empty_password',
                'Password was not provided.'
            );
        }

        if ( ! User::email_exists( $this->email ) ) {
            return AuthenticationResult::failure(
                AuthenticationResult::STATUS_ACTOR_NOT_FOUND,
                'invalid_user',
                'Unknown email address.'
            );
        }

        /**
         * @var \SmartLicenseServer\Security\Actors\User $user
         *
         * User already fetched and cached by User::email_exists. 
         */
        $user = User::get_by_email( $this->email );

        if ( ! password_verify( $this->password, $user->get_password_hash() ) ) {
            return AuthenticationResult::failure(
                AuthenticationResult::STATUS_INVALID_CREDENTIALS,
                'incorrect_password',
                'The password you entered is incorrect.'
            );
        }

        // Is this user a valid resource owner?
        $owner  = ContextServiceProvider::get_default_owner( $user );

        // Maybe acting for self or another resource owner like an organization?
        $owner_subject  = $owner ? ContextServiceProvider::get_owner_subject( $owner ) : null;

        // Actor's role for either an organization or self
        // must be valid regardless.
        $role   = ContextServiceProvider::get_principal_role( $user, $owner_subject );

        if ( ! $role ) {
            return AuthenticationResult::failure(
                status: AuthenticationResult::STATUS_UNAUTHORIZED,
                error_code: 'invalid_role',
                message: 'Authentication denied due to invalid role.'
            );
        }

        return AuthenticationResult::authenticated(
            actor: $user,
            owner: $owner,
            role: $role,
            message: 'Successfully authenticated.'
        );
    }
}