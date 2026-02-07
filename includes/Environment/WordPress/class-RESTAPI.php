<?php
/**
 * WordPress REST API configuration class file.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Environment\WordPress
 */

namespace SmartLicenseServer\Environment\WordPress;

use SmartLicenseServer\Core\URL;
use SmartLicenseServer\RESTAPI\RESTInterface;
use SmartLicenseServer\Utils\SanitizeAwareTrait;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class RESTAPI {
    use SanitizeAwareTrait;
    /**
     * The current REST API object.
     */
    private ?RESTInterface $rest;
    /**
     * Class constructor
     *
     * @param RESTInterface $rest
     */
    public function __construct( RESTInterface $rest ) {
        $this->rest = $rest;
        
        add_filter( 'rest_request_before_callbacks', [$this, 'rest_request_before_callbacks'], -1, 3 );
        add_filter( 'rest_post_dispatch', [$this, 'filter_rest_response'], 10, 3 );
        add_filter( 'rest_pre_dispatch', array( $this, 'enforce_https_for_rest_api' ), 10, 3 );
    }


    /**
     * Preempt REST API request callbacks.
     * 
     * @param WP_REST_Response|WP_HTTP_Response|WP_Error|mixed $response Result to send to the client.
     *                                                                   Usually a WP_REST_Response or WP_Error.
     * @param array                                            $handler  Route handler used for the request.
     * @param WP_REST_Request                                  $request  Request used to generate the response.
     */
    public function rest_request_before_callbacks( $response, $handler, $request ) {
        
        $route     = ltrim( $request->get_route(), '/' );
        $namespace = trim( $this->rest->get_namespace(), '/' );

        // Match if route starts with namespace
        if ( ! preg_match( '#^' . preg_quote( $namespace, '#' ) . '(/|$)#', $route ) ) {
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
    public function filter_rest_response( WP_REST_Response $response, WP_REST_Server $server, WP_REST_Request $request ) {

        if ( false !== strpos( $request->get_route(), $this->rest->get_namespace() ) ) {

            $response->header( 'X-App-Name', SMLISER_APP_NAME );
            $response->header( 'X-API-Version', 'v1' );

            $data = $response->get_data();

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
     * @return WP_Error|null WP_Error object if HTTPS/TLS requirement is not met, null otherwise.
     */
    public function enforce_https_for_rest_api( $result, $server, $request ) {
        // Check if current request belongs to the plugin's namespace.
        if ( ! str_contains( $request->get_route(), $this->rest->get_namespace() ) ) {
            return;
        }

        // Check if environment is production and request is not over HTTPS.
        if ( 'production' === wp_get_environment_type() && ! is_ssl() ) {
            // Create WP_Error object to indicate insecure SSL.
            $error = new WP_Error( 'connection_not_secure', 'HTTPS/TLS is required for secure communication.', array( 'status' => 400, ) );
            
            // Return the WP_Error object.
            return $error;
        }
    }

    /**
     * Register REST API routes
     *
     * @return void
     */
    public function register_rest_routes() {
        $api_config = $this->rest->get_routes();
        $namespace = $api_config['namespace'];
        $routes = $api_config['routes'];

        foreach ( $routes as $route_config ) {
            register_rest_route(
                $namespace,
                $route_config['route'],
                array(
                    'methods'             => $route_config['methods'],
                    'callback'            => $route_config['callback'],
                    'permission_callback' => $route_config['permission'],
                    'args'                => $this->validate_rest_args( $route_config['args'] ),
                )
            );
        }        
    }

    /**
     * Add WordPress-specific validation and sanitization to route arguments.
     * 
     * @param array $args Route arguments.
     * @return array Modified arguments with WordPress callbacks.
     */
    private function validate_rest_args( $args ) {
        foreach ( $args as $key => &$arg ) {
            // Add sanitization callbacks based on type
            if ( $arg['type'] === 'string' ) {
                if ( $key === 'domain' ) {
                    $arg['sanitize_callback'] = array( __CLASS__, 'sanitize_url' );
                    $arg['validate_callback'] = array( __CLASS__, 'is_url' );
                } else {
                    $arg['sanitize_callback'] = array( __CLASS__, 'sanitize' );
                    $arg['validate_callback'] = array( __CLASS__, 'not_empty' );
                }
            } elseif ( $arg['type'] === 'integer' ) {
                $arg['sanitize_callback'] = 'absint';
            } elseif ( $arg['type'] === 'array' ) {
                $arg['sanitize_callback'] = array( __CLASS__, 'sanitize' );
                if ( ! isset( $arg['validate_callback'] ) ) {
                    $arg['validate_callback'] = '__return_true';
                }
            }
        }

        return $args;
    }

    /**
     * Encapsulted sanitization function for REST param.
     * 
     * @param mixed $value The value to sanitize.
     * @return mixed
     */
    public static function sanitize( $value ) : mixed{
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
    public static function sanitize_url( $url ) {
        $url = esc_url_raw( $url );
        if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
            return new WP_Error( 'rest_invalid_param', __( 'Invalid URL format.', 'smliser' ), array( 'status' => 400 ) );
        }
        return $url;
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
    public static function is_url( $url, $request, $param ) {
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
    public static function is_int( $value, $request, $key ) {
        if ( ! is_numeric( $value ) || intval( $value ) != $value ) {
            return new WP_Error( 'rest_invalid_param', __( 'The value must be an integer.', 'smliser' ), array( 'status' => 400 ) );
        }
        return true;
    }
}