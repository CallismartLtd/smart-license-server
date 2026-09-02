<?php
/**
 * The Smart License Server request class file.
 * 
 * @author Callistus Nwachukwu <admin@callismart.com.ng>
 */

namespace SmartLicenseServer\Core;

use SmartLicenseServer\Core\Parsers\HttpRequestParser;
use SmartLicenseServer\Utils\SanitizeAwareTrait;

/**
 * The classical representation of a request object that is understood by all core models.
 * 
 * An object of this class should be prepared by the environment adapter and passed to the core controller.
 */
class Request {
    use SanitizeAwareTrait;

    /**
     * HTTP DELETE method.
     * 
     * @var string
     */
    public const DELETE = 'DELETE';

    /**
     * HTTP GET method.
     * 
     * @var string
     */
    public const GET = 'GET';

    /**
     * HTTP HEAD method.
     * 
     * @var string
     */
    public const HEAD = 'HEAD';

    /**
     * HTTP OPTIONS method.
     * 
     * @var string
     */
    public const OPTIONS = 'OPTIONS';

    /**
     * HTTP PATCH method.
     * 
     * @var string
     */
    public const PATCH = 'PATCH';

    /**
     * HTTP POST method.
     * 
     * @var string
     */
    public const POST = 'POST';

    /**
     * HTTP PUT method.
     * 
     * @var string
     */
    public const PUT = 'PUT';

    /**
     * Internal storage for all parameters.
     * 
     * Both JSON, GET, and POST data are merged by default for backward compatibility.
     * 
     * @var array
     */
    private array $params = [];

    /**
     * Dedicated storage for query parameters ($_GET).
     * 
     * @var array
     */
    protected array $query = [];

    /**
     * Dedicated storage for POST form parameters ($_POST).
     * 
     * @var array
     */
    protected array $post = [];

    /**
     * Dedicated storage for parsed JSON payload.
     * 
     * @var array
     */
    protected array $json = [];

    /**
     * Dedicated storage for cookie parameters ($_COOKIE).
     * 
     * @var array
     */
    protected array $cookies = [];

    /**
     * Dedicated storage for server environment variables ($_SERVER).
     * 
     * @var array
     */
    protected array $server = [];

    /**
     * Dedicated storage for resolved routes parameters.
     * 
     * @var array
     */
    protected array $route_params = [];

    /**
     * Flag to track if raw JSON payload has been evaluated and cached.
     * 
     * @var bool
     */
    private bool $json_parsed = false;

    /**
     * Raw HTTP request body contents cache.
     * 
     * @var string|null
     */
    private ?string $raw_content = null;

    /**
     * Stores sanitized parameters.
     * 
     * @var array
     */
    private array $sanitized_params = [];

    /**
     * Holds the files uploaded.
     * 
     * @var array<string, UploadedFileCollection>
     */
    protected array $files = [];

    /**
     * Internal storage for all headers.
     * Keys are canonicalized (lowercase with underscores).
     * 
     * @var array
     */
    private array $headers = [];

    /**
     * Original header names for preserving case.
     * Maps canonical names to original names.
     * 
     * @var array
     */
    private array $original_header_names = [];

    /**
     * The HTTP method (GET, POST, PUT, DELETE, etc.).
     * 
     * @var string
     */
    private string $method;

    /**
     * The request URI.
     * 
     * @var string
     */
    private string $uri;

    /**
     * Tracks when the request object was instantiated.
     * 
     * @var float
     */
    private float $startTime = 0.0;

    /**
     * Flag to track multipart request parsing.
     * 
     * @var bool
     */
    protected bool $parsed_multipart = false;

    /**
     * Application debug state.
     * 
     * @var bool
     */
    protected bool $debug = APP_DEBUG;

    /**
     * Constructor.
     *
     * @param array  $params  The request params, defaults to merged superglobals.
     * @param array  $headers The request headers, defaults to all parsed headers.
     * @param string $method  The HTTP method, defaults to $_SERVER['REQUEST_METHOD'].
     * @param string $uri     The request URI, defaults to $_SERVER['REQUEST_URI'].
     */
    public function __construct( array $params = [], array $headers = [], string $method = '', string $uri = '' ) {
        $this->startTime = microtime( true );
        $this->parse_server();
        $this->parse_cookies();

        $this->method = ! empty( $method ) ? strtoupper( $method ) : ( $this->server['REQUEST_METHOD'] ?? 'GET' );
        $this->uri    = ! empty( $uri ) ? $uri : ( $this->server['REQUEST_URI'] ?? '/' );
        
        $this->set_headers( empty( $headers ) ? $this->parse_default_headers() : $headers );

        if ( in_array( $this->method(), [static::PATCH, static::PUT, static::DELETE], true ) ) {
            try {
                $parser = new HttpRequestParser(
                    method: $this->method,
                    content_type: $this->contentType(),
                    upload_tmp_prefix: SMLISER_UPLOAD_TMP_PREFIX,
                    debug: $this->debug,
                );

                $result = $parser->parse();
                $this->parse_post( $result['post'] );
                $this->parse_uploaded_files( $result['files'] );
            } catch ( \Exception ) {
                $this->parse_post();
                $this->parse_uploaded_files();
            }
        } else {
            $this->parse_post();
            $this->parse_uploaded_files();
        }
        
        $this->parse_query();
 
        if ( ! empty( $params ) ) {
            $this->set_params( $params );
        } else {
            $this->build_merged_params();
        }

        // $this->parse_uploaded_files();
    }

    /**
     * Create request instance using global environment arrays.
     *
     * @return static
     */
    public static function createFromGlobals(): static {
        return new static();
    }

    /*
    |--------------------------------------------------------------------------
    | DEDICATED PARAMETER BAG APIS
    |--------------------------------------------------------------------------
    */

    /**
     * Access query ($_GET) parameters.
     *
     * @param string|null $key     Parameter key or null to get all query parameters.
     * @param mixed       $default Default value if parameter key is missing.
     * @return mixed
     */
    public function query( ?string $key = null, mixed $default = null ): mixed {
        if ( null === $key ) {
            return $this->query;
        }

        return $this->query[ $key ] ?? $default;
    }

    /**
     * Access form post ($_POST) parameters.
     *
     * @param string|null $key     Parameter key or null to get all post parameters.
     * @param mixed       $default Default value if parameter key is missing.
     * @return mixed
     */
    public function post( ?string $key = null, mixed $default = null ): mixed {
        if ( null === $key ) {
            return $this->post;
        }

        return $this->post[ $key ] ?? $default;
    }

    /**
     * Access decoded JSON payload parameters.
     *
     * @param string|null $key     Parameter key or null to get all decoded JSON.
     * @param mixed       $default Default value if parameter key is missing.
     * @return mixed
     */
    public function json( ?string $key = null, mixed $default = null ): mixed {
        if ( ! $this->json_parsed ) {
            $this->parse_json();
        }

        if ( null === $key ) {
            return $this->json;
        }

        return $this->json[ $key ] ?? $default;
    }

    /**
     * Get resolved route parameter(s).
     * 
     * @param string|null $key     Parameter key or null to get all route parameters.
     * @param mixed  $default Default value if parameter key is missing.
     */
    public function route_param( ?string $key = null, mixed $default = null ): mixed {
        if ( null === $key ) {
            return $this->route_params;
        }

        return $this->route_params[ $key ] ?? $default;
    }

    /**
     * Set resolved route parameter(s).
     * 
     * @param string|array $key   Parameter key or associative array of parameters.
     */
    public function set_route_param( string|array $key, mixed $value = null ): static {
        if ( is_array( $key ) ) {
            foreach ( $key as $k => $v ) {
                $this->route_params[ $k ] = $v;
            }
        } else {
            $this->route_params[ $key ] = $value;
        }

        return $this;
    }

    /**
     * Access cookie ($_COOKIE) parameters.
     *
     * @param string|null $key     Parameter key or null to get all cookies.
     * @param mixed       $default Default value if parameter key is missing.
     * @return mixed
     */
    public function cookie( ?string $key = null, mixed $default = null ): mixed {
        if ( null === $key ) {
            return $this->cookies;
        }

        return $this->cookies[ $key ] ?? $default;
    }

    /**
     * Access server ($_SERVER) variables.
     *
     * @param string|null $key     Variable key or null to get all server variables.
     * @param mixed       $default Default value if variable key is missing.
     * @return mixed
     */
    public function server( ?string $key = null, mixed $default = null ): mixed {
        if ( null === $key ) {
            return $this->server;
        }

        return $this->server[ strtoupper( $key ) ] ?? $default;
    }

    /*
    |----------------------------------
    | LEGACY MERGED PARAMETER API
    |----------------------------------
    */

    /**
     * Set a parameter value.
     *
     * @param string $parameter Parameter name.
     * @param mixed  $value     Parameter value.
     * @return static For method chaining.
     */
    public function set( string $parameter, mixed $value ): static {
        $this->params[ $parameter ] = $value;
        unset( $this->sanitized_params[ $parameter ] );
        return $this;
    }

    /**
     * Set multiple parameters.
     * 
     * @param array $parameters Key-value parameter pairs.
     * @return static
     */
    public function set_params( array $parameters ): static {
        foreach ( $parameters as $key => $value ) {
            $this->set( $key, $value );
        }

        return $this;
    }

    /**
     * Get a parameter value from the merged input pool.
     *
     * @param string $parameter Parameter name.
     * @param mixed  $default   Optional default value if parameter is not set.
     * @param bool   $sanitize  Whether to automatically sanitize the value.
     * @return mixed
     */
    public function get( string $parameter, mixed $default = null, bool $sanitize = true ): mixed {
        if ( ! $sanitize ) {
            return $this->params[ $parameter ] ?? $default;
        }

        if ( array_key_exists( $parameter, $this->sanitized_params ) && null !== $this->sanitized_params[ $parameter ] ) {
            return $this->sanitized_params[ $parameter ];
        }

        if ( isset( $this->params[ $parameter ] ) ) {
            $this->sanitized_params[ $parameter ] = static::sanitize_auto( $this->params[ $parameter ] );
        }

        return $this->sanitized_params[ $parameter ] ?? $default;
    }

    /**
     * Get a parameter value as a specific type.
     * 
     * @param string $parameter Parameter name.
     * @param string $type      Type to cast to (string, int, float, bool, array).
     * @param mixed  $default   Optional default value if parameter is not set.
     * @return mixed
     */
    public function getTyped( string $parameter, string $type = 'string', mixed $default = null ): mixed {
        $value = $this->get( $parameter, $default, false );
        
        if ( $value === $default ) {
            return $value;
        }

        return match ( $type ) {
            'int', 'integer'   => (int) $value,
            'float', 'double'  => (float) $value,
            'bool', 'boolean'  => (bool) $value,
            'array'            => (array) $value,
            'string'           => (string) $value,
            default            => $value,
        };
    }

    /**
     * Get multiple parameters at once.
     * 
     * @param array $parameters Array of parameter names.
     * @param mixed $default    Default value for missing parameters.
     * @param bool  $sanitize   Whether to automatically sanitize parameter values.
     * @return array
     */
    public function getMany( array $parameters, mixed $default = null, bool $sanitize = true ): array {
        $result = [];
        foreach ( $parameters as $param ) {
            $result[ $param ] = $this->get( $param, $default, $sanitize );
        }
        return $result;
    }

    /**
     * Get only the specified parameters.
     * 
     * @param array $parameters Array of parameter names to include.
     * @return array
     */
    public function only( array $parameters ): array {
        return array_intersect_key( $this->params, array_flip( $parameters ) );
    }

    /**
     * Get all parameters except the specified ones.
     * 
     * @param array $parameters Array of parameter names to exclude.
     * @return array
     */
    public function except( array $parameters ): array {
        return array_diff_key( $this->params, array_flip( $parameters ) );
    }

    /**
     * Magic getter.
     * 
     * @param string $name Parameter name.
     * @return mixed
     */
    public function __get( string $name ) {
        return $this->get( $name, null, false );
    }

    /**
     * Magic setter.
     * 
     * @param string $name  Parameter name.
     * @param mixed  $value Parameter value.
     */
    public function __set( string $name, mixed $value ) {
        $this->set( $name, $value );
    }

    /**
     * Check if a parameter exists.
     *
     * @param string $parameter Parameter name.
     * @return bool
     */
    public function has( string $parameter ): bool {
        return array_key_exists( $parameter, $this->params );
    }

    /**
     * Tells whether the specified parameter exists and is empty.
     * 
     * @param string $parameter Parameter name.
     * @return bool
     */
    public function isEmpty( string $parameter ): bool {
        return empty( $this->get( $parameter, null, false ) );
    }

    /**
     * Tells whether the specified parameter exists and is not empty.
     * 
     * @param string $parameter Parameter name.
     * @return bool
     */
    public function hasValue( string $parameter ): bool {
        return ! $this->isEmpty( $parameter );
    }

    /**
     * Tells whether the specified properties are all present and not empty.
     * 
     * @param array $properties Parameter names.
     * @return bool
     */
    public function hasAll( array $properties ): bool {
        foreach ( $properties as $parameter ) {
            if ( $this->isEmpty( $parameter ) ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if any of the specified parameters are present and not empty.
     * 
     * @param array $properties Parameter names.
     * @return bool
     */
    public function hasAny( array $properties ): bool {
        foreach ( $properties as $parameter ) {
            if ( $this->hasValue( $parameter ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Tells whether the value of a parameter matches expected value.
     * 
     * @param string $param    Parameter key.
     * @param mixed  $expected Expected value.
     * @return bool
     */
    public function param_matches( string $param, mixed $expected ): bool {
        return $this->get( $param ) === $expected;
    }

    /**
     * Return all merged parameters as array.
     *
     * @return array
     */
    public function get_params(): array {
        return $this->params;
    }

    /**
     * Merge additional parameters into the request.
     * 
     * @param array $params Key-value parameter array to merge.
     * @return static
     */
    public function merge( array $params ): static {
        $this->params           = array_merge( $this->params, $params );
        $this->sanitized_params = [];
        return $this;
    }

    /**
     * Remove a parameter.
     * 
     * @param string $parameter Parameter key.
     * @return static
     */
    public function remove( string $parameter ): static {
        unset( $this->params[ $parameter ], $this->sanitized_params[ $parameter ] );
        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | HEADER MANAGEMENT
    |--------------------------------------------------------------------------
    */

    /**
     * Get a header value.
     * 
     * @param string $header  Header name (case-insensitive).
     * @param string $default Default value if header not found.
     * @return string
     */
    public function get_header( string $header, string $default = '' ): string {
        $canonical = $this->header_canonical( $header );
        
        if ( ! $this->has_header( $canonical ) ) {
            return $default;
        }

        return implode( ',', (array) $this->headers[ $canonical ] );
    }

    /**
     * Get all headers.
     * 
     * @return array
     */
    public function get_headers(): array {
        return $this->headers;
    }

    /**
     * Set a header value.
     * 
     * @param string $header Header name.
     * @param mixed  $value  Header value.
     * @return static
     */
    public function set_header( string $header, mixed $value ): static {
        $canonical                                 = $this->header_canonical( $header );
        $this->headers[ $canonical ]               = $value;
        $this->original_header_names[ $canonical ] = $header;
        return $this;
    }

    /**
     * Set headers.
     * 
     * @param array $headers Key-value array of headers.
     * @return static
     */
    public function set_headers( array $headers ): static {
        foreach ( $headers as $key => $value ) {
            $this->set_header( $key, $value );
        }

        return $this;
    }

    /**
     * Check if a header exists.
     * 
     * @param string $header Header name (case-insensitive).
     * @return bool
     */
    public function has_header( string $header ): bool {
        $canonical = $this->header_canonical( $header );
        return array_key_exists( $canonical, $this->headers );
    }

    /**
     * Standardize header keys internally.
     *
     * @param string $key Header key.
     * @return string Canonicalized key.
     */
    private function header_canonical( string $key ): string {
        return str_replace( '-', '_', strtolower( $key ) );
    }

    /*
    |--------------------------------------------------------------------------
    | HTTP METADATA & CONVENIENCE HELPERS
    |--------------------------------------------------------------------------
    */

    /**
     * Get the HTTP method.
     * 
     * @return string
     */
    public function method(): string {
        return $this->method;
    }

    /**
     * Check if the request method matches.
     * 
     * @param string $method Method name to compare.
     * @return bool
     */
    public function is_method( string $method ): bool {
        return strcasecmp( $this->method, $method ) === 0;
    }

    /**
     * Get the request URI.
     * 
     * @return string
     */
    public function uri(): string {
        return $this->uri;
    }

    /**
     * Get the request path (URI without query string).
     * 
     * @return string
     */
    public function path(): string {
        return parse_url( $this->uri, PHP_URL_PATH ) ?: '/';
    }

    /**
     * Check if request is GET.
     * 
     * @return bool
     */
    public function isGet(): bool {
        return static::GET === $this->method;
    }

    /**
     * Check if request is POST.
     * 
     * @return bool
     */
    public function isPost(): bool {
        return static::POST === $this->method;
    }

    /**
     * Check if request is PUT.
     * 
     * @return bool
     */
    public function isPut(): bool {
        return static::PUT === $this->method;
    }

    /**
     * Check if request is DELETE.
     * 
     * @return bool
     */
    public function isDelete(): bool {
        return static::DELETE === $this->method;
    }

    /**
     * Check if request is PATCH.
     * 
     * @return bool
     */
    public function isPatch(): bool {
        return static::PATCH === $this->method;
    }

    /**
     * Check if request is AJAX.
     * 
     * @return bool
     */
    public function isAjax(): bool {
        return strcasecmp( $this->get_header( 'X-Requested-With', '' ), 'XMLHttpRequest' ) === 0;
    }

    /**
     * Check if request accepts JSON response.
     * 
     * @return bool
     */
    public function wantsJson(): bool {
        $media  = $this->get_header( 'accept', '*/*' );

        if ( (bool) preg_match( '/(^|\s|,)application\/([\w!#\$&-\^\.\+]+\+)?json(\+oembed)?($|\s|;|,)/i', $media ) ) {
            return true;
        }

        return false;
    }

    /**
     * Check if request expects a JSON response or sends JSON content.
     * 
     * @return bool
     */
    public function expectsJson(): bool {
        return $this->wantsJson() || $this->isJson();
    }

    /**
     * Check if client accepts a specific MIME type.
     * 
     * @param string $mimeType MIME type to check.
     * @return bool
     */
    public function accepts( string $mimeType ): bool {
        $accept = $this->get_header( 'Accept', '' );
        return str_contains( strtolower( $accept ), strtolower( $mimeType ) ) || str_contains( $accept, '*/*' );
    }

    /**
     * Get the Content-Type header value.
     * 
     * @return string
     */
    public function contentType(): string {
        return $this->get_header( 'Content-Type', '' );
    }

    /**
     * Get request Content-Length.
     * 
     * @return int
     */
    public function contentLength(): int {
        return (int) ( $this->get_header( 'Content-Length', '0' ) ?: $this->server( 'CONTENT_LENGTH', 0 ) );
    }

    /**
     * Check if request content type is JSON.
     * 
     * @return bool
     */
    public function isJson(): bool {
        return str_contains( strtolower( $this->contentType() ), 'application/json' );
    }

    /**
     * Get the authorization bearer token from header.
     * 
     * @return string|null
     */
    public function bearerToken(): ?string {
        $header = $this->get_header( 'Authorization', '' );

        if ( preg_match( '/Bearer\s+(.*)$/i', $header, $matches ) ) {
            return trim( $matches[1] );
        }
        
        return null;
    }

    /**
     * Get scheme (http or https).
     * 
     * @return string
     */
    public function scheme(): string {
        return $this->isSecure() ? 'https' : 'http';
    }

    /**
     * Get host name.
     * 
     * @return string
     */
    public function host(): string {
        if ( $host = $this->get_header( 'Host' ) ) {
            return strtolower( trim( explode( ':', $host )[0] ) );
        }
        return (string) $this->server( 'SERVER_NAME', '' );
    }

    /**
     * Get port number.
     * 
     * @return int
     */
    public function port(): int {
        if ( $host = $this->get_header( 'Host' ) ) {
            $parts = explode( ':', $host );
            if ( isset( $parts[1] ) ) {
                return (int) $parts[1];
            }
        }
        return (int) $this->server( 'SERVER_PORT', $this->isSecure() ? 443 : 80 );
    }

    /**
     * Get base URL (scheme + host + optional port).
     * 
     * @return string
     */
    public function baseUrl(): string {
        $scheme      = $this->scheme();
        $host        = $this->host();
        $port        = $this->port();
        $defaultPort = ( 'https' === $scheme && 443 === $port ) || ( 'http' === $scheme && 80 === $port );

        return $scheme . '://' . $host . ( $defaultPort ? '' : ':' . $port );
    }

    /**
     * Get full URL including path and query string.
     * 
     * @return string
     */
    public function fullUrl(): string {
        return $this->baseUrl() . $this->uri();
    }

    /**
     * Get client IP address.
     * 
     * @param bool $ignore_private Whether to ignore private and reserved IP ranges.
     * @return string
     */
    public function ip( bool $ignore_private = false ): string {
        // Check standard headers first via internal header handler, then fall back to $_SERVER keys.
        $header_keys = [
            'X-Real-IP',
            'HTTP-Client-IP',
            'X-Forwarded-For',
            'X-Forwarded',
            'X-Cluster-Client-IP',
            'Forwarded-For',
            'Forwarded',
        ];

        // 1. Try resolving through headers
        foreach ( $header_keys as $key ) {
            if ( $this->has_header( $key ) ) {
                $resolved = $this->extract_valid_ip( $this->get_header( $key ), $ignore_private );
                if ( null !== $resolved ) {
                    return $resolved;
                }
            }
        }

        // 2. Fall back to standard direct remote address
        $remote_addr = (string) $this->server( 'REMOTE_ADDR', '' );
        if ( ! empty( $remote_addr ) ) {
            $resolved = $this->extract_valid_ip( $remote_addr, $ignore_private );
            if ( null !== $resolved ) {
                return $resolved;
            }
        }

        return 'unresolved_ip';
    }

    /**
     * Extract the first valid IP address from a comma-separated list.
     * 
     * @param string $ips            Raw IP string.
     * @param bool   $ignore_private Ignore private/reserved ranges.
     * @return string|null
     */
    private function extract_valid_ip( string $ips, bool $ignore_private = false ): ?string {
        $ip_list = explode( ',', $ips );

        $flags = FILTER_FLAG_NONE;
        if ( $ignore_private ) {
            $flags = FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;
        }

        foreach ( $ip_list as $ip ) {
            $ip = static::sanitize_text( trim( $ip ) );
            if ( filter_var( $ip, FILTER_VALIDATE_IP, $flags ) !== false ) {
                return $ip;
            }
        }

        return null;
    }

    /**
     * Get user agent string.
     * 
     * @return string|null
     */
    public function userAgent(): ?string {
        $agent = $this->get_header( 'User-Agent' );
        return ! empty( $agent ) ? $agent : null;
    }

    /**
     * Get referer string.
     * 
     * @return string|null
     */
    public function referer(): ?string {
        $referer = $this->get_header( 'Referer' );
        return ! empty( $referer ) ? $referer : null;
    }

    /**
     * Check if request is secure (HTTPS).
     * 
     * @return bool
     */
    public function isSecure(): bool {
        $https = $this->server( 'HTTPS' );
        if ( null !== $https && 'off' !== strtolower( (string) $https ) ) {
            return true;
        }

        return strtolower( $this->get_header( 'X-Forwarded-Proto' ) ) === 'https';
    }

    /**
     * Get raw request body contents.
     * 
     * @return string
     */
    public function getContent(): string {
        if ( null === $this->raw_content ) {
            $this->raw_content = (string) file_get_contents( 'php://input' );
        }
        return $this->raw_content;
    }

    /**
     * Convert request to array representation.
     * 
     * @return array
     */
    public function toArray(): array {
        return [
            'files'   => $this->files,
            'params'  => $this->params,
            'headers' => $this->headers,
            'method'  => $this->method,
            'uri'     => $this->uri,
        ];
    }

    /**
     * Get the request start time.
     * 
     * @return float
     */
    public function startTime(): float {
        return $this->startTime;
    }

    /**
     * Get first file for key (backward compatible).
     *
     * @param string $key File input key.
     * @return UploadedFile|null
     */
    public function get_file( string $key ): ?UploadedFile {
        $collection = $this->files[ $key ] ?? null;

        if ( ! $collection instanceof UploadedFileCollection ) {
            return null;
        }

        return $collection->get( 0 );
    }

    /**
     * Get file collection.
     *
     * @param string $key File input key.
     * @return UploadedFileCollection|null
     */
    public function get_files( string $key ): ?UploadedFileCollection {
        return $this->files[ $key ] ?? null;
    }

    /*
    |--------------------------------------------------------------------------
    | DEFAULT PARSERS & INTERNAL PIPELINE
    |--------------------------------------------------------------------------
    */

    /**
     * Populate query parameters, pulls from superglobals when null is passed.
     * 
     * @param array|null $query_params
     */
    protected function parse_query( ?array $query_params = null ): void {
        $this->query = $query_params ?? $_GET;
    }

    /**
     * Populate post parameters, pulls from superglobals when null is passed.
     * 
     * @param array|null $post_data
     */
    protected function parse_post( ?array $post_data = null ): void {
        $this->post = $post_data ?? $_POST;
    }

    /**
     * Populate cookie parameters, pulls from superglobals when null is passed.
     * 
     * @param ?array $cookies
     */
    protected function parse_cookies( ?array $cookies = null ): void {
        $this->cookies = $cookies ?? $_COOKIE;
    }

    /**
     * Populate server variables from superglobals.
     */
    protected function parse_server(): void {
        $this->server = $_SERVER;
    }

    /**
     * Parse and cache JSON request body.
     */
    protected function parse_json(): void {
        $this->json_parsed = true;

        if ( ! $this->isJson() ) {
            return;
        }

        $raw_data = $this->getContent();
        if ( '' === trim( $raw_data ) ) {
            return;
        }

        $data = json_decode( $raw_data, true );

        if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $data ) ) {
            $data = [];
        }

        $this->json = $data;
    }

    /**
     * Parse parameters from isolated input bags maintaining precedence.
     * 
     * @return array
     */
    public function parse_params(): array {
        if ( ! $this->json_parsed && $this->isJson() ) {
            $this->parse_json();
        }

        return array_merge( $this->query, $this->post, $this->json );
    }

    /**
     * Build legacy merged parameter storage pool.
     */
    protected function build_merged_params(): void {
        $this->params = $this->parse_params();
    }

    /**
     * Parse default HTTP request headers.
     *
     * @return array<string, string>
     */
    public static function parse_default_headers(): array {
        if ( function_exists( 'getallheaders' ) ) {
            $headers = getallheaders();
            if ( false !== $headers ) {
                return $headers;
            }
        }

        $headers = [];

        foreach ( $_SERVER as $key => $value ) {
            if ( str_starts_with( $key, 'HTTP_' ) ) {
                $name = substr( $key, 5 );
                $name = str_replace( ' ', '-', ucwords( strtolower( str_replace( '_', ' ', $name ) ) ) );
                $headers[ $name ] = $value;
                continue;
            }

            if ( in_array( $key, [ 'CONTENT_TYPE', 'CONTENT_LENGTH', 'CONTENT_MD5' ], true ) ) {
                $name = str_replace( ' ', '-', ucwords( strtolower( str_replace( '_', ' ', $key ) ) ) );
                $headers[ $name ] = $value;
            }
        }

        if ( ! isset( $headers['Authorization'] ) ) {
            if ( isset( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) ) {
                $headers['Authorization'] = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
            } elseif ( isset( $_SERVER['PHP_AUTH_USER'] ) ) {
                $basic_pass = $_SERVER['PHP_AUTH_PW'] ?? '';
                $headers['Authorization'] = 'Basic ' . base64_encode( $_SERVER['PHP_AUTH_USER'] . ':' . $basic_pass );
            } elseif ( isset( $_SERVER['PHP_AUTH_DIGEST'] ) ) {
                $headers['Authorization'] = $_SERVER['PHP_AUTH_DIGEST'];
            }
        }

        return $headers;
    }

    /**
     * Parse and normalize uploaded files into collections.
     * 
     * @param ?array $files
     */
    public function parse_uploaded_files( ?array $files = null ): void {
        $files  = $files ?? $_FILES;

        foreach ( $files as $key => $_ ) {
            $this->files[ $key ] = UploadedFileCollection::from_files( $key );
        }
    }
}