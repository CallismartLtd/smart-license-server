<?php
/**
 * The Security Guard class file.
 * @author Callistus Nwachukwu
 */

namespace SmartLicenseServer\Security\Context;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Orchestrates the transition from an authenticated Actor to a contextual Principal.
 * The Guard is responsible for resolving the relationship between the Actor 
 * and the specific Resource Owner targeted by a Request.
 */
final class SecurityGuard {

    /**
     * The current principal instance for this request.
     * @var Principal|null
     */
    private static ?Principal $current_principal = null;

    /**
     * Set the current principal.
     *
     * @param Principal|null $principal
     * @return void
     */
    public static function set_principal( ?Principal $principal ) : void {
        self::$current_principal = $principal;
    }

    /**
     * Get the current principal.
     *
     * @return Principal|null
     */
    public static function get_principal() : ?Principal {
        return self::$current_principal;
    }

    /**
     * Check if a principal is currently set.
     *
     * @return bool
     */
    public static function has_principal() : bool {
        return isset( self::$current_principal );
    }

    /**
     * Clear the principal (useful for testing or ending request).
     *
     * @return void
     */
    public static function clear_principal() : void {
        self::$current_principal = null;
    }

}
