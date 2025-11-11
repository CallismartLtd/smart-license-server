<?php
/**
 * Portable URL Object Class
 *
 * Provides a rich, object-oriented, and portable API for parsing and manipulating URLs,
 * including complete query parameter management and origin extraction.
 *
 * @package SmartLicenseServer\Core
 * @author  Callistus
 * @since   1.1.0
 */

namespace SmartLicenseServer\Core;

defined( 'ABSPATH' ) || exit;

/**
 * A portable URL object with chainable methods for URL manipulation.
 */
class URL {

    /**
     * URL components parsed by parse_url().
     *
     * @var array
     */
    protected $components = [];

    /**
     * Initialize from a URL string.
     *
     * @param string $url The URL to parse.
     */
    public function __construct( string $url ) {
        $this->components = $this->parse_components( $url );
    }

    /**
     * Parse URL into components.
     *
     * @param string $url The URL string.
     * @return array
     */
    protected function parse_components( string $url ): array {
        $components = parse_url( trim( $url ) );
        return $components ? $components : [];
    }

    /**
     * Get the full URL string.
     *
     * @return string
     */
    public function get_href(): string {
        return self::build_url( $this->components );
    }

    /**
     * Get the scheme + domain + optional port (the origin).
     *
     * @return string|null
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
     * Get the domain (host only).
     *
     * @return string|null
     */
    public function get_domain(): ?string {
        return $this->components['host'] ?? null;
    }

    /**
     * Get a specific component.
     *
     * @param string $key Component key (scheme, host, path, query, etc.).
     * @return string|null
     */
    public function get( string $key ): ?string {
        return $this->components[ $key ] ?? null;
    }

    /**
     * Get URL path.
     *
     * @return string|null
     */
    public function get_path(): ?string {
        return $this->get( 'path' );
    }

    /**
     * Get fragment (hash).
     *
     * @return string|null
     */
    public function get_hash(): ?string {
        return $this->get( 'fragment' );
    }

    /**
     * Get query parameters as array.
     *
     * @return array
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
     * Get a specific query parameter.
     *
     * @param string $key     The parameter name.
     * @param mixed  $default Default value if not found.
     * @return mixed
     */
    public function get_query_param( string $key, $default = null ) {
        $params = $this->get_query_params();
        return $params[ $key ] ?? $default;
    }

    /**
     * Check if a query parameter exists.
     *
     * @param string $key Parameter name.
     * @return bool
     */
    public function has_query_param( string $key ): bool {
        $params = $this->get_query_params();
        return array_key_exists( $key, $params );
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

        return $this->update_query( $params );
    }

    /**
     * Add or update multiple query parameters.
     *
     * @param array $params Associative array of parameters.
     * @return self
     */
    public function add_query_params( array $params ): self {
        $merged = array_merge( $this->get_query_params(), $params );
        return $this->update_query( $merged );
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

        return $this->update_query( $params );
    }

    /**
     * Replace the full query string.
     *
     * @param array $params Query parameters.
     * @return self
     */
    protected function update_query( array $params ): self {
        $clone = clone $this;
        $clone->components['query'] = http_build_query( $params );
        return $clone;
    }

    /**
     * Set a new path.
     *
     * @param string $path The new path.
     * @return self
     */
    public function set_path( string $path ): self {
        $clone = clone $this;
        $clone->components['path'] = '/' . ltrim( $path, '/' );
        return $clone;
    }

    /**
     * Set a new hash (fragment).
     *
     * @param string $hash The new fragment (with or without '#').
     * @return self
     */
    public function set_hash( string $hash ): self {
        $clone = clone $this;
        $clone->components['fragment'] = ltrim( $hash, '#' );
        return $clone;
    }

    /**
     * Cast object to string (returns full URL).
     *
     * @return string
     */
    public function __toString(): string {
        return $this->get_href();
    }

    /**
     * Helper to rebuild a URL string from components.
     *
     * @param array $c URL components.
     * @return string
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
