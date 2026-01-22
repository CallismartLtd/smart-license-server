<?php
/**
 * The principal object class.
 * 
 * @author Callistus Nwachukwu.
 * @package SmartLicenseServer\Security
 */

namespace SmartLicenseServer\Security;

use function defined;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Canonical represensation of the currently logged in actor.
 * 
 * This class should be instantiated early in the request lifecycle.
 */
final class Principal {
    /**
     * The currently logged in actor object.
     * 
     * @var PrincipalInterface $principal
     */
    protected static ?PrincipalInterface $principal = null;

    /**
     * Class constructor
     * 
     * @param PrincipalInterface $principal
     */
    public function __construct( PrincipalInterface $principal ) {
        self::$principal    = $principal;
    }

    /**
     * Proxy method calls to the underlying principal.
     *
     * @param string $method Method name.
     * @param array  $args   Method arguments.
     * @return mixed
     *
     * @throws \BadMethodCallException If method does not exist on the principal.
     */
    public function __call( string $method, array $args ) {
        if ( method_exists( self::$principal, $method ) ) {
            return call_user_func_array( [ self::$principal, $method ], $args );
        }

        throw new \BadMethodCallException(
            sprintf( 'Method %s::%s does not exist.', get_class( self::$principal ), $method )
        );
    }
}