<?php
/**
 * Mutable URL Object Class
 *
 * Provides an object-oriented API for parsing and manipulating URLs,
 * including query parameter management, origin extraction, and validation.
 *
 * @package SmartLicenseServer\Core
 * @author  Callistus
 * @since   1.1.0
 */

namespace SmartLicenseServer\Core;

defined( 'SMLISER_ABSPATH' ) || exit;

class URL {

    /**
     * URL components parsed by parse_url().
     *
     * @var array
     */
    protected $components = [];

    /*----------------------------------------------------------
     * CONSTRUCTOR
     *---------------------------------------------------------*/

    /**
     * Initialize the URL object from a URL string.
     *
     * @param string $url The URL to parse.
     */
    public function __construct( string $url ) {
        $this->components = $this->parse_components( $url );
    }

    /*----------------------------------------------------------
     * PARSING
     *---------------------------------------------------------*/

    /**
     * Parse a URL string into components.
     *
     * Automatically prepends 'http://' if URL is localhost or 127.0.0.1
     * and no scheme is provided.
     *
     * @param string $url The URL string to parse.
     * @return array URL components (scheme, host, path, query, etc.)
     */
    protected function parse_components( string $url ): array {
        $url = trim( $url );

        if ( preg_match( '#^(localhost|127\.0\.0\.1)#', $url ) ) {
            $url = 'http://' . $url;
        }

        $components = parse_url( $url );
        return $components ?: [];
    }

    /*----------------------------------------------------------
     * GETTERS
     *---------------------------------------------------------*/

    /**
     * Get the full URL string (reconstructed from components).
     *
     * @return string Full URL.
     */
    public function get_href(): string {
        return self::build_url( $this->components );
    }

    /**
     * Get the scheme + host + optional port (origin).
     *
     * @return string|null Origin or null if host is missing.
     */
    public function get_origin(): ?string {
        if ( empty( $this->components['host'] ) ) {
            return null;
        }

        $scheme = $this->components['scheme'] ?? 'http';
        $origin = $scheme . '://' . $this->components['host'];

        if ( ! empty( $this->components['port'] ) ) {
            $origin .= ':' . $this->components['port'];
        }

        return $origin;
    }

    /**
     * Get the host/domain only.
     *
     * @return string|null Domain or null if not set.
     */
    public function get_host(): ?string {
        return $this->components['host'] ?? null;
    }

    /**
     * Get the URL scheme (http, https, ftp, etc.).
     *
     * @return string|null Scheme or null if not set.
     */
    public function get_scheme(): ?string {
        return $this->components['scheme'] ?? null;
    }

    /**
     * Get the URL path component.
     *
     * @return string|null Path or null if not set.
     */
    public function get_path(): ?string {
        return $this->components['path'] ?? null;
    }

    /**
     * Get the fragment/hash component (#fragment).
     *
     * @return string|null Fragment or null if not set.
     */
    public function get_hash(): ?string {
        return $this->components['fragment'] ?? null;
    }

    /**
     * Get all query parameters as an associative array.
     *
     * @return array Query parameters.
     */
    public function get_query_params(): array {
        $query = $this->components['query'] ?? '';
        if ( empty( $query ) ) {
            return [];
        }

        parse_str( $query, $params );
        return $params;
    }

    /**
     * Get a single query parameter by key.
     *
     * @param string $key     The query parameter name.
     * @param mixed  $default Default value if parameter is not found.
     * @return mixed Query parameter value or $default.
     */
    public function get_query_param( string $key, $default = null ) {
        $params = $this->get_query_params();
        return $params[ $key ] ?? $default;
    }

    /**
     * Check if a query parameter exists.
     *
     * @param string $key Query parameter name.
     * @return bool True if exists, false otherwise.
     */
    public function has_query_param( string $key ): bool {
        $params = $this->get_query_params();
        return array_key_exists( $key, $params );
    }

    /*----------------------------------------------------------
     * SETTERS / MUTATORS
     *---------------------------------------------------------*/

    /**
     * Set or replace the URL scheme.
     *
     * @param string $scheme URL scheme (http, https, ftp, etc.).
     * @return self
     */
    public function set_scheme( string $scheme ): self {
        $this->components['scheme'] = rtrim( strtolower( $scheme ), '://' );
        return $this;
    }

    /**
     * Set or replace the URL path.
     *
     * @param string $path New path.
     * @return self
     */
    public function set_path( string $path ): self {
        $this->components['path'] = '/' . ltrim( $path, '/' );
        return $this;
    }

    /**
     * Append the URL path
     * 
     * @param string $pathname
     * @return self
     */
    public function append_path( $pathname ) : self {
        $this->components['path'] .= sprintf( '/%s', ltrim( $pathname, '/' ) );

        return $this;
    }

    /**
     * Set or replace the URL fragment/hash.
     *
     * @param string $hash New fragment (with or without '#').
     * @return self
     */
    public function set_hash( string $hash ): self {
        $this->components['fragment'] = ltrim( $hash, '#' );
        return $this;
    }

    /**
     * Add or update a single query parameter.
     *
     * @param string $key   Parameter name.
     * @param mixed  $value Parameter value.
     * @return self
     */
    public function add_query_param( string $key, $value ): self {
        $params = $this->get_query_params();
        $params[ $key ] = $value;
        $this->components['query'] = http_build_query( $params );
        return $this;
    }

    /**
     * Add or update multiple query parameters at once.
     *
     * @param array $params Associative array of parameters.
     * @return self
     */
    public function add_query_params( array $params ): self {
        $merged = array_merge( $this->get_query_params(), $params );
        $this->components['query'] = http_build_query( $merged );
        return $this;
    }

    /**
     * Remove one or more query parameters.
     *
     * @param string|array $keys Parameter key(s) to remove.
     * @return self
     */
    public function remove_query_param( $keys ): self {
        $params = $this->get_query_params();
        foreach ( (array) $keys as $key ) {
            unset( $params[ $key ] );
        }
        $this->components['query'] = http_build_query( $params );
        return $this;
    }

    /*----------------------------------------------------------
    * VALIDATION
    *---------------------------------------------------------*/

    /**
     * Validate the URL.
     *
     * Performs a basic syntax check, and optionally checks if the host exists via DNS.
     *
     * @param bool $dns_check Whether to perform DNS lookup for the host. Default false.
     * @return bool True if valid (and host exists if $dns_check is true), false otherwise.
     */
    public function validate( bool $dns_check = false ): bool {
        $href = $this->get_href();

        // Basic URL syntax check
        if ( ! filter_var( $href, FILTER_VALIDATE_URL ) ) {
            return false;
        }

        // Optional DNS check
        if ( $dns_check ) {
            $host = $this->get_host();
            if ( empty( $host ) || ! checkdnsrr( $host, 'A' ) ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Tells wether the url is valid
     * 
     * @param bool $dns_check Whether to perform DNS lookup for the host. Default false.
     */
    public function is_valid( $dns_check = false ) : bool {
        return true === $this->validate( $dns_check );
    }

    /**
     * Check if the URL uses HTTPS scheme.
     *
     * @return bool True if URL scheme is https, false otherwise.
     */
    public function is_ssl(): bool {
        return strtolower( $this->get_scheme() ?? '' ) === 'https';
    }

    /**
     * Check if the host is localhost (127.0.0.1, ::1, or localhost).
     *
     * @return bool True if host is local, false otherwise.
     */
    public function is_localhost(): bool {
        $host = $this->get_host();
        return in_array( $host, ['localhost', '127.0.0.1', '::1'], true );
    }

    /**
     * Check if the URL uses a private IP address (RFC1918).
     *
     * @return bool True if host is a private IP, false otherwise.
     */
    public function is_private_ip(): bool {
        $host = $this->get_host();
        if ( empty( $host ) ) {
            return false;
        }

        if ( filter_var( $host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE ) === false ) {
            return true;
        }

        if ( filter_var( $host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) && strpos( $host, 'fc') === 0 ) {
            return true;
        }

        return false;
    }

    /**
     * Check whether the url has scheme
     */
    public function has_scheme() : bool {
        return isset( $this->components['scheme'] );
    }

    /**
     * Check if URL has a port explicitly defined.
     *
     * @return bool True if port is set, false otherwise.
     */
    public function has_port(): bool {
        return isset( $this->components['port'] );
    }

    /**
     * Check if the URL has query parameters.
     *
     * @return bool True if there is at least one query parameter, false otherwise.
     */
    public function has_query(): bool {
        $params = $this->get_query_params();
        return ! empty( $params );
    }

    /**
     * Check if the URL has a fragment/hash component.
     *
     * @return bool True if fragment is set, false otherwise.
     */
    public function has_fragment(): bool {
        return ! empty( $this->components['fragment'] );
    }

    /*----------------------------------------------------------
     * UTILITY
     *---------------------------------------------------------*/

    /**
     * Cast object to string, returning the full URL.
     *
     * @return string Full URL.
     */
    public function __toString(): string {
        return $this->get_href();
    }

    /**
     * Rebuild a URL string from parsed components.
     *
     * @param array $c URL components.
     * @return string Reconstructed URL.
     */
    protected static function build_url( array $c ): string {
        $url = '';

        if ( isset( $c['scheme'] ) ) {
            $url .= $c['scheme'] . '://';
        }

        if ( isset( $c['user'] ) ) {
            $url .= $c['user'];
            if ( isset( $c['pass'] ) ) {
                $url .= ':' . $c['pass'];
            }
            $url .= '@';
        }

        if ( isset( $c['host'] ) ) {
            $url .= $c['host'];
        }

        if ( isset( $c['port'] ) ) {
            $url .= ':' . $c['port'];
        }

        if ( isset( $c['path'] ) ) {
            $url .= $c['path'];
        }

        if ( isset( $c['query'] ) && $c['query'] !== '' ) {
            $url .= '?' . $c['query'];
        }

        if ( isset( $c['fragment'] ) && $c['fragment'] !== '' ) {
            $url .= '#' . $c['fragment'];
        }

        return $url;
    }
}
