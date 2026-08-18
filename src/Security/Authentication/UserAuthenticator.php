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

        /** @var \SmartLicenseServer\Security\Actors\User $user */
        $user = User::get_by_email( $this->email );

        if ( ! password_verify( $this->password, $user->get_password_hash() ) ) {
            return AuthenticationResult::failure(
                AuthenticationResult::STATUS_INVALID_CREDENTIALS,
                'incorrect_password',
                'The password you entered is incorrect.'
            );
        }

        return AuthenticationResult::authenticated(
            $user,
            'Successfully authenticated.'
        );
    }
}