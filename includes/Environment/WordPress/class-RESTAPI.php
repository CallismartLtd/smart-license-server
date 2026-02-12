<?php
/**
 * WordPress REST API configuration class file.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Environment\WordPress
 */

namespace SmartLicenseServer\Environment\WordPress;

use ArgumentCountError;
use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\Response;
use SmartLicenseServer\Core\URL;
use SmartLicenseServer\Exceptions\Exception;
use SmartLicenseServer\Exceptions\RequestException;
use SmartLicenseServer\RESTAPI\RESTInterface;
use SmartLicenseServer\RESTAPI\RESTProviderInterface;
use SmartLicenseServer\Security\Actors\ServiceAccount;
use SmartLicenseServer\Security\Context\ContextServiceProvider;
use SmartLicenseServer\Security\Context\Principal;
use SmartLicenseServer\Security\Context\SecurityGuard;
use SmartLicenseServer\Utils\SanitizeAwareTrait;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

use function is_smliser_error, defined, add_action, add_filter, method_exists;

defined( 'SMLISER_ABSPATH' ) || exit;

class RESTAPI implements RESTProviderInterface {
    use SanitizeAwareTrait;
    /**
     * The current REST API object.
     */
    private ?RESTInterface $rest;

    /**
     * Holds the current WP_REST_Request object.
     * 
     * @var WP_REST_Request $wp_request
     */
    private WP_REST_Request $wp_request;

    /**
     * Holds a refrence to our request object in the current request.
     * 
     * @var Request $request
     */
    private Request $request;

    /**
     * Class constructor
     *
     * @param RESTInterface $rest
     */
    public function __construct( RESTInterface $rest ) {
        $this->rest = $rest;
        
        add_filter( 'rest_request_before_callbacks', [$this, 'rest_request_before_callbacks'], -1, 3 );
        add_filter( 'rest_post_dispatch', [$this, 'filter_response'], 10, 3 );
        add_filter( 'rest_pre_dispatch', [$this, 'enforce_https'], 10, 3 );
        add_action( 'rest_api_init', [$this, 'register_rest_routes'], 30 );
        add_action( 'rest_authentication_errors', [$this, 'authenticate'], 5 );
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
                $data->remove_header( 'Content-Length' ); // Allow WordPress to calculate.
                
                foreach ( $data->get_headers( true ) as $key => $value ) {                    
                    $response->header( $key, $value );
                }

                $response->set_status( $data->get_status_code() );
                
                if ( $data->has_errors() ) {
                    $data = $data->get_exception()->to_wp_error();
                } else {
                    $data   = $data->get_body();
                }

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
    public function enforce_https( ...$params ) : mixed {
        $total_args = count( $params );

        if ( $total_args < 3 ) {
            $reflection = new \ReflectionMethod( $this, __FUNCTION__ );
            $func_line  = $reflection->getStartLine();
            $bt = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 1 )[0]; // top of stack

            throw new ArgumentCountError(
                sprintf(
                    'Too few arguments to function %s, %d passed in %s on line %d and exactly 3 expected in %s on line %d',
                    __METHOD__,
                    $total_args,
                    $bt['file'] ?? 'unknown',
                    $bt['line'] ?? 0,
                    __FILE__,
                    $func_line
                )
            );
        }

        [$result, $server, $request] = $params;

        if ( ! $this->in_namespace( $request->get_route() ) ) {
            return $result;
        }

        if ( 'production' === wp_get_environment_type() && ! is_ssl() ) {
            $result = new WP_Error(
                'connection_not_secure',
                'HTTPS/TLS is required for secure communication.',
                [ 'status' => 400 ]
            );
        }

        return $result;
    }

    /**
     * Register REST API routes in WordPress.
     *
     * @return void
     */
    public function register_rest_routes() : void {
        $api_config = $this->rest->get_routes();
        $namespace  = $api_config['namespace'];
        $routes     = $api_config['routes'];

        foreach ( $routes as $route_config ) {
            $methods =  $route_config['methods'];

            $handlers = [];

            foreach ( $methods as $method ) {
                $handlers[] = [
                    'methods'  => $method,

                    'callback' => function( WP_REST_Request $wp_request ) use ( $route_config ) {
                        return $this->main_dispatcher(
                            $wp_request,
                            $route_config['handler']
                        );
                    },

                    'permission_callback' => function( WP_REST_Request $wp_request ) use ( $route_config ) {
                        
                        return $this->permission_dispatcher(
                            $wp_request,
                            $route_config['guard']
                        );
                    },

                    'args' => $this->prepare_rest_args(
                        $route_config['args'] ?? []
                    ),
                ];
            }

            register_rest_route(
                $namespace,
                $route_config['route'],
                $handlers
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

        return $this->get_request()
            ->merge( $params )
        ->set_headers( $headers );    
    }

    /**
     * The the current request object.
     * 
     * @return Request
     */
    public function get_request() : Request {
        if ( ! isset( $this->request ) ) {
            $this->request = new Request;
        }

        return $this->request;
    }

    /**
     * Perform Authentication using our security and access control policy.
     * 
     * Authentication method is by the HTTP authorization bearer <TOKEN>.
     *
     * @return bool|\WP_Error|null
     */
    public function authenticate() {

        $request = $this->get_request();
        $route   = $this->guess_route();

        if ( ! $this->in_namespace( $route ) ) {
            return null;
        }

        $bearer_token = (string) $request->bearerToken();

        try {

            // No token provided.
            if ( ! $bearer_token ) {

                // Allow anonymous GET requests.
                if ( $request->isGet() ) {
                    return false;
                }

                // All non-GET requests require authentication.
                throw new Exception(
                    'authentication_error',
                    'Authorization header is missing. Please provide a valid Bearer token.',
                    [ 'status' => 401 ]
                );
            }

            // Token provided, attempt authentication.
            $actor = ServiceAccount::verify_api_key( $bearer_token );

            $owner = $actor->get_owner();

            if ( ! $owner || ! $owner->exists() ) {
                throw new Exception(
                    'authentication_error',
                    'Sorry, the service account owner does not exist.',
                    [ 'status' => 403 ]
                );
            }

            $owner_subject = ContextServiceProvider::get_owner_subject( $owner );

            if ( ! $owner_subject ) {
                throw new Exception(
                    'authentication_error',
                    'Sorry, you can no longer act for this resource owner.',
                    [ 'status' => 403 ]
                );
            }

            $role = ContextServiceProvider::get_principal_role( $actor, $owner_subject );

            if ( ! $role ) {
                throw new Exception(
                    'authentication_error',
                    'Sorry, you do not have a valid role.',
                    [ 'status' => 403 ]
                );
            }

            $principal = new Principal( $actor, $role, $owner );

            SecurityGuard::set_principal( $principal );

            return true;

        } catch ( Exception $e ) {
            return $e->to_wp_error();
        }
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
     * @return WP_Error|bool Result of the permission callback.
     */
    public function permission_dispatcher( WP_REST_Request $wp_request, callable $callback ) : WP_Error|bool {

        $request    = $this->convert_wp_request( $wp_request );

        /** @var RequestException|bool $result */ 
        $result     = call_user_func( $callback, $request );

        if ( is_smliser_error( $result ) ) {
            if ( method_exists( $result, 'to_wp_error' ) ) {
                $result = $result->to_wp_error();
            } else {
                $result = new WP_Error( $result->get_error_code(), $result->get_error_message() );
            }
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
        $request    = $this->convert_wp_request( $wp_request );
        $result     = call_user_func( $callback, $request );
        
        return $result;
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
     * Attempt to guess the rest route
     * 
     * @return string
     */
    public function guess_route() : string {
        $uri        = ltrim( $this->get_request()->path(), '/' );
        $prefix     = rest_get_url_prefix();
        $route      = substr( $uri, strlen( $prefix ) ); 

        return $route;
    }
}