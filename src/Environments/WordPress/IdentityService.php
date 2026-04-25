<?php
/**
 * WordPress identity service provider.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Environments\WordPress
 */

namespace SmartLicenseServer\Environments\WordPress;

use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Exceptions\RequestException;
use SmartLicenseServer\Exceptions\SecurityException;
use SmartLicenseServer\Security\Actors\User;
use SmartLicenseServer\Security\Context\AbstractIdentityProvider;
use SmartLicenseServer\Security\Context\ContextServiceProvider;
use SmartLicenseServer\Security\Context\Guard;
use SmartLicenseServer\Security\Context\Principal;
use SmartLicenseServer\Security\Owner;
use SmartLicenseServer\Security\Permission\DefaultRoles;
use SmartLicenseServer\Security\Permission\Role;
use WP_Error;
use WP_User;

final class IdentityService extends AbstractIdentityProvider {
    
    /*
    |------------------------------------------
    | PROVIDER IDENTITY
    |------------------------------------------
    */

    /**
     * Get provider ID.
     *
     * @return string
     */
    protected function issuer() : string {
        $adapter = \smliser_settings();

        $inst_id = $adapter->get( 'installation_uuid', false, true );

        if ( empty( $inst_id ) ) {
            $inst_id = \smliser_generate_uuid_v4();
            $adapter->set( 'installation_uuid', $inst_id, true );
        }

        return 'urn:smliser:wordpress:' . $inst_id;
    }

    /*
    |------------------------------------------
    | FEDERATION & SYNCHRONIZATION
    |------------------------------------------
    */

    /**
     * Get the resource owner this actor is associated with.
     * 
     * @return Owner|null
     */
    protected function get_actor_owner() : ?Owner {
        $session_token = wp_get_session_token();

        if ( ! $session_token ) {
            return null;
        }

        $transient_key = md5( $this->issuer() . '_' . $session_token );
        $owner_id = get_transient( $transient_key );

        if ( $owner_id ) {
            return Owner::get_by_id( (int) $owner_id );
        }

        return null;
    }

    /**
     * Add a Wordpress user to Smart License Server using the federation feature.
     * 
     * @param int $wp_user_id The WordPress user ID.
     * @param int|User $user ID or an instance of @see `\SmartLicenseServer\Security\Actors\User`
     * @return bool
     */
    protected function sync_user( int $wp_user_id, User|int $user ) : bool {
        $user = is_int( $user ) ? User::get_by_id( $user ) : $user;

        if ( ! $user ) {
            return false;
        }

        return $this->add( $user->get_id(), $this->issuer(), $wp_user_id );
    }

    /**
     * Remove a WordPress user from Smart License Server.
     * 
     * This method is called when a WordPress user is deleted to keep the federation table clean.
     * 
     * @param int $wp_user_id The WordPress user ID.
     * @return void
     */
    public static function desync_user( int $wp_user_id ) : void {
        $static = new static;
        $user = $static->find_actor( $static->issuer(), (string) $wp_user_id );
        if ( $user ) {
            $static->remove( $user->get_id() );

            /** @disregard */
            $user->set_status( User::STATUS_DELETE_SCHEDULED )->save();
        }
    }

    /*
    |------------------------------------------
    | WORDPRESS USER MANAGEMENT
    |------------------------------------------
    */

    /**
     * Set the current WordPress user.
     * 
     * @param WP_User $wp_user
     * @param bool $remember
     * @return WP_User
     */
    protected function set_current_user( WP_User $wp_user, bool $remember = false ) : WP_User {
        wp_set_auth_cookie( $wp_user->ID, $remember );
        wp_set_current_user( $wp_user->ID, $wp_user->user_login );
        
        do_action( 'wp_login', $wp_user->user_login, $wp_user );
        
        $this->authenticate();

        return $wp_user;
    }

    /**
     * Create a WP_User.
     * 
     * @param array{
     *      email: string
     *      password: string,
     *      username?: string,
     *      display_name?: string,
     *   
     * } $userdata
     * @return RequestException|WP_User
     */
    private function create_wp_user( array $userdata ) : RequestException|WP_User {
        $email = $userdata['email'];
        $pwd = $userdata['password'];
        $username = $userdata['username'] ?? '';
        $display_name = $userdata['display_name'] ?? '';

        if ( \email_exists( $email ) ) {
            return new RequestException( 'email_exists' );
        }

        $username = $username ?: sanitize_user( current( explode( '@', $email ) ), true );

        if ( \username_exists( $username ) ) {
            do {
                $username .= \wp_generate_password( 4, false );
            } while( \username_exists( $username ) );
        }

        $userdata = [
            'user_login'    => $username,
            'user_pass'     => $pwd,
            'user_email'    => $email,
            'display_name'  => $display_name,
        ];

        $wp_user_id = wp_insert_user( $userdata );

        if ( $wp_user_id instanceof WP_Error ) {
            return new RequestException(
                $wp_user_id->get_error_code(),
                $wp_user_id->get_error_message(),
                ['status' => 401 ]
            );
        }

        $wp_user = get_user( $wp_user_id );

        if ( ! $wp_user ) {
            return new RequestException(
                'user_not_created',
                'Unable to create WordPress user, try again.'
            );
        }

        return $wp_user;
    }

    /*
    |------------------------------------------
    | AUTO-PROVISIONING
    |------------------------------------------
    */

    /**
     * Automatically federate WordPress administrators into Smart License Server.
     * 
     * Silently fails on errors.
     *
     * @return void
     */
    public function auto_provision() : void {
        if ( ! \is_user_logged_in() || ! \is_super_admin() ) {
            return;
        }

        if ( Guard::has_principal() ) {
            return;
        }

        $wp_user = wp_get_current_user();
        $issuer = $this->issuer();
        $wp_user_id = (string) $wp_user->ID;
        $actor = $this->find_actor( $issuer, $wp_user_id );

        if ( $actor ) {
            return;
        }

        $email = $wp_user->user_email;
        $smliser_user = User::get_by_email( $email );

        if ( $smliser_user ) {
            $this->sync_user( $wp_user_id, $smliser_user );
            return;
        }

        $user = new User;
        $password_hash = password_hash( wp_generate_password(), PASSWORD_ARGON2ID );
        $user->set_display_name( $wp_user->display_name )
            ->set_password_hash( $password_hash )
            ->set_status( User::STATUS_ACTIVE )
            ->set_email( $email );

        if ( ! $user->save() ) {
            return;
        }
        
        $default_role = DefaultRoles::get( 'system_admin' );
        $caps = $default_role['capabilities'];
        $role_slug = $default_role['slug'];
        $role_label = $default_role['label'];
        
        $role = Role::get_by_slug( $role_slug );

        if ( ! $role ) {
            $role = new Role();
        }

        $role->set_label( $role_label)
            ->set_slug( $role_slug )
            ->set_capabilities( $caps )
            ->set_is_canonical( $default_role['is_canonical'] );
        try {
            $owner = ContextServiceProvider::get_default_owner( $user );
            if ( ! $owner ) {
                $owner = new Owner;
            }
            
            if ( ! $owner->exists() ) {
                $owner->set_name( $user->get_display_name() )
                    ->set_status( Owner::STATUS_ACTIVE )
                    ->set_subject_id( $user->get_id() )
                    ->set_type( Owner::TYPE_INDIVIDUAL );
                $owner->save();
            }
            
            $owner_subject = ContextServiceProvider::get_owner_subject( $owner );
            ContextServiceProvider::save_actor_role( $user, $role, $owner_subject );

        } catch ( \Throwable $th ) {
            return;
        }

        $this->sync_user( $wp_user_id, $user );
    }

    /*
    |------------------------------------------
    | AUTHENTICATION
    |------------------------------------------
    */

    /**
     * Authenticates and sets the principal.
     * 
     * @return Principal|null 
     */
    public function authenticate() : ?Principal {
        if ( ! is_user_logged_in() ) {
            return null;
        }

        if ( Guard::has_principal() ) {
            return Guard::get_principal();
        }

        $wp_user = wp_get_current_user();
        $issuer = $this->issuer();
        $wp_user_id = (string) $wp_user->ID;

        $actor = $this->find_actor( $issuer, $wp_user_id );

        if ( ! $actor ) {
            return null;
        }
        
        $owner = $this->get_actor_owner();

        if ( ! $owner || ! $owner->exists() ) {
            $owner = ContextServiceProvider::get_default_owner( $actor );
        }

        $owner_subject = $owner ? ContextServiceProvider::get_owner_subject( $owner ) : null;
        $role = ContextServiceProvider::get_principal_role( $actor, $owner_subject );

        if ( ! $role || ! $role->exists() ) {
            return null;
        }

        $principal = new Principal( $actor, $role, $owner );

        Guard::set_principal( $principal );

        return $principal;
    }

    /**
     * Logon using credentials.
     * 
     * {@inheritdoc}
     *
     * @param string $email
     * @param string $pwd
     * @param bool $remember
     * @return RequestException|Principal
     */
    public function logon( string $email, #[\SensitiveParameter] string $pwd, bool $remember = false ): RequestException|Principal {
        $login = $this->wp_user_logon( $email, $pwd, $remember );

        if ( $login instanceof RequestException ) {
            if ( 'invalid_user' !== $login->get_error_code() ) {
                return $login;
            }

            $login = $this->smliser_user_logon( $email, $pwd, $remember );

            if ( $login instanceof RequestException ) {
                return $login;
            }
        }

        $principal = Guard::get_principal();

        if ( ! $principal ) {
            return new RequestException( 'authentication_error', 'Unable to set current user.' );
        }

        return $principal;
    }

    /**
     * Logon a WP_User.
     * 
     * @param string $email
     * @param string $pwd
     * @param bool $remember
     * @return RequestException|WP_User
     */
    public function wp_user_logon( string $email, #[\SensitiveParameter] string $pwd, bool $remember = false ) : RequestException|WP_User {
        $wp_user = \wp_signon(
            [
                'user_login' => $email,
                'user_password' => $pwd,
                'remember' => $remember

            ],
            true
        );

        if ( $wp_user instanceof WP_Error ) {
            $code = $wp_user->get_error_code();

            if ( 'invalid_email' === $code ) {
                $code = 'invalid_user';
            }

            $message = \strip_tags( $wp_user->get_error_message() );

            if ( 'incorrect_password' === $code ) {
                $message = 'The password you entered is incorrect.';
            }

            return new RequestException( $code, $message );
        }

        return $this->set_current_user( $wp_user, $remember );
    }

    /**
     * Directly logon a \SmartLicenseServer\Security\Actors\User and auto federate.
     * 
     * @param string $email
     * @param string $pwd
     * @param bool $remember
     * @return RequestException|WP_User
     */
    public function smliser_user_logon( string $email, #[\SensitiveParameter] string $pwd, bool $remember = false ) : RequestException|WP_User {
        if ( ! User::email_exists( $email ) ) {
            return new RequestException(
                'invalid_user',
                'Unknown email address.',
                ['status' => 401]
            );
        }

        /** @var \SmartLicenseServer\Security\Actors\User $user */
        $user = User::get_by_email( $email );

        if ( ! password_verify( $pwd, $user->get_password_hash() ) ) {
            return new RequestException( 'incorrect_password' );
        }

        if ( ! \email_exists( $user->get_email() ) ) {
            $wp_user = $this->create_wp_user( [
                'email' => $email,
                'password' => $pwd,
                'display_name' => $user->get_display_name()
            ] );

            if ( $wp_user instanceof RequestException ) {
                return $wp_user;
            }

        } else {
            $wp_user = \get_user_by( 'email', $user->get_email() );

            if ( ! $wp_user ) {
                return new RequestException(
                    'invalid_user',
                    'Unknown email address.'
                );
            }
        }

        if ( ! $this->find_actor( $this->issuer(), $wp_user->ID ) ) {
            $this->sync_user( $wp_user->ID, $user );
        }

        return $this->set_current_user( $wp_user, $remember );
    }

    /*
    |------------------------------------------
    | SIGNUP & REGISTRATION
    |------------------------------------------
    */

    /**
     * Signup a user.
     * 
     * {@inheritdoc}
     *
     * @param Request $request
     * @return RequestException|Principal
     */
    public function signup( Request $request ): RequestException|Principal {
        if ( $request->isEmpty( 'email' ) ) {
            return new RequestException(
                'required_param',
                'Email is required.',
                ['status' => 400]    
            );
        }

        $email = $request->get( 'email' );

        if ( ! filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
            return new RequestException(
                'invalid_email',
                'The email address is invalid.',
                ['status' => 400]    
            );
        }

        $fingerprint = md5( $this->issuer() . '_' . $email );
        $cache_key = 'signup_lock_' . $fingerprint;

        if ( \smliser_cache()->has( $cache_key ) ) {
            return new RequestException(
                'signup_locked',
                'Too many signup attempts, please try again later.',
                ['status' => 429 ]
            );
        }

        \smliser_cache()->set( $cache_key, time(), 300 );

        try {
            if ( $request->isEmpty( 'password_1' ) ) {
                throw new RequestException(
                    'required_param',
                    'Password is required.'
                );
            }

            $password_1 = $_POST['password_1'];
            $password_2 = $_POST['password_2'] ?? '';

            if ( $password_1 !== $password_2 ) {
                throw new RequestException(
                    'password_mismatch',
                    'Passwords do not match.'
                );
            }

            $display_name = $request->get( 'full_name', '' );
            $wp_user = $this->create_wp_user([
                'email'         => $email,
                'password'      => $password_1,
                'display_name'  => $display_name
            ]);

            if ( $wp_user instanceof RequestException ) {
                throw $wp_user;
            }

            if ( ! User::email_exists( $email ) ) {
                $user = new User;
                $password_hash = password_hash( $password_1, PASSWORD_ARGON2ID );
                $user->set_display_name( $display_name ?: $wp_user->display_name )
                    ->set_password_hash( $password_hash )
                    ->set_status( User::STATUS_ACTIVE )
                    ->set_email( $email );

                if ( ! $user->save() ) {
                    throw new RequestException(
                        'user_save_error',
                        'Unable to save user, try again.',
                        ['status' => 500 ]    
                    );
                }
            } else {
                $user = User::get_by_email( $email );
            }

            $account_type = $request->get( 'account_type', 'viewer' );

            if ( 'resource_owner' !== $account_type ) {
                $account_type = 'viewer';
            }

            $role = Role::get_by_slug( $account_type );

            if ( ! $role || ! $role->exists() ) {
                throw new RequestException(
                    'invalid_account_type',
                    'The selected account type is invalid.',
                    ['status' => 400 ]    
                );
            }

            ContextServiceProvider::save_actor_role( $user, $role );

            $this->sync_user( $wp_user->ID, $user );
            $this->set_current_user( $wp_user, true );

            if ( ! Guard::has_principal() ) {
                throw new RequestException(
                    'authentication_error',
                    'Unable to set current user.'
                );
            }

            return Guard::get_principal();

        } catch( RequestException $e ) {
            return $e;
        } catch ( \Throwable ) {
            return new RequestException(
                'signup_error',
                'An error occurred during signup, please try again.',
                ['status' => 500 ]
            );
        } finally {
            \smliser_cache()->delete( $cache_key );
        }
    }

    /*
    |------------------------------------------
    | PASSWORD MANAGEMENT
    |------------------------------------------
    */

    /**
     * Perform password reset for a user identified by email.
     * 
     * {@inheritdoc}
     *
     * @param User $user
     * @param string $new_pwd
     * @return bool
     */
    public function reset_password( User $user, string $new_pwd ): bool {
        if ( '' === $new_pwd ) {
            throw new SecurityException(
                'empty_password',
                'New password cannot be empty.'
            );
        }

        $password_hash = password_hash( $new_pwd, \PASSWORD_ARGON2ID );

        if ( ! $user->set_password_hash( $password_hash )->save() ) {
            throw new SecurityException(
                'password_save_error',
                'Unable to set new password',
                ['status' => 500 ]    
            );
        }

        $wp_user_id = $this->find_external_id( $this->issuer(), $user->get_id() );

        if ( $wp_user_id ) {
            wp_set_password( $new_pwd, (int) $wp_user_id );
        }

        return true;
    }

    /*
    |------------------------------------------
    | LOGOUT
    |------------------------------------------
    */

    /**
     * Logout the current user.
     * 
     * {@inheritdoc}
     *
     * @return void
     */
    public function logout() : void {
        wp_logout();
        Guard::clear_principal();
    }
}