<?php
/**
 * Security aware trait file.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Security
 */

namespace SmartLicenseServer\Security;

use SmartLicenseServer\Exceptions\RequestException;
use SmartLicenseServer\HostedApps\HostedAppsInterface;
use SmartLicenseServer\Security\Context\Guard;

/**
 * Shared security check trait.
 */
trait SecurityAwareTrait {
    /**
     * Check minimum required permissions.
     * 
     * @param string[] $perms
     * @throws RequestException When actor is unauthenticated.
     */
    private static function check_permissions( ...$perms ) {
        $principal      = Guard::get_principal();

        if ( ! $principal ) {
            throw new RequestException( 'missing_auth' );
        }

        if ( ! empty( $perms ) && ! $principal->can_any( $perms ) ) {
            throw new RequestException( 'unauthorized_scope' );
        }
    }

    /**
     * Tells whether the current principal owns or has permission to work on the app.
     * 
     * @param HostedAppsInterface $app.
     * @throws RequestException On failure.
     */
    private static function check_app_ownership( HostedAppsInterface $app ) : void {
        if ( ! $app->exists() || ! $app->get_owner_id() ) {
            return;
        }

        $principal      = Guard::get_principal();

        if ( ! $principal ) {
            throw new RequestException( 'missing_auth' );
        }

        if ( ! $principal->get_owner()->owns_app( $app ) ) {
            throw new RequestException( 'unuathorized_app_access' );
        }
    }

    /**
     * Tells whether the principal is a system administrator.
     * 
     * @throws RequestException On failure.
     */
    private static function is_system_admin() {
        $principal      = Guard::get_principal();

        if ( ! $principal ) {
            throw new RequestException( 'missing_auth' );
        }

        if ( ! $principal->is( 'system_admin' ) ) {
            throw new RequestException( 'unauthorized_scope' );
        }
    }
}