<?php
/**
 * HTTP Request Value Object.
 *
 * Immutable representation of an outgoing HTTP request.
 * Constructed once and passed to any HttpAdapterInterface implementation.
 *
 * @package SmartLicenseServer\Http
 * @since 0.2.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Http;

use InvalidArgumentException;

defined( 'SMLISER_ABSPATH' ) || exit;

class HttpRequest {

    public const METHOD_GET    = 'GET';
    public const METHOD_POST   = 'POST';
    public const METHOD_PUT    = 'PUT';
    public const METHOD_PATCH  = 'PATCH';
    public const METHOD_DELETE = 'DELETE';

    protected const SUPPORTED_METHODS = [
        self::METHOD_GET,
        self::METHOD_POST,
        self::METHOD_PUT,
        self::METHOD_PATCH,
        self::METHOD_DELETE,
    ];

    protected const DEFAULT_TIMEOUT      = 30;
    protected const DEFAULT_MAX_REDIRECTS = 5;

    /**
     * @param string               $method          HTTP method.
     * @param string               $url             Full request URL.
     * @param array<string,string> $headers         Request headers keyed by header name.
     * @param string               $body            Raw request body.
     * @param int                  $timeout         Request timeout in seconds.
     * @param bool                 $verify_ssl      Whether to verify SSL certificates.
     * @param int                  $max_redirects   Maximum number of redirects to follow.
     * @param array<string,string> $cookies         Cookies keyed by name.
     * @param string|null          $sink            Absolute path to file name where response is written.
     */
    public function __construct(
        public readonly string  $method,
        public readonly string  $url,
        public readonly array   $headers       = [],
        public readonly string  $body          = '',
        public readonly int     $timeout       = self::DEFAULT_TIMEOUT,
        public readonly bool    $verify_ssl    = true,
        public readonly int     $max_redirects = self::DEFAULT_MAX_REDIRECTS,
        public readonly array   $cookies       = [],
        public readonly ?string $sink          = null,
    ) {
        $this->assert_valid_method( $method );
        $this->assert_valid_url( $url );
    }

    /*
    |------------------
    | NAMED CONSTRUCTORS
    |------------------
    */

    /**
     * Create a GET request.
     *
     * @param string               $url
     * @param array<string,string> $headers
     * @param array<string,mixed>  $options
     * @return static
     */
    public static function get( string $url, array $headers = [], array $options = [] ): static {
        return static::make( self::METHOD_GET, $url, $headers, '', $options );
    }

    /**
     * Create a POST request.
     *
     * @param string               $url
     * @param string               $body
     * @param array<string,string> $headers
     * @param array<string,mixed>  $options
     * @return static
     */
    public static function post( string $url, string $body = '', array $headers = [], array $options = [] ): static {
        return static::make( self::METHOD_POST, $url, $headers, $body, $options );
    }

    /**
     * Create a PUT request.
     *
     * @param string               $url
     * @param string               $body
     * @param array<string,string> $headers
     * @param array<string,mixed>  $options
     * @return static
     */
    public static function put( string $url, string $body = '', array $headers = [], array $options = [] ): static {
        return static::make( self::METHOD_PUT, $url, $headers, $body, $options );
    }

    /**
     * Create a PATCH request.
     *
     * @param string               $url
     * @param string               $body
     * @param array<string,string> $headers
     * @param array<string,mixed>  $options
     * @return static
     */
    public static function patch( string $url, string $body = '', array $headers = [], array $options = [] ): static {
        return static::make( self::METHOD_PATCH, $url, $headers, $body, $options );
    }

    /**
     * Create a DELETE request.
     *
     * @param string               $url
     * @param array<string,string> $headers
     * @param array<string,mixed>  $options
     * @return static
     */
    public static function delete( string $url, array $headers = [], array $options = [] ): static {
        return static::make( self::METHOD_DELETE, $url, $headers, '', $options );
    }

    /*
    |----------
    | WITHERS
    |----------
    */

    /**
     * Return a copy with an additional or replaced header.
     *
     * @param string $name
     * @param string $value
     * @return static
     */
    public function with_header( string $name, string $value ): static {
        $headers          = $this->headers;
        $headers[ $name ] = $value;
        return new static( $this->method, $this->url, $headers, $this->body,
            $this->timeout, $this->verify_ssl, $this->max_redirects, $this->cookies );
    }

    /**
     * Return a copy with a JSON body and Content-Type header set automatically.
     *
     * @param array<string, mixed> $data
     * @return static
     */
    public function with_json( array $data ): static {
        $headers                   = $this->headers;
        $headers['Content-Type']   = 'application/json';
        $headers['Accept']         = 'application/json';
        return new static( $this->method, $this->url, $headers, json_encode( $data ),
            $this->timeout, $this->verify_ssl, $this->max_redirects, $this->cookies );
    }

    /**
     * Return a copy with SSL verification disabled.
     *
     * Should only be used in development or testing environments.
     *
     * @return static
     */
    public function without_ssl_verification(): static {
        return new static( $this->method, $this->url, $this->headers, $this->body,
            $this->timeout, false, $this->max_redirects, $this->cookies );
    }

    /**
     * Return a copy with an additional cookie.
     *
     * @param string $name
     * @param string $value
     * @return static
     */
    public function with_cookie( string $name, string $value ): static {
        $cookies          = $this->cookies;
        $cookies[ $name ] = $value;
        return new static( $this->method, $this->url, $this->headers, $this->body,
            $this->timeout, $this->verify_ssl, $this->max_redirects, $cookies );
    }

    /**
     * Return a copy of the request that streams the response body to a file.
     *
     * When a sink path is set, adapters MUST write the response body
     * directly to this file rather than returning it in HttpResponse::$body.
     * HttpResponse::$body will be an empty string and HttpResponse::$sink_path
     * will carry the destination path instead.
     *
     * The directory must already exist and be writable. The file is created
     * (or truncated) by the adapter at download time.
     *
     * Example:
     *   $request = HttpRequest::get( $url )->with_sink( '/tmp/plugin.zip' );
     *   $response = $client->send( $request );
     *   if ( $response->is_success() ) {
     *       // file is at /tmp/plugin.zip
     *   }
     *
     * @param  string $path  Absolute path to the destination file.
     * @return static
     * @throws \InvalidArgumentException If the directory does not exist or is not writable.
     */
    public function with_sink( string $path ): static {
        $dir = dirname( $path );

        if ( ! is_dir( $dir ) ) {
            throw new \InvalidArgumentException(
                "HttpRequest::with_sink() — directory does not exist: '{$dir}'."
            );
        }

        if ( ! is_writable( $dir ) ) {
            throw new \InvalidArgumentException(
                "HttpRequest::with_sink() — directory is not writable: '{$dir}'."
            );
        }

        return new static(
            method        : $this->method,
            url           : $this->url,
            headers       : $this->headers,
            body          : $this->body,
            timeout       : $this->timeout,
            verify_ssl    : $this->verify_ssl,
            max_redirects : $this->max_redirects,
            cookies       : $this->cookies,
            sink          : $path,
        );
    }

    /**
     * Return a copy of the request with multiple options applied.
     *
     * @param array<string, mixed> $options Supported keys:
     *                                      timeout, verify_ssl,
     *                                      max_redirects, cookies, sink
     * @return static
     */
    public function with_options( array $options ): static {
        return new static(
            method        : $this->method,
            url           : $this->url,
            headers       : $this->headers,
            body          : $this->body,
            timeout       : isset( $options['timeout'] )
                ? (int) $options['timeout']
                : $this->timeout,
            verify_ssl    : isset( $options['verify_ssl'] )
                ? (bool) $options['verify_ssl']
                : $this->verify_ssl,
            max_redirects : isset( $options['max_redirects'] )
                ? (int) $options['max_redirects']
                : $this->max_redirects,
            cookies       : isset( $options['cookies'] )
                ? (array) $options['cookies']
                : $this->cookies,
            sink          : array_key_exists( 'sink', $options )
                ? ( $options['sink'] !== null ? (string) $options['sink'] : null )
                : $this->sink,
        );
    }


    /*
    |----------
    | HELPERS
    |----------
    */

    /**
     * Return the Cookie header string built from the cookies array.
     *
     * @return string
     */
    public function get_cookie_header(): string {
        if ( empty( $this->cookies ) ) {
            return '';
        }

        return implode( '; ', array_map(
            fn( $name, $value ) => "{$name}={$value}",
            array_keys( $this->cookies ),
            $this->cookies
        ) );
    }

    /**
     * Return whether the request has a body.
     *
     * @return bool
     */
    public function has_body(): bool {
        return $this->body !== '';
    }

    /*
    |------------
    | VALIDATION
    |------------
    */

    /**
     * @throws InvalidArgumentException
     */
    protected function assert_valid_method( string $method ): void {
        if ( ! in_array( $method, self::SUPPORTED_METHODS, true ) ) {
            throw new InvalidArgumentException(
                "HttpRequest: unsupported method '{$method}'. "
                . 'Supported: ' . implode( ', ', self::SUPPORTED_METHODS ) . '.'
            );
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    protected function assert_valid_url( string $url ): void {
        if ( empty( $url ) || ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
            throw new InvalidArgumentException(
                "HttpRequest: '{$url}' is not a valid URL."
            );
        }
    }

    /**
     * Whether this request should stream its response body to a file.
     *
     * Adapters use this to decide between memory-buffered and
     * file-streamed response handling.
     *
     * @return bool
     */
    public function has_sink(): bool {
        return $this->sink !== null;
    }

    /*
    |---------
    | FACTORY
    |---------
    */

    /**
     * Internal factory used by all named constructors.
     *
     * @param string               $method
     * @param string               $url
     * @param array<string,string> $headers
     * @param string               $body
     * @param array<string,mixed>  $options  Supported keys: timeout, verify_ssl, max_redirects, cookies.
     * @return static
     */
    protected static function make(
        string $method,
        string $url,
        array  $headers,
        string $body,
        array  $options
    ): static {
        return new static(
            method        : $method,
            url           : $url,
            headers       : $headers,
            body          : $body,
            timeout       : (int)    ( $options['timeout']       ?? self::DEFAULT_TIMEOUT ),
            verify_ssl    : (bool)   ( $options['verify_ssl']    ?? true ),
            max_redirects : (int)    ( $options['max_redirects'] ?? self::DEFAULT_MAX_REDIRECTS ),
            cookies       : (array)  ( $options['cookies']       ?? [] ),
            sink          : isset( $options['sink'] ) ? (string) $options['sink'] : null,
        );
    }
}