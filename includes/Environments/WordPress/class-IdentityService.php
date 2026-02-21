<?php
/**
 * WordPress identity service provider.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Environments\WordPress
 */

namespace SmartLicenseServer\Environments\WordPress;

use SmartLicenseServer\Security\Actors\User;
use SmartLicenseServer\Security\Context\AbstractIdentityProvider;
use SmartLicenseServer\Security\Context\ContextServiceProvider;
use SmartLicenseServer\Security\Context\Guard;
use SmartLicenseServer\Security\Context\Principal;
use SmartLicenseServer\Security\Owner;

final class IdentityService extends AbstractIdentityProvider {

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
     * Get provider ID.
     *
     * @return string
     */
    protected function issuer() : string {

        $adapter = \smliser_settings_adapter();

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

        $transient_key  = sprintf( '%s_%s', md5( $this->issuer() ), $session_token );
        $owner_id       = get_transient( $transient_key );

        if ( $owner_id ) {
            return Owner::get_by_id( $owner_id );
        }

        return null;
    }

    /**
     * Add a Wordpress user to Smart License Server using the federation feature.
     * 
     * @param int       $id     The WordPress user ID.
     * @param int|User  $user   ID or an instance of @see `\SmartLicenseServer\Security\Actors\User`
     */
    public function sync_user( int $id, User|int $user ) : bool {
        $user   = is_int( $user ) ? User::get_by_id( $user ) : $user;

        if ( ! $user ) {
            return false;
        }

        return $this->add( $user->get_id(), $this->issuer(), $id );
    }
}