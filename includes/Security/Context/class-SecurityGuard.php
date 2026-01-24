<?php
/**
 * The Security Guard class file.
 * @author Callistus Nwachukwu
 */

namespace SmartLicenseServer\Security\Context;

defined( 'SMLISER_ABSPATH' ) || exit;

use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Security\Actors\ActorInterface;
use SmartLicenseServer\Security\Owner;

/**
 * Orchestrates the transition from an authenticated Actor to a contextual Principal.
 * The Guard is responsible for resolving the relationship between the Actor 
 * and the specific Resource Owner targeted by a Request.
 */
class SecurityGuard {

    /**
     * Attempts to initialize a Principal for a given Actor and Request.
     * @param ActorInterface $actor   The authenticated internal user.
     * @param Request        $request The current request object.
     * @return Principal|null Returns the Principal if the context is valid, null otherwise.
     */
    public function discover_principal( ActorInterface $actor, Request $request ) : ?Principal {

        $owner = $this->resolve_owner( $request );
        
        if ( ! $owner ) {
            return null;
        }

        $role = ContextServiceProvider::get_principal_role( $owner, $actor );

        if ( ! $role ) {
            return null;
        }

        // Return the locked-in Principal.
        return new Principal( $actor, $role, $owner );
    }

    /**
     * Resolves the Resource Owner based on request properties.
     * @param Request $request
     * @return Owner|null
     */
    private function resolve_owner( Request $request ) : ?Owner {
        
        if ( $request->has( 'app_id' ) ) {
            // Placeholder: Application-specific owner resolution
            // return HostedApp::get_by_id( $request->app_id )?->get_owner();
        }

        // if ( $request->get( 'context' ) === 'system' ) {
        //     return Owner::get_system_owner();
        // }

        return null;
    }
}