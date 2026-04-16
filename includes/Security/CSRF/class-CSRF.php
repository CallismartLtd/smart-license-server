<?php
/**
 * Portable CSRF Token Manager
 *
 * A flexible, environment-agnostic CSRF protection class that works across
 * multiple contexts (forms, API endpoints, custom URLs) with:
 *
 *   - Single-use tokens (invalidated after verification)
 *   - Token rotation (old token invalidated when new one generated)
 *   - Multiple output formats (form field, header, URL param)
 *   - Custom token storage backends
 *   - No expiration (session-based lifecycle only)
 *
 * Usage:
 *
 *   // Initialize
 *   $csrf = new CSRF( $storage );
 *
 *   // Generate token
 *   $token = $csrf->generate();
 *
 *   // Output in different contexts
 *   echo $csrf->field();           // <input type="hidden" ...>
 *   echo $csrf->header();          // X-CSRF-Token: ...
 *   $url = $csrf->addToUrl( $url ); // ?_token=...
 *
 *   // Verify token
 *   if ( $csrf->verify( $_POST['_token'] ) ) {
 *       // Safe to proceed
 *   }
 *
 * @package SmartLicenseServer
 * @since 1.0.0
 */

namespace SmartLicenseServer\Security;

use SmartLicenseServer\Security\CSRF\Storage\CSRFStorage;

/**
 * CSRF Token Manager
 *
 * Handles generation, storage, rotation, and verification of CSRF tokens
 * across multiple contexts.
 */
class CSRF {

    /**
     * Token field name (used in forms, headers, URL params)
     *
     * @var string
     */
    private $field_name = '_token';

    /**
     * Header name for API requests
     *
     * @var string
     */
    private $header_name = 'X-CSRF-Token';

    /**
     * Token storage backend
     *
     * @var CSRFStorage
     */
    private $storage;

    /**
     * Current session/user identifier
     *
     * @var string|int
     */
    private $session_id;

    /**
     * Constructor
     *
     * @param CSRFStorage $storage    Storage backend for tokens
     * @param string      $session_id Unique session/user identifier (defaults to session ID)
     * @param string      $field_name Custom field name (default: '_token')
     * @param string      $header_name Custom header name (default: 'X-CSRF-Token')
     */
    public function __construct(
        CSRFStorage $storage,
        $session_id = null,
        $field_name = '_token',
        $header_name = 'X-CSRF-Token'
    ) {
        $this->storage = $storage;
        $this->session_id = $session_id ?? $this->getDefaultSessionId();
        $this->field_name = $field_name;
        $this->header_name = $header_name;
    }

    /**
     * Generate a new CSRF token
     *
     * - Invalidates the previous token (rotation)
     * - Stores the new token in the backend
     * - Returns the token string
     *
     * @return string New CSRF token
     */
    public function generate() {

        // Invalidate old token (rotation)
        $this->storage->remove( $this->session_id );

        // Generate new token
        $token = $this->createToken();

        // Store new token
        $this->storage->set( $this->session_id, $token );

        return $token;
    }

    /**
     * Get the current token without generating a new one
     *
     * If no token exists, generates one.
     *
     * @return string Current CSRF token
     */
    public function get() {

        $token = $this->storage->get( $this->session_id );

        if ( empty( $token ) ) {
            $token = $this->generate();
        }

        return $token;
    }

    /**
     * Verify a CSRF token
     *
     * - Checks if token matches the stored token for this session
     * - Invalidates the token immediately (single-use)
     * - Returns true only if token is valid and not already used
     *
     * @param string $token Token to verify
     *
     * @return bool True if token is valid, false otherwise
     */
    public function verify( $token ) {

        // Token is required
        if ( empty( $token ) ) {
            return false;
        }

        // Get stored token
        $stored = $this->storage->get( $this->session_id );

        if ( empty( $stored ) ) {
            return false;
        }

        // Verify token matches (constant-time comparison)
        $is_valid = hash_equals( (string) $stored, (string) $token );

        if ( $is_valid ) {
            // Invalidate token (single-use)
            $this->storage->remove( $this->session_id );
        }

        return $is_valid;
    }

    /**
     * Output token as hidden form field
     *
     * @param string $id    Optional input ID attribute
     * @param array  $attrs Optional additional attributes
     *
     * @return string HTML hidden input element
     */
    public function field( $id = '', $attrs = array() ) {

        $token = $this->get();

        $id_attr = ! empty( $id )
            ? sprintf( ' id="%s"', esc_attr( $id ) )
            : '';

        $extra_attrs = '';
        if ( ! empty( $attrs ) ) {
            foreach ( $attrs as $key => $value ) {
                $extra_attrs .= sprintf( ' %s="%s"', esc_attr( $key ), esc_attr( $value ) );
            }
        }

        return sprintf(
            '<input type="hidden" name="%s" value="%s"%s%s />',
            esc_attr( $this->field_name ),
            esc_attr( $token ),
            $id_attr,
            $extra_attrs
        );
    }

    /**
     * Output token as HTTP header value
     *
     * Useful for JavaScript/AJAX requests.
     *
     * @return string Header value (token only, no "Header: " prefix)
     */
    public function header() {
        return $this->get();
    }

    /**
     * Output HTTP header line suitable for headers or meta tags
     *
     * @return string Full header line (e.g., "X-CSRF-Token: abc123")
     */
    public function headerLine() {
        return sprintf(
            '%s: %s',
            $this->header_name,
            $this->get()
        );
    }

    /**
     * Output meta tag for JavaScript access
     *
     * JavaScript can read token via: document.querySelector('meta[name="csrf-token"]').content
     *
     * @param string $meta_name Optional meta name (default: 'csrf-token')
     *
     * @return string HTML meta element
     */
    public function meta( $meta_name = 'csrf-token' ) {
        return sprintf(
            '<meta name="%s" content="%s" />',
            esc_attr( $meta_name ),
            esc_attr( $this->get() )
        );
    }

    /**
     * Add token to URL as query parameter
     *
     * @param string $url URL to add token to
     *
     * @return string URL with token parameter appended
     */
    public function addToUrl( $url ) {

        $token = $this->get();

        $separator = strpos( $url, '?' ) === false ? '?' : '&';

        return $url . $separator . http_build_query( array(
            $this->field_name => $token,
        ) );
    }

    /**
     * Get token from request
     *
     * Checks multiple sources in order:
     *   1. POST/GET parameter
     *   2. HTTP header
     *   3. Custom callback
     *
     * @param callable $custom_getter Optional callback to retrieve token from custom source
     *
     * @return string|null Token if found, null otherwise
     */
    public function getFromRequest( callable $custom_getter ) {

        // Check POST parameter
        if ( ! empty( $_POST[ $this->field_name ] ) ) {
            return $_POST[ $this->field_name ];
        }

        // Check GET parameter (for URL-based tokens)
        if ( ! empty( $_GET[ $this->field_name ] ) ) {
            return $_GET[ $this->field_name ];
        }

        // Check HTTP header
        $header = $this->getHeader( $this->header_name );
        if ( ! empty( $header ) ) {
            return $header;
        }

        // Check custom source
        return $custom_getter();
    }

    /**
     * Verify token from current request
     *
     * Convenience method that retrieves token from request and verifies it.
     *
     * @param callable $custom_getter Optional callback for custom source
     *
     * @return bool True if token is valid, false otherwise
     */
    public function verifyFromRequest( callable $custom_getter ) {

        $token = $this->getFromRequest( $custom_getter );

        return $this->verify( $token );
    }

    /**
     * Get field name
     *
     * @return string
     */
    public function getFieldName() {
        return $this->field_name;
    }

    /**
     * Get header name
     *
     * @return string
     */
    public function getHeaderName() {
        return $this->header_name;
    }

    /**
     * Get session ID
     *
     * @return string|int
     */
    public function getSessionId() {
        return $this->session_id;
    }

    /**
     * Set session ID (e.g., after switching users)
     *
     * @param string|int $session_id
     *
     * @return void
     */
    public function setSessionId( $session_id ) {
        $this->session_id = $session_id;
    }

    /**
     * Clear token for current session
     *
     * @return void
     */
    public function clear() {
        $this->storage->remove( $this->session_id );
    }

    /**
     * Create a cryptographically secure random token
     *
     * @return string
     */
    private function createToken() {
        return bin2hex( random_bytes( 32 ) );
    }

    /**
     * Get default session ID
     *
     * Tries to use PHP's session ID, falls back to a request-based hash.
     *
     * @return string
     */
    private function getDefaultSessionId() {

        // Use PHP session ID if available
        if ( ! empty( session_id() ) ) {
            return session_id();
        }

        // Fallback: Use IP + User-Agent hash
        $ip = $this->getClientIp();
        $user_agent = isset( $_SERVER['HTTP_USER_AGENT'] )
            ? $_SERVER['HTTP_USER_AGENT']
            : '';

        return hash( 'sha256', $ip . $user_agent );
    }

    /**
     * Get client IP address
     *
     * Handles proxied requests, X-Forwarded-For headers, etc.
     *
     * @return string Client IP address
     */
    private function getClientIp() {

        $ip = '';

        if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            // Handle multiple IPs in X-Forwarded-For
            $ips = explode( ',', $_SERVER['HTTP_X_FORWARDED_FOR'] );
            $ip = trim( $ips[0] );
        } elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        // Validate IP format
        if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
            $ip = '127.0.0.1';
        }

        return $ip;
    }

    /**
     * Get HTTP header value (server-agnostic)
     *
     * Works with Apache, Nginx, and CGI.
     *
     * @param string $header Header name (e.g., 'Content-Type')
     *
     * @return string|null Header value or null if not found
     */
    private function getHeader( $header ) {

        $header = strtoupper( str_replace( '-', '_', $header ) );

        // Try common header sources
        if ( ! empty( $_SERVER[ 'HTTP_' . $header ] ) ) {
            return $_SERVER[ 'HTTP_' . $header ];
        }

        if ( ! empty( $_SERVER[ $header ] ) ) {
            return $_SERVER[ $header ];
        }

        // Try PHP's getallheaders() if available
        if ( function_exists( 'getallheaders' ) ) {
            $headers = getallheaders();
            $header_name = strtolower( str_replace( '_', '-', $header ) );

            foreach ( $headers as $key => $value ) {
                if ( strtolower( $key ) === $header_name ) {
                    return $value;
                }
            }
        }

        return null;
    }
}