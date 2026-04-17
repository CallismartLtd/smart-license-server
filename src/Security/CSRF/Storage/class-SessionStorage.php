<?php
/**
 * SessionStorage class file.
 * 
 * @author Callistus Nwchukwu
 * @package SmartLicenseServer\Security
 * @since 0.2.0
 */
declare( strict_types = 1 );

namespace SmartLicenseServer\Security\CSRF\Storage;

/**
 * PHP Session Storage
 *
 * Stores CSRF tokens in PHP's $_SESSION superglobal.
 * Simplest implementation, no external dependencies.
 *
 * Use when:
 *   - Single-server deployment
 *   - Session data is shared via PHP native sessions
 *   - No persistent storage needed
 */
class SessionStorage implements CSRFStorage {

    /**
     * Session key prefix for storing tokens
     *
     * @var string
     */
    private $prefix = '_csrf_tokens_';

    /**
     * Constructor
     *
     * @param string $prefix Optional key prefix (default: '_csrf_tokens_')
     */
    public function __construct( $prefix = '' ) {
        $this->prefix = $prefix ? $prefix : $this->prefix;

        // Ensure session is started
        if ( session_status() === PHP_SESSION_NONE ) {
            session_start();
        }

        // Initialize tokens array if not exists
        if ( ! isset( $_SESSION[ $this->prefix ] ) ) {
            $_SESSION[ $this->prefix ] = array();
        }
    }

    /**
     * Store a token
     */
    public function set( $session_id, $token ) {
        $_SESSION[ $this->prefix ][ $session_id ] = $token;
        return true;
    }

    /**
     * Retrieve a token
     */
    public function get( $session_id ) {
        return $_SESSION[ $this->prefix ][ $session_id ] ?? null;
    }

    /**
     * Remove a token
     */
    public function remove( $session_id ) {
        unset( $_SESSION[ $this->prefix ][ $session_id ] );
        return true;
    }

    /**
     * Check if token exists
     */
    public function exists( $session_id ) {
        return isset( $_SESSION[ $this->prefix ][ $session_id ] );
    }
}