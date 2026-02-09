<?php
/**
 * WordPress REST API configuration class file.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Environment\WordPress
 */

namespace SmartLicenseServer\Environment\WordPress;

use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\Response;
use SmartLicenseServer\Core\URL;
use SmartLicenseServer\RESTAPI\RESTInterface;
use SmartLicenseServer\Utils\SanitizeAwareTrait;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

use function is_smliser_error, defined, add_action, add_filter, method_exists;

defined( 'SMLISER_ABSPATH' ) || exit;

class RESTAPI {
    use SanitizeAwareTrait;
    /**
     * The current REST API object.
     */
    private ?RESTInterface $rest;

    /**
     * Temporary cache for converted request objects during REST dispatch lifecycle.
     *
     * Used to persist our request instance between permission
     * and main callbacks for the same WP_REST_Request object.
     *
     * @var array<string, Request>
     */
    private array $request_cache = [];

    /**
     * Class constructor
     *
     * @param RESTInterface $rest
     */
    public function __construct( RESTInterface $rest ) {
        $this->rest = $rest;
        
        add_filter( 'rest_request_before_callbacks', [$this, 'rest_request_before_callbacks'], -1, 3 );
        add_filter( 'rest_post_dispatch', [$this, 'filter_response'], 10, 3 );
        add_filter( 'rest_pre_dispatch', array( $this, 'enforce_https' ), 10, 3 );
        add_action( 'rest_api_init', [$this, 'register_rest_routes'] );
    }


    /**
     * Preempt REST API request callbacks.
     * 
     * @param WP_REST_Response|WP_HTTP_Response|WP_Error|mixed $response Result to send to the client.
     *                                                                   Usually a WP_REST_Response or WP_Error.
     * @param array                                            $handler  Route handler used for the request.
     * @param WP_REST_Request                                  $request  Request used to generate the response.
     * @return mixed
     */
    public function rest_request_before_callbacks( $response, $handler, $request ) : mixed {
        
        if ( ! $this->in_namespace( $request->get_route() ) ) {
            return $response;
        }

        if ( is_smliser_error( $response ) ) {
            remove_filter( 'rest_post_dispatch', 'rest_send_allow_header' ); // Prevents calling the permission callback again.
        }

        return $response;
    }

    /**
     * Filter the REST API response.
     *
     * @param WP_REST_Response $response The REST API response object.
     * @param WP_REST_Server   $server   The REST server object.
     * @param WP_REST_Request  $request  The REST request object.
     * @return WP_REST_Response Modified REST API response object.
     */
    public function filter_response( WP_REST_Response $response, WP_REST_Server $server, WP_REST_Request $request ) : WP_REST_Response {

        if ( $this->in_namespace( $request->get_route() ) ) {
            $data = $response->get_data();

            if ( $data instanceof Response ) {
                foreach ( $data->get_headers() as $key => $value ) {
                    $response->header( $key, $value );
                }

                $data   = $data->get_body();
                $response->set_data( $data );
            }

            $response->header( 'X-App-Name', SMLISER_APP_NAME );
            $response->header( 'X-API-Version', 'v1' );

            if ( is_array( $data ) ) {
                $data = array( 'success' => ! $response->is_error() ) + $data;
                $response->set_data( $data );
            }
        }

        return $response;
    }

    /**
     * Ensures HTTPS/TLS for REST API endpoints within the plugin's namespace.
     *
     * Checks if the current REST API request belongs to the plugin's namespace
     * and enforces HTTPS/TLS requirements if the environment is production.
     *
     * @return mixed WP_Error object if HTTPS/TLS requirement is not met, mixed 
     * depending on what other callbacks might return.
     */
    public function enforce_https( $result, $server, $request ) : mixed {
        if ( ! $this->in_namespace( $request->get_route() ) ) {
            return $result;
        }

        // Check if environment is production and request is not over HTTPS.
        if ( 'production' === wp_get_environment_type() && ! is_ssl() ) {
            $result = new WP_Error( 'connection_not_secure', 'HTTPS/TLS is required for secure communication.', array( 'status' => 400, ) );
        }

        return $result;
    }

    /**
     * Register REST API routes
     *
     * @return void
     */
    public function register_rest_routes() {
        $api_config     = $this->rest->get_routes();
        $namespace      = $api_config['namespace'];
        $routes         = $api_config['routes'];

        foreach ( $routes as $route_config ) {
            register_rest_route(
                $namespace,
                $route_config['route'],
                array(
                    'methods'   => $route_config['methods'],
                    'callback'  => function( WP_REST_Request $wp_request ) use ( $route_config ) {
                        return $this->main_dispatcher( $wp_request, $route_config['callback'] );
                    },

                    'permission_callback' => function( WP_REST_Request $wp_request ) use ( $route_config ) {
                        return $this->permission_dispatcher( $wp_request, $route_config['permission'] );
                    },

                    'args'  => $this->prepare_rest_args( $route_config['args'] ),
                )
            );
        }        
    }

    /**
     * Prepare and enhance REST route arguments with WordPress-specific
     * sanitization and validation callbacks.
     *
     * @param array $args Raw route arguments.
     * @return array Prepared arguments ready for register_rest_route().
     */
    private function prepare_rest_args( array $args ) : array {
        foreach ( $args as $key => &$arg ) {
            // Add sanitization callbacks based on type.
            if ( 'string' === $arg['type'] ) {
                if ( $key === 'domain' ) {
                    $arg['sanitize_callback'] = array( __CLASS__, 'sanitize_url' );
                    $arg['validate_callback'] = array( __CLASS__, 'is_url' );
                } else {
                    $arg['sanitize_callback'] = array( __CLASS__, 'sanitize' );
                    $arg['validate_callback'] = array( __CLASS__, 'not_empty' );
                }
            } elseif ( isset( $arg['type'] ) && 'integer' === $arg['type'] ) {
                $arg['sanitize_callback'] = 'absint';
            } elseif ( isset( $arg['type'] ) && 'array' === $arg['type'] ) {
                $arg['sanitize_callback'] = array( __CLASS__, 'sanitize' );
                if ( ! isset( $arg['validate_callback'] ) ) {
                    $arg['validate_callback'] = '__return_true';
                }
            }
        }

        return $args;
    }

    /**
     * Convert WordPress REST API request object to our request object for
     * compatibility in WordPress environment.
     * 
     * @param WP_REST_Request $wp_request
     * @param callable $callback
     */
    public function convert_wp_request( WP_REST_Request $wp_request ) : Request {
        $headers    = $wp_request->get_headers();
        $params     = $wp_request->get_params();

        $request    = new Request( $params );

        $request->set_headers( $headers );

        return $request;
    } 

    /**
     * Encapsulted sanitization function for REST param.
     * 
     * @param mixed $value The value to sanitize.
     * @return mixed
     */
    public static function sanitize( $value ) : mixed {
        if ( is_string( $value ) ) {
            $value = static::sanitize_text( $value );
        } elseif ( is_array( $value ) ) {
            $value = static::sanitize_deep( $value );
        } elseif ( is_int( $value ) ) {
            $value = static::sanitize_int( $value );
        } elseif ( is_float( $value ) ) {
            $value = static::sanitize_float( $value );
        }

        return $value;
    }
    
    /**
     * Sanitize a URL for REST API validation.
     * 
     * @param string $url The URL to sanitize.
     * @return string Sanitized URL.
     */
    public static function sanitize_url( $url ) : string {
        $url = new URL( $url );
        $url->sanitize();
        
        return $url->get_href();
    }

    /**
     * Check whether a value is empty for REST API validation.
     * @param string $value The value to check.
     * @return bool true if not empty, false otherwise.
     */
    public static function not_empty( $value ) {
        if ( empty( $value ) ) {
            return new WP_Error( 'rest_invalid_param', __( 'The value cannot be empty.', 'smliser' ), array( 'status' => 400 ) );
        }
        return true;
    }

    /**
     * Validate if the given URL is an HTTP or HTTPS URL.
     *
     * @param string $url The URL to validate.
     * @param WP_REST_Request $request The request object.
     * @param string $param The parameter name.
     * @return true|WP_Error
     */
    public static function is_url( $url, $request, $param ) : bool|WP_Error {
        if ( empty( $url ) ) {
            return new WP_Error( 'rest_invalid_param', __( 'The domain parameter is required.', 'smliser' ), array( 'status' => 400 ) );
        }

        $url    = new URL( $url );

        if ( ! $url->has_scheme() ) {
            return new WP_Error( 'rest_invalid_param', __( 'Invalid URL format.', 'smliser' ), array( 'status' => 400 ) );
        }

        if ( ! $url->is_ssl() ) {
            return new WP_Error( 'rest_invalid_param', __( 'Only HTTPS URLs are allowed.', 'smliser' ), array( 'status' => 400 ) );
        }

        if ( ! $url->validate( true ) ) {
            return new WP_Error( 'rest_invalid_param', __( 'The URL does not resolve to a valid host.', 'smliser' ), array( 'status' => 400 ) );
        }

        return true;
    }

    /**
     * Validate if a given REST API parameter is an integer.
     * 
     * @param mixed $value The value to validate.
     * @param WP_REST_Request $request The request object.
     * @param string $key The parameter name.
     * @return true|WP_Error Returns true if the value is an integer, otherwise returns a WP_Error object.
     */
    public static function is_int( $value, $request, $key ) : bool|WP_Error {
        if ( ! is_numeric( $value ) || intval( $value ) != $value ) {
            return new WP_Error( 'rest_invalid_param', __( 'The value must be an integer.', 'smliser' ), array( 'status' => 400 ) );
        }
        return true;
    }

    /**
     * Determine whether the given route belongs to our REST namespace.
     *
     * @param string $route_name The full REST route name.
     * @return bool True when the route name starts with our namespace.
     */
    public function in_namespace( string $route_name ) : bool {
        $route     = ltrim( $route_name, '/' );
        $namespace = trim( $this->rest->get_namespace(), '/' );
        $namespace = preg_quote( $namespace, '#' );

        return (bool) preg_match( "#^{$namespace}(/|$)#", $route );
    }

    /**
     * Generate a unique identifier for a WordPress REST request instance.
     *
     * This ensures that the same request object used in permission callback
     * can be reliably matched to the one used in the main callback.
     *
     * @param WP_REST_Request $request The WordPress REST request object.
     * @return string Unique object hash for the request.
     */
    private function get_request_key( WP_REST_Request $request ) : string {
        return spl_object_hash( $request );
    }

    /**
     * Dispatch permission callback using our request object.
     *
     * Converts the WP_REST_Request into our internal Request object,
     * executes the permission callback, and stores the converted
     * request instance for reuse in the main callback.
     *
     * This avoids WordPress recreating request data between callbacks.
     *
     * @param WP_REST_Request $wp_request The original WordPress REST request.
     * @param callable        $callback   The permission callback to execute.
     * @return mixed Result of the permission callback.
     */
    public function permission_dispatcher( WP_REST_Request $wp_request, callable $callback ) : mixed {

        $request = $this->convert_wp_request( $wp_request );

        $result = call_user_func( $callback, $request );

        $key = $this->get_request_key( $wp_request );

        $this->request_cache[ $key ] = $request;

        if ( is_smliser_error( $result ) ) {
            $result = method_exists( $result, 'to_wp_error' ) ? $result->to_wp_error() : $result;
        }

        return $result;
    }

    /**
     * Dispatch main REST callback using our request object.
     *
     * Reuses the Request instance created during permission callback
     * if available, ensuring consistent data between both execution stages.
     *
     * After execution, the cached request is removed to prevent memory leaks.
     *
     * @param WP_REST_Request $wp_request The original WordPress REST request.
     * @param callable        $callback   The main route callback to execute.
     * @return mixed Result of the main callback.
     */
    public function main_dispatcher( WP_REST_Request $wp_request, callable $callback ) : mixed {

        $key = $this->get_request_key( $wp_request );

        if ( isset( $this->request_cache[ $key ] ) ) {
            $request = $this->request_cache[ $key ];
        } else {
            $request = $this->convert_wp_request( $wp_request );
        }

        return call_user_func( $callback, $request );
    }

    /**
     * Clear request cache manually if needed.
     *
     * Useful for debugging or explicit lifecycle cleanup.
     *
     * @return void
     */
    public function clear_request_cache() : void {
        $this->request_cache = [];
    }

}