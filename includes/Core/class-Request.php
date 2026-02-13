<?php
/**
 * The Smart License Server request class file.
 * 
 * @author Callistus Nwachukwu <admin@callismart.com.ng>
 */

namespace SmartLicenseServer\Core;

use SmartLicenseServer\Utils\SanitizeAwareTrait;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * The classical representation a request object that is understood by all core models.
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
    const DELETE = 'DELETE';

    /**
     * HTTP GET method.
     *
     * @var string
     */
    const GET = 'GET';

    /**
     * HTTP HEAD method.
     *
     * @var string
     */
    const HEAD = 'HEAD';

    /**
     * HTTP OPTIONS method.
     *
     * @var string
     */
    const OPTIONS = 'OPTIONS';

    /**
     * HTTP PATCH method.
     *
     * @var string
     */
    const PATCH = 'PATCH';

    /**
     * HTTP POST method.
     *
     * @var string
     */
    const POST = 'POST';

    /**
     * HTTP PUT method.
     *
     * @var string
     */
    const PUT = 'PUT';


    /**
     * Internal storage for all parameters.
     * 
     * Please note: Both Json, GET and POST data are merged by default.
     *
     * @var array
     */
    private array $params = [];

    /**
     * Holds the files uploaded
     * 
     * @var array<string, \SmartLicenseServer\Core\UploadedFile>
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
     * The HTTP method (GET, POST, PUT, DELETE, etc.)
     * 
     * @var string
     */
    private string $method;

    /**
     * The request URI
     * 
     * @var string
     */
    private string $uri;

    /**
     * Tracks when the request object was instantiated.
     * 
     * @var float $startTime
     */
    private float $startTime = 0.0;

    /**
     * Constructor.
     *
     * @param array  $params  The request params, defaults to $_REQUEST array.
     * @param array  $headers The request headers, defaults to all headers.
     * @param string $method  The HTTP method, defaults to $_SERVER['REQUEST_METHOD'].
     * @param string $uri     The request URI, defaults to $_SERVER['REQUEST_URI'].
     */
    public function __construct( array $params = [], array $headers = [], string $method = '', string $uri = '' ) {
        $this->startTime    = microtime( true );

        if ( class_exists( MultipartRequestParser::class, true ) ) {
            $parser = new MultipartRequestParser();
            $parser->populate_globals();
        }

        $params             = empty( $params ) ? $_REQUEST : $params;
        $this->method       = ! empty( $method ) ? strtoupper( $method ) : ( $_SERVER['REQUEST_METHOD'] ?? 'GET' );
        $this->uri          = ! empty( $uri ) ? $uri : ( $_SERVER['REQUEST_URI'] ?? '/' );
        $raw_headers        = empty( $headers ) ? $this->parse_default_headers() : $headers;
        
        $this->parse_uploaded_files();
        $this->set_headers( $raw_headers );
        $this->set_params( $params );
    }

    /**
     * Set a parameter value.
     *
     * @param string $parameter
     * @param mixed  $value
     * @return static For method chaining
     */
    public function set( string $parameter, $value ): static {
        $this->params[ $parameter ] = $value;
        return $this;
    }

    /**
     * Set multiple parameters
     * 
     * @param array $parameters
     * @return static
     */
    public function set_params( array $parameters ) : static {
        foreach ( $parameters as $key => $value ) {
            $this->set( $key, $value );
        }

        return $this;
    }

    /**
     * Get a parameter value.
     *
     * @param string $parameter
     * @param mixed  $default Optional default value if parameter is not set.
     * @return mixed
     */
    public function get( string $parameter, $default = null ) {
        return $this->params[ $parameter ] ?? $default;
    }

    /**
     * Get a parameter value as a specific type.
     * 
     * @param string $parameter
     * @param string $type      Type to cast to (string, int, float, bool, array)
     * @param mixed  $default   Optional default value if parameter is not set.
     * @return mixed
     */
    public function getTyped( string $parameter, string $type = 'string', $default = null ) {
        $value = $this->get( $parameter, $default );
        
        if ( $value === $default ) {
            return $value;
        }

        return match ( $type ) {
            'int', 'integer' => (int) $value,
            'float', 'double' => (float) $value,
            'bool', 'boolean' => (bool) $value,
            'array' => (array) $value,
            'string' => (string) $value,
            default => $value,
        };
    }

    /**
     * Get multiple parameters at once.
     * 
     * @param array $parameters Array of parameter names
     * @param mixed $default    Default value for missing parameters
     * @return array
     */
    public function getMany( array $parameters, $default = null ): array {
        $result = [];
        foreach ( $parameters as $param ) {
            $result[ $param ] = $this->get( $param, $default );
        }
        return $result;
    }

    /**
     * Get only the specified parameters.
     * 
     * @param array $parameters Array of parameter names to include
     * @return array
     */
    public function only( array $parameters ): array {
        return array_intersect_key( $this->params, array_flip( $parameters ) );
    }

    /**
     * Get all parameters except the specified ones.
     * 
     * @param array $parameters Array of parameter names to exclude
     * @return array
     */
    public function except( array $parameters ): array {
        return array_diff_key( $this->params, array_flip( $parameters ) );
    }

    /**
     * Magic getter.
     */
    public function __get( string $name ) {
        return $this->get( $name );
    }

    /**
     * Magic setter.
     */
    public function __set( string $name, $value ) {
        $this->set( $name, $value );
    }

    /**
     * Check if a parameter exists.
     *
     * @param string $parameter
     * @return bool
     */
    public function has( string $parameter ): bool {
        return array_key_exists( $parameter, $this->params );
    }

    /**
     * Tells whether the specified parameter exists and is not empty.
     * 
     * @param string $parameter The parameter name.
     * @return bool
     */
    public function isEmpty( string $parameter ): bool {
        return empty( $this->get( $parameter ) );
    }

    /**
     * Tells whether the specified parameter exists and is not empty.
     * Alias for !isEmpty() for better readability.
     * 
     * @param string $parameter The parameter name.
     * @return bool
     */
    public function filled( string $parameter ): bool {
        return ! $this->isEmpty( $parameter );
    }

    /**
     * Tells whether the specified properties are all present and not empty.
     * 
     * @param array $properties The parameter names.
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
     * @param array $properties The parameter names.
     * @return bool
     */
    public function hasAny( array $properties ): bool {
        foreach ( $properties as $parameter ) {
            if ( $this->filled( $parameter ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Return all parameters as array.
     *
     * @return array
     */
    public function get_params(): array {
        return $this->params;
    }

    /**
     * Get all parameters as array (alias for get_params).
     * 
     * @return array
     */
    public function all(): array {
        return $this->params;
    }

    /**
     * Merge additional parameters into the request.
     * 
     * @param array $params
     * @return static
     */
    public function merge( array $params ): static {
        $this->params = array_merge( $this->params, $params );
        return $this;
    }

    /**
     * Remove a parameter.
     * 
     * @param string $parameter
     * @return static
     */
    public function remove( string $parameter ): static {
        unset( $this->params[ $parameter ] );
        return $this;
    }

    /**
     * Get a header value.
     * 
     * @param string $header  Header name (case-insensitive)
     * @param mixed  $default Default value if header not found
     * @return mixed
     */
    public function get_header( string $header, $default = null ) {
        $canonical = $this->header_canonical( $header );
        
        if ( ! $this->has_header( $canonical ) ) {
            return $default;
        }

        return implode( ',', (array) $this->headers[$canonical] );
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
    public function set_header( string $header, $value ): static {
        $canonical                                  = $this->header_canonical( $header );
        $this->headers[ $canonical ]                = $value;
        $this->original_header_names[ $canonical ]  = $header;
        return $this;
    }

    /**
     * Set headers
     * 
     * @param array $headers
     * @return static
     */
    public function set_headers( array $headers ) : static {
        foreach( $headers as $key => $value ) {
            $this->set_header( $key, $value );
        }

        return $this;
    }

    /**
     * Check if a header exists.
     * 
     * @param string $header Header name (case-insensitive)
     * @return bool
     */
    public function has_header( string $header ): bool {
        $canonical = $this->header_canonical( $header );
        return array_key_exists( $canonical, $this->headers );
    }

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
     * @param string $method
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
     * Check if request expects JSON response.
     * 
     * @return bool
     */
    public function wantsJson(): bool {
        $accept = $this->get_header( 'Accept', '' );
        return str_contains( strtolower( $accept ), 'application/json' );
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
     * Check if request content type is JSON.
     * 
     * @return bool
     */
    public function isJson(): bool {
        return str_contains( strtolower( $this->contentType() ), 'application/json' );
    }

    /**
     * Get the authorization token from header.
     * 
     * @return string|null
     */
    public function bearerToken(): ?string {
        $header = $this->get_header( 'Authorization', '' );

        if ( is_array( $header ) ) {
            foreach ( $header as $value ) {
                if ( preg_match( '/Bearer\s+(.*)$/i', $value, $matches ) ) {
                    return $matches[1];
                }
            }
        } else if ( preg_match( '/Bearer\s+(.*)$/i', $header, $matches ) ) {
            return $matches[1];
        }
        
        return null;
    }

    /**
     * Ensures that header names are always treated the same regardless of
     * source. Header names are always case-insensitive.
     *
     * @param string $key Header name.
     * @return string Canonicalized name.
     */
    private function header_canonical( string $key ): string {
        $key = strtolower( $key );
        $key = str_replace( '-', '_', $key );

        return $key;
    }

    /**
     * Parse default HTTP headers.
     * 
     * @return array
     */
    public static function parse_default_headers(): array {
        $headers = [];

        if ( function_exists( 'getallheaders' ) ) {
            $headers = (array) getallheaders();
        } else {
            // Fallback for environments where getallheaders() is not available.
            foreach ( $_SERVER as $key => $value ) {
                if ( str_starts_with( $key, 'HTTP_' ) ) {
                    $header = str_replace( '_', '-', substr( $key, 5 ) );
                    $headers[ $header ] = $value;
                }
            }
        }

        return $headers;
    }

    /**
     * Convert request to array representation.
     * 
     * @return array
     */
    public function toArray(): array {
        return [
            'files'     => $this->files,
            'params'    => $this->params,
            'headers'   => $this->headers,
            'method'    => $this->method,
            'uri'       => $this->uri,
        ];
    }

    /**
     * Get the request start time
     * 
     * @return float
     */
    public function startTime() : float {
        return $this->startTime;
    }

    /**
     * Parse and convert each uploaded files
     */
    public function parse_uploaded_files() : void {
        foreach ( $_FILES as $key => $value ) {
            $this->files[$key]  = new UploadedFile( $value, $key );
        }
    }

    /**
     * Get a file.
     * 
     * @param string $key
     * @return ?UploadedFile
     */
    public function get_file( string $key ) : ?UploadedFile {
        return $this->files[$key] ?? null;
    }

}