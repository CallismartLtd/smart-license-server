<?php
/**
 * Immutable URL Object Class
 *
 * Provides an object-oriented API for parsing and manipulating URLs,
 * including query parameter management, origin extraction, and validation.
 *
 * Each mutator method returns a new URL instance, leaving the original
 * unchanged. This makes it safe to derive multiple URL variants from
 * the same base object without defensive cloning at the call site.
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
     * Private to enforce immutability — all mutations go through
     * the clone-and-return pattern in the mutator methods.
     *
     * @var array
     */
    private array $components = [];

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
    private function parse_components( string $url ): array {
        $url = trim( $url );

        if ( preg_match( '#^(localhost|127\.0\.0\.1)#', $url ) ) {
            $url = 'http://' . $url;
        }

        $components = parse_url( $url );
        return $components ?: [];
    }

    /**
     * Return a new instance with the given components array.
     *
     * Central helper used by all mutators to produce a new immutable
     * instance instead of modifying $this.
     *
     * @param array $components New components to apply.
     * @return self
     */
    private function with_components( array $components ): self {
        $clone             = clone $this;
        $clone->components = $components;
        return $clone;
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
     * Get the port number.
     *
     * @return int|null
     */
    public function get_port(): ?int {
        return $this->components['port'] ?? null;
    }

    /**
     * Get the URL username.
     *
     * @return string|null
     */
    public function get_user(): ?string {
        return $this->components['user'] ?? null;
    }

    /**
     * Get the URL password.
     *
     * @return string|null
     */
    public function get_pass(): ?string {
        return $this->components['pass'] ?? null;
    }

    /*----------------------------------------------------------
     * MUTATORS
     *
     * Every method here returns a NEW instance with the requested
     * change applied. The original instance is never modified.
     *---------------------------------------------------------*/

    /**
     * Return a new instance with the given scheme.
     *
     * @param string $scheme URL scheme (http, https, ftp, etc.).
     * @return self
     */
    public function set_scheme( string $scheme ): self {
        $components           = $this->components;
        $components['scheme'] = rtrim( strtolower( $scheme ), '://' );
        return $this->with_components( $components );
    }

    /**
     * Return a new instance with the given path.
     *
     * @param string $path New path.
     * @return self
     */
    public function set_path( string $path ): self {
        $components         = $this->components;
        $components['path'] = '/' . ltrim( $path, '/' );
        return $this->with_components( $components );
    }

    /**
     * Return a new instance with the given path segment appended.
     *
     * @param string $pathname Path segment to append.
     * @return self
     */
    public function append_path( string $pathname ): self {
        $components         = $this->components;
        $components['path'] = ( $components['path'] ?? '' ) . '/' . ltrim( $pathname, '/' );
        return $this->with_components( $components );
    }

    /**
     * Return a new instance with the given fragment/hash.
     *
     * @param string $hash New fragment (with or without '#').
     * @return self
     */
    public function set_hash( string $hash ): self {
        $components              = $this->components;
        $components['fragment']  = ltrim( $hash, '#' );
        return $this->with_components( $components );
    }

    /**
     * Return a new instance with the given query parameter added or updated.
     *
     * @param string $key   Parameter name.
     * @param mixed  $value Parameter value.
     * @return self
     */
    public function add_query_param( string $key, $value ): self {
        $params        = $this->get_query_params();
        $params[ $key] = $value;

        $components          = $this->components;
        $components['query'] = http_build_query( $params );
        return $this->with_components( $components );
    }

    /**
     * Return a new instance with the given query parameters merged in.
     *
     * @param array $params Associative array of parameters.
     * @return self
     */
    public function add_query_params( array $params ): self {
        $merged = array_merge( $this->get_query_params(), $params );

        $components          = $this->components;
        $components['query'] = http_build_query( $merged );
        return $this->with_components( $components );
    }

    /**
     * Return a new instance with the given port set.
     *
     * @param int $port Port number (1–65535).
     * @return self
     * @throws \InvalidArgumentException If the port is out of range.
     */
    public function set_port( int $port ): self {
        if ( $port < 1 || $port > 65535 ) {
            throw new \InvalidArgumentException( 'Invalid port number.' );
        }

        $components         = $this->components;
        $components['port'] = $port;
        return $this->with_components( $components );
    }

    /**
     * Return a new instance with the given username set.
     *
     * @param string $user Username.
     * @return self
     */
    public function set_user( string $user ): self {
        $components         = $this->components;
        $components['user'] = rawurlencode( $user );
        return $this->with_components( $components );
    }

    /**
     * Return a new instance with the given password set.
     *
     * @param string $pass Password.
     * @return self
     */
    public function set_pass( string $pass ): self {
        $components         = $this->components;
        $components['pass'] = rawurlencode( $pass );
        return $this->with_components( $components );
    }

    /**
     * Return a new instance with the given credentials set.
     *
     * @param string $user Username.
     * @param string $pass Password.
     * @return self
     */
    public function set_credentials( string $user, string $pass ): self {
        return $this->set_user( $user )->set_pass( $pass );
    }

    /**
     * Return a new instance with credentials removed.
     *
     * @return self
     */
    public function remove_credentials(): self {
        $components = $this->components;
        unset( $components['user'], $components['pass'] );
        return $this->with_components( $components );
    }

    /**
     * Return a new instance with the given query parameter(s) removed.
     *
     * @param string|array $keys Parameter key(s) to remove.
     * @return self
     */
    public function remove_query_param( ...$keys ): self {
        $params = $this->get_query_params();
        foreach ( (array) $keys as $key ) {
            unset( $params[ $key ] );
        }

        $components          = $this->components;
        $components['query'] = http_build_query( $params );
        return $this->with_components( $components );
    }

    /**
     * Return a new instance with the port removed.
     *
     * @return self
     */
    public function remove_port(): self {
        $components = $this->components;
        unset( $components['port'] );
        return $this->with_components( $components );
    }

    /**
     * Return a new instance with the username removed.
     *
     * @return self
     */
    public function remove_user(): self {
        $components = $this->components;
        unset( $components['user'] );
        return $this->with_components( $components );
    }

    /**
     * Return a new instance with the password removed.
     *
     * @return self
     */
    public function remove_pass(): self {
        $components = $this->components;
        unset( $components['pass'] );
        return $this->with_components( $components );
    }

    /*----------------------------------------------------------
     * VALIDATION
     *---------------------------------------------------------*/

    /**
     * Validate the URL.
     *
     * Performs a basic syntax check, and optionally checks if the host
     * exists via DNS.
     *
     * @param bool $dns_check Whether to perform DNS lookup for the host. Default false.
     * @return bool True if valid (and host exists if $dns_check is true), false otherwise.
     */
    public function validate( bool $dns_check = false ): bool {
        $href = $this->get_href();

        if ( ! filter_var( $href, FILTER_VALIDATE_URL ) ) {
            return false;
        }

        if ( $dns_check ) {
            $host = $this->get_host();
            if ( empty( $host ) || ! checkdnsrr( $host, 'A' ) ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check whether the URL is valid.
     *
     * @param bool $dns_check Whether to perform DNS lookup for the host. Default false.
     * @return bool
     */
    public function is_valid( bool $dns_check = false ): bool {
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
        return in_array( $host, [ 'localhost', '127.0.0.1', '::1' ], true );
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

        if ( filter_var( $host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) && strpos( $host, 'fc' ) === 0 ) {
            return true;
        }

        return false;
    }

    /**
     * Check whether the URL has a scheme.
     *
     * @return bool
     */
    public function has_scheme(): bool {
        return isset( $this->components['scheme'] );
    }

    /**
     * Check if the URL has a port explicitly defined.
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
        return ! empty( $this->get_query_params() );
    }

    /**
     * Check if the URL has a fragment/hash component.
     *
     * @return bool True if fragment is set, false otherwise.
     */
    public function has_fragment(): bool {
        return ! empty( $this->components['fragment'] );
    }

    /**
     * Check if a specific query parameter exists.
     *
     * @param string $key Query parameter name.
     * @return bool True if exists, false otherwise.
     */
    public function has_query_param( string $key ): bool {
        return array_key_exists( $key, $this->get_query_params() );
    }

    /**
     * Check if credentials are present.
     *
     * @return bool
     */
    public function has_credentials(): bool {
        return isset( $this->components['user'] );
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
    private static function build_url( array $c ): string {
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

    /**
     * Return a new sanitized instance.
     *
     * Cleans unsafe characters, normalizes casing, encodes query
     * parameters safely, and ensures structural consistency — without
     * altering the original instance.
     *
     * @return self
     */
    public function sanitize(): self {
        $c = $this->components;

        // Normalize scheme.
        if ( isset( $c['scheme'] ) ) {
            $c['scheme'] = strtolower(
                preg_replace( '/[^a-z0-9+\-.]/i', '', $c['scheme'] )
            );
        }

        // Normalize host.
        if ( isset( $c['host'] ) ) {
            $c['host'] = strtolower( trim( $c['host'] ) );
        }

        // Normalize path.
        if ( isset( $c['path'] ) ) {
            $path       = preg_replace( '#/+#', '/', $c['path'] );
            $path       = preg_replace( '/[\x00-\x1F\x7F]/u', '', $path );
            $c['path']  = $path === '' ? '/' : $path;
        }

        // Sanitize query parameters.
        if ( isset( $c['query'] ) && $c['query'] !== '' ) {
            parse_str( $c['query'], $params );

            $clean = [];
            foreach ( $params as $key => $value ) {
                if ( $key === '' ) {
                    continue;
                }

                $clean_key = preg_replace( '/[^\w\-\.]/', '', (string) $key );

                if ( is_array( $value ) ) {
                    $clean[ $clean_key ] = array_map( 'rawurlencode', $value );
                } else {
                    $clean[ $clean_key ] = rawurlencode( (string) $value );
                }
            }

            $c['query'] = http_build_query( $clean, '', '&', PHP_QUERY_RFC3986 );
        }

        // Normalize fragment.
        if ( isset( $c['fragment'] ) ) {
            $c['fragment'] = rawurlencode(
                preg_replace( '/[\x00-\x1F\x7F]/u', '', $c['fragment'] )
            );
        }

        // Remove invalid port.
        if ( isset( $c['port'] ) ) {
            $port = (int) $c['port'];
            if ( $port < 1 || $port > 65535 ) {
                unset( $c['port'] );
            }
        }

        return $this->with_components( $c );
    }

    /**
     * Get the basename of the URL path.
     *
     * @return string|null Basename or null if path is not set.
     */
    public function basename(): ?string {
        $path = $this->get_path();
        if ( $path === null ) {
            return null;
        }

        return basename( $path );
    }

    /*----------------------------------------------------------
     * DEBUG
     *---------------------------------------------------------*/

    /**
     * Dump all URL properties and their current state.
     *
     * Provides a comprehensive view of all URL components, validation
     * states, and computed properties for debugging and inspection.
     *
     * @return array
     */
    public function dump(): array {
        return [
            'url' => [
                'full_url' => $this->get_href(),
                'origin'   => $this->get_origin(),
            ],

            'components' => [
                'scheme'   => $this->get_scheme(),
                'host'     => $this->get_host(),
                'port'     => $this->get_port(),
                'user'     => $this->get_user(),
                'pass'     => $this->get_pass() ? '***REDACTED***' : null,
                'path'     => $this->get_path(),
                'query'    => $this->components['query'] ?? null,
                'fragment' => $this->get_hash(),
            ],

            'query_params' => $this->get_query_params(),

            'validation' => [
                'is_valid'      => $this->is_valid(),
                'is_valid_dns'  => $this->is_valid( true ),
                'is_ssl'        => $this->is_ssl(),
                'is_localhost'  => $this->is_localhost(),
                'is_private_ip' => $this->is_private_ip(),
            ],

            'has' => [
                'scheme'      => $this->has_scheme(),
                'port'        => $this->has_port(),
                'credentials' => $this->has_credentials(),
                'query'       => $this->has_query(),
                'fragment'    => $this->has_fragment(),
            ],

            'raw_components' => $this->components,
        ];
    }
}