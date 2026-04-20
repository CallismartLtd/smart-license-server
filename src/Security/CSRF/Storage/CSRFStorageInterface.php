<?php
/**
 * CSRF Token Storage Backends
 *
 * Provides interface and multiple implementations for CSRF token persistence:
 *
 *   - SessionStorage  — PHP $_SESSION (simplest, in-memory)
 *   - DatabaseStorage — Custom database table (persistent)
 *   - CustomStorage   — User-defined callback-based storage
 *
 * @package SmartLicenseServer\Security
 * @since 0.2.0
 */

namespace SmartLicenseServer\Security\CSRF\Storage;

/**
 * CSRF Storage Interface
 *
 * Defines contract for CSRF token storage backends.
 */
interface CSRFStorage {

    /**
     * Store a token
     *
     * @param string $session_id Unique session/user identifier
     * @param string $token      Token value
     *
     * @return bool True on success
     */
    public function set( $session_id, $token );

    /**
     * Retrieve a token
     *
     * @param string $session_id Unique session/user identifier
     *
     * @return string|null Token value or null if not found
     */
    public function get( $session_id );

    /**
     * Remove/invalidate a token
     *
     * @param string $session_id Unique session/user identifier
     *
     * @return bool True on success
     */
    public function remove( $session_id );

    /**
     * Check if a token exists
     *
     * @param string $session_id Unique session/user identifier
     *
     * @return bool True if token exists
     */
    public function exists( $session_id );
}

/**
 * Database Storage
 *
 * Stores CSRF tokens in a custom database table.
 * Requires custom database setup and callback functions.
 *
 * Use when:
 *   - Multi-server deployment (shared storage)
 *   - Need persistent token history
 *   - Want audit trail of token usage
 *
 * Required database table structure:
 *
 *   CREATE TABLE csrf_tokens (
 *       id INT PRIMARY KEY AUTO_INCREMENT,
 *       session_id VARCHAR(255) UNIQUE NOT NULL,
 *       token VARCHAR(255) NOT NULL,
 *       created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
 *   );
 *
 *   CREATE INDEX idx_session_id ON csrf_tokens(session_id);
 */
class DatabaseStorage implements CSRFStorage {

    /**
     * Callback to execute SET query
     *
     * @var callable
     */
    private $set_callback;

    /**
     * Callback to execute GET query
     *
     * @var callable
     */
    private $get_callback;

    /**
     * Callback to execute REMOVE query
     *
     * @var callable
     */
    private $remove_callback;

    /**
     * Callback to check EXISTS
     *
     * @var callable
     */
    private $exists_callback;

    /**
     * Constructor
     *
     * Requires four callbacks for database operations.
     *
     * @param callable $set_callback    function( $session_id, $token ) : bool
     * @param callable $get_callback    function( $session_id ) : string|null
     * @param callable $remove_callback function( $session_id ) : bool
     * @param callable $exists_callback function( $session_id ) : bool
     */
    public function __construct(
        callable $set_callback,
        callable $get_callback,
        callable $remove_callback,
        callable $exists_callback
    ) {
        $this->set_callback = $set_callback;
        $this->get_callback = $get_callback;
        $this->remove_callback = $remove_callback;
        $this->exists_callback = $exists_callback;
    }

    /**
     * Store a token
     */
    public function set( $session_id, $token ) {
        return call_user_func( $this->set_callback, $session_id, $token );
    }

    /**
     * Retrieve a token
     */
    public function get( $session_id ) {
        return call_user_func( $this->get_callback, $session_id );
    }

    /**
     * Remove a token
     */
    public function remove( $session_id ) {
        return call_user_func( $this->remove_callback, $session_id );
    }

    /**
     * Check if token exists
     */
    public function exists( $session_id ) {
        return call_user_func( $this->exists_callback, $session_id );
    }
}

/**
 * Custom Storage
 *
 * Array-based in-memory storage with optional persistence callback.
 * Useful for testing or custom implementations.
 *
 * Use when:
 *   - Testing/mocking
 *   - Simple key-value storage needed
 *   - Custom persistence logic
 */
class CustomStorage implements CSRFStorage {

    /**
     * In-memory token storage
     *
     * @var array
     */
    private $tokens = array();

    /**
     * Optional persistence callback
     *
     * @var callable|null
     */
    private $persist_callback;

    /**
     * Constructor
     *
     * @param callable|null $persist_callback Optional callback( $tokens ) for persistence
     */
    public function __construct( callable $persist_callback = null ) {
        $this->persist_callback = $persist_callback;
    }

    /**
     * Store a token
     */
    public function set( $session_id, $token ) {
        $this->tokens[ $session_id ] = $token;
        $this->persist();
        return true;
    }

    /**
     * Retrieve a token
     */
    public function get( $session_id ) {
        return $this->tokens[ $session_id ] ?? null;
    }

    /**
     * Remove a token
     */
    public function remove( $session_id ) {
        unset( $this->tokens[ $session_id ] );
        $this->persist();
        return true;
    }

    /**
     * Check if token exists
     */
    public function exists( $session_id ) {
        return isset( $this->tokens[ $session_id ] );
    }

    /**
     * Call persistence callback if set
     *
     * @return void
     */
    private function persist() {
        if ( is_callable( $this->persist_callback ) ) {
            call_user_func( $this->persist_callback, $this->tokens );
        }
    }

    /**
     * Get all tokens (for testing)
     *
     * @return array
     */
    public function all() {
        return $this->tokens;
    }

    /**
     * Clear all tokens (for testing)
     *
     * @return void
     */
    public function clear() {
        $this->tokens = array();
        $this->persist();
    }
}