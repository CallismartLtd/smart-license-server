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
use SmartLicenseServer\Security\Actors\User;
use SmartLicenseServer\Security\Context\AbstractIdentityProvider;
use SmartLicenseServer\Security\Context\ContextServiceProvider;
use SmartLicenseServer\Security\Context\Guard;
use SmartLicenseServer\Security\Context\Principal;
use SmartLicenseServer\Security\Owner;
use SmartLicenseServer\Security\Permission\DefaultRoles;
use SmartLicenseServer\Security\Permission\Role;

final class IdentityService extends AbstractIdentityProvider {
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

    /**
     * Get the resource owner this actor is associated with.
     * 
     * @return Owner|null
     */
    protected function get_actor_owner() : ?Owner {
        $session_token    = wp_get_session_token();

        if ( ! $session_token ) {
            return null;
        }

        $transient_key  = md5( $this->issuer() . '_' . $session_token );
        $owner_id       = get_transient( $transient_key );

        if ( $owner_id ) {
            return Owner::get_by_id( (int) $owner_id );
        }

        return null;
    }

    /**
     * Add a Wordpress user to Smart License Server using the federation feature.
     * 
     * @param int       $wp_user_id     The WordPress user ID.
     * @param int|User  $user   ID or an instance of @see `\SmartLicenseServer\Security\Actors\User`
     */
    protected function sync_user( int $wp_user_id, User|int $user ) : bool {
        $user   = is_int( $user ) ? User::get_by_id( $user ) : $user;

        if ( ! $user ) {
            return false;
        }

        return $this->add( $user->get_id(), $this->issuer(), $wp_user_id );
    }

    /*
    |-----------------
    | PUBLIC METHODS
    |-----------------
    */

    /**
     * Auto automatically federate WordPress administrators into Smart License Server.
     * 
     * Silently fails on errors.
     */
    public function auto_provision() : void {
        if ( ! \is_user_logged_in() || ! \is_super_admin() ) {
            return;
        }

        if ( Guard::has_principal() ) {
            // Already has access.
            return;
        }

        $wp_user    = wp_get_current_user();
        $issuer     = $this->issuer();
        $wp_user_id      = (string) $wp_user->ID;
        $actor      = $this->find_actor( $issuer, $wp_user_id );

        if ( $actor ) {
            // Already federated.
            return;
        }

        $email          = $wp_user->user_email;
        $smliser_user   = User::get_by_email( $email );

        if ( $smliser_user ) {
            // An account exists for this admin, federate it.
            $this->sync_user( $wp_user_id, $smliser_user );
            return;
        }

        // We are creating a new User.
        $user           = new User;
        $password_hash  = password_hash( wp_generate_password(), PASSWORD_ARGON2ID );
        $user->set_display_name( $wp_user->display_name )
            ->set_password_hash( $password_hash )
            ->set_status( User::STATUS_ACTIVE )
            ->set_email( $email );

        if ( ! $user->save() ) {
            return;
        }
        
        $default_role   = DefaultRoles::get( 'system_admin' );
        $caps           = $default_role['capabilities'];
        $role_slug      = $default_role['slug'];
        $role_label     = $default_role['label'];
        
        $role           = Role::get_by_slug( $role_slug );

        if ( ! $role ) {
            $role   = new Role();
        }

        $role->set_label( $role_label)
            ->set_slug( $role_slug )
            ->set_capabilities( $caps )
            ->set_is_canonical( $default_role['is_canonical'] );
        try {
            $owner  = ContextServiceProvider::get_default_owner( $user );
            if ( ! $owner ) {
                $owner  = new Owner;
            }
            
            if ( ! $owner->exists() ) {
                $owner->set_name( $user->get_display_name() )
                    ->set_status( Owner::STATUS_ACTIVE )
                    ->set_subject_id( $user->get_id() )
                    ->set_type( Owner::TYPE_INDIVIDUAL );
                $owner->save();
            }
            
            // User will act for self for now.
            $owner_subject  = ContextServiceProvider::get_owner_subject( $owner );
            ContextServiceProvider::save_actor_role( $user, $role, $owner_subject );

        } catch ( \Throwable $th ) {
            return;
        }

        // Sync to federation table.
        $this->sync_user( $wp_user_id, $user );
    }

    public function authenticate() : ?Principal {

        if ( ! is_user_logged_in() ) {
            return null;
        }

        if ( Guard::has_principal() ) {
            return Guard::get_principal();
        }

        $wp_user      = wp_get_current_user();
        $issuer       = $this->issuer();
        $external_id  = (string) $wp_user->ID;

        $actor = $this->find_actor( $issuer, $external_id );

        if ( ! $actor ) {
            // The logged WordPress user is yet to be federated into
            // Smart License Server.
            return null;
        }
        
        $owner  = $this->get_actor_owner();

        if ( ! $owner || ! $owner->exists() ) {
            // Actor has not yet switched ownership context.
            $owner = ContextServiceProvider::get_default_owner( $actor );
        }

        // No owner yet?
        if ( ! $owner ) {
            // A user must be a resource owner to act for self.
            return null;
        }

        // Owner subject can be an organization or the user.
        $owner_subject  = ContextServiceProvider::get_owner_subject( $owner );
        $role           = ContextServiceProvider::get_principal_role( $actor, $owner_subject );

        // Users without roles cannot act.
        if ( ! $role ) {
            return null;
        }

        $principal = new Principal( $actor, $role, $owner );

        Guard::set_principal( $principal );

        return $principal;
    }

    /**
     * {@inheritdoc}
     * 
     * Logon using credentials
     */
    public function logon( string $email, string $pwd, bool $remember = false ): RequestException|Principal {
        $wp_user    = \wp_signon(
            [
                'user_login'    => $email,
                'user_password' => $pwd,
                'remember'      => $remember

            ],
            true
        );

        if ( $wp_user instanceof \WP_Error ) {

            if ( \email_exists( $email ) ) {
                return new RequestException(
                    $wp_user->get_error_code(),
                    $wp_user->get_error_message()
                );
            }

        }

        $this->authenticate();

        $principal  = Guard::get_principal();

        if ( ! $principal ) {
            return new RequestException( 'authentication_error', 'Unable to set current user.' );
        }

        return $principal;

    }

    public function signup( Request $request ): RequestException|Principal {
        throw new \Exception('Not implemented');
    }

    public function forgot_password( $email ): void {
        throw new \Exception('Not implemented');
    }
}