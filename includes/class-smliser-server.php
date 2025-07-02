<?php
/**
 * REST API Server file
 * 
 * @author Callistus
 * @package SmartLicenseServer\classes
 * @since 1.0.0
 */

defined( 'ABSPATH'  ) || exit;

/**
 * 
 */
class Smliser_Server{

    /**
     * Remote validation tasks.
     * 
     * @var array $tasks
     */
    private $tasks = array();

    /**
     * Single instance of this class.
     * 
     * @var Smliser_Server $instance Instance.
     */
    private static $instance = null;

    /**
     * Our registered namespace
     * 
     * @var string $namespace
     */
    protected $namespace = '';

    /**
     * Instance of the Plugin class.
     * 
     * @var Smliser_Plugin $plugin Instance of our plugin class.
     */
    protected $plugin = null;

    /**
     * Instance of our license class.
     * 
     * @var Smliser_license $license
     */
    protected $license = null;

    /**
     * Authorization token.
     */
    private $authorization = '';

    /**
     * Authorization type
     * 
     * @var string $authorization_type
     */
    private $authorization_type = '';

    /**
     * Permission
     * 
     * @var string $permission
     */
    private $permission = '';

    /**
     * Class constructor.
     */
    public function __construct() {
        $this->namespace = SmartLicense_config::instance()->namespace();
        add_action( 'template_redirect', array( $this, 'serve_package_download' ) );
        add_action( 'admin_post_smliser_authorize_app', array( 'Smliser_Api_Cred', 'oauth_client_consent_handler' ) );
        add_filter( 'template_include', array( $this, 'load_auth_template' ) );
        add_filter( 'rest_request_before_callbacks', array( __CLASS__, 'initialize_plugin_context' ), -1, 3 );
    }

    /*
    |------------
    | SETTERS.
    |------------
    */

    /**
     * Set authorization token
     * 
     * @param string $value The value
     */
    public function set_oauth_token( $value ) {
        $this->authorization = sanitize_text_field( $value );
    }

    /**
     * Set authorization type.
     * 
     * @param WP_REST_Request $request
     */
    public function set_auth_type( WP_REST_Request $request ) {
        $auth_header    = $request->get_header( 'authorization' );
        if ( ! empty( $auth_header ) ) {
            $parts  = explode( ' ', $auth_header );

            // The first value should always be the authentication type;
            $this->authorization_type = sanitize_text_field( wp_unslash( $parts[0] ) );
        }
    }

    /**
     * Set Props
	 * @param WP_REST_Response|WP_HTTP_Response|WP_Error|mixed $response Result to send to the client.
	 *                                                                   Usually a WP_REST_Response or WP_Error.
	 * @param array                                            $handler  Route handler used for the request.
	 * @param WP_REST_Request                                  $request  Request used to generate the response.
     */
    public static function initialize_plugin_context( $response, $handler, $request ) {
        // Ensure this request is for our route
        if ( ! str_contains( $request->get_route(), self::instance()->namespace ) ) {
            return $response;
        }

        if ( is_wp_error( $response ) ) {
            remove_filter( 'rest_post_dispatch', 'rest_send_allow_header' );
        }
        if ( is_null( self::instance()->plugin ) ) {
            self::instance()->plugin   = Smliser_Plugin::instance();            
        }

        if ( is_null( self::instance()->license ) ) {
            self::instance()->license  = Smliser_license::instance();

        }

        return $response;
    }

    /*
    |-----------------------------------------
    | REPOSITORY RESOURCE SERVER METHODS
    |-----------------------------------------
    */

    /**
     * Handles permission when we serve updates informations for a plugin hosted here.
     * 
     * @param WP_REST_Request $request The REST API request object.
     */
    public static function plugin_info( WP_REST_Request $request ) {
        // Either provide the plugin ID(item_id) or the plugin slug.
        $item_id    = absint( $request->get_param( 'item_id' ) );
        $slug       = $request->get_param( 'slug' );

        // Let's identify the plugin.
        $plugin =  self::$instance->plugin->get_plugin( $item_id ) ? self::$instance->plugin->get_plugin( $item_id ) : self::$instance->plugin->get_plugin_by( 'slug', $slug );

        if ( ! $plugin ) {
            
            $reasons = array(
                '',
                array( 
                    'route'         => Smliser_Stats::$plugin_update,
                    'status_code'   => 403,
                    'request_data'  => 'Plugin update response',
                    'response_data' => array( 'reason' => 'The requested plugin does not exists.' )
                )
            );
            do_action( 'smliser_stats', 'denied_access', '', '', $reasons );

            return new WP_Error( 'smliser_rest_error_invalid_plugin', 'The requested plugin does not exists.', array( 'status' => 404 ) );
        }

        if ( self::$instance->is_licensed( $plugin->get_item_id() ) ) {
            $api_key    = sanitize_text_field( self::$instance->extract_token( $request, 'raw' ) );
            
            if ( ! smliser_verify_item_token( $api_key, absint( $plugin->get_item_id() ) ) ) {
                $reasons = array(
                    '',
                    array( 
                        'route'         => Smliser_Stats::$plugin_update,
                        'status_code'   => 401,
                        'request_data'  => 'update permission check',
                        'response_data' => array( 'reason' => 'Unauthorized plugin download' )
                    )
                );
                do_action( 'smliser_stats', 'denied_access', '', '', $reasons );
    
                return new WP_Error( 'smliser_rest_error_invalid_authorization', 'Licensed plugin, provide a valid authorization key.', array( 'status' => 401 ) );
            }
        }
        self::$instance->plugin = $plugin;
        return true;
    }

    /**
     * Update response route handler
     */
    public static function plugin_update_response( $request ) {

        $the_plugin = self::$instance->plugin;
        
        /**
         * Fires for stats syncronization.
         * 
         * @param string $context The context which the hook is fired.
         * @param Smliser_Plugin.
         */
        do_action( 'smliser_stats', 'plugin_update', $the_plugin );
        $response = new WP_REST_Response( $the_plugin->formalize_response(), 200 );
        $response->header( 'content-type', 'application/json' );
        
        return $response;

    }

    /**
     * Repository REST API Route permission handler
     * 
     * @param WP_REST_Request $request The current request object.
     */
    public static function repository_access_permission( WP_REST_Request $request ) {
        $authorization = self::$instance->extract_token( $request );
        self::$instance->set_oauth_token( $authorization );
        self::$instance->set_auth_type( $request );

        if ( 'Bearer' !== self::$instance->authorization_type ) {
            return new WP_Error( 'unsupported_auth_type', "The '" . self::$instance->authorization_type . "' authorization type is not supported.", array( 'status' => 400 ) );
        }
       
        return self::$instance->validate_token() && self::$instance->permission_check( $request );
    }

    /**
     * Repository REST API handler
     * 
     * @param WP_REST_Request $request The current request object.

     */
    public static function repository_response( $request ) {
        $route          = $request->get_route();
        $slug           = sanitize_text_field( $request->get_param( 'slug' ) );
        $scope          = sanitize_text_field( $request->get_param( 'scope' ) );
        $is_entire_repo = false;
        $single_plugin  = false;

        if ( empty( $slug ) && empty( $scope ) ) {
            $is_entire_repo = true;
        } elseif ( ! empty( $slug ) && ! empty( $scope ) ) {
            $single_plugin  = true;
        }
    
        /**
         * Handle request to read entire repository
         */
        if ( $is_entire_repo && 'GET' === $request->get_method() ) {
            $plugin_obj     = new Smliser_plugin();
            $all_plugins    = $plugin_obj->get_plugins();
            $plugins_info   = array();
            if ( ! empty( $all_plugins ) ) {
                foreach ( $all_plugins  as $plugin ) {
                    $plugins_info[] = $plugin->formalize_response();
                }
            }
            
            $response = array(
                'success'   => true,
                'plugins'   => $plugins_info,

            );
        
            return new WP_REST_Response( $response, 200 );
        }

        if ( $single_plugin && 'GET' === $request->get_method() ) {
            $plugin_obj = new Smliser_plugin();
            $real_slug  = trailingslashit( $slug ) . $slug . '.zip';
            $the_plugin = $plugin_obj->get_plugin_by( 'slug', $real_slug );
            
            if ( ! $the_plugin ){
                $response_data = array(
                    'success'      => false,
                    'message'   => 'The plugin was not found'
                );
                $response = new WP_REST_Response( $response_data, 404 );
                $response->header( 'content-type', 'application/json' );
        
                return $response;
            }
            $response_data = array(
                'success'   => true,
                'plugin'    => $the_plugin->formalize_response(),
            );
            
            $response = new WP_REST_Response( $response_data, 200 );
            $response->header( 'content-type', 'application/json' );
            return $response;
        }

    }


    /**
     * Serve plugin Download.
     */
    public function serve_package_download() {
        global $wp_query, $smliser_repo;

        if ( isset( $wp_query->query_vars['smliser_repository_download_page'] ) ) {
            
            $plugin_slug    = ! empty( $wp_query->query_vars['plugin_slug'] ) ? sanitize_text_field( wp_unslash( $wp_query->query_vars['plugin_slug'] ) ) : '';
            $plugin_file    = ! empty( $wp_query->query_vars['plugin_file'] ) ? sanitize_text_field( wp_unslash( $wp_query->query_vars['plugin_file'] ) ) : '';
            
            if ( empty( $plugin_slug ) || empty( $plugin_file ) ) {
                wp_die( 'Plugin slug missing', 400 );
            }

            if ( $plugin_slug !== $plugin_file ) {
                wp_die( 'Invalid plugin slug construct.', 400 );
            }

            $plugin_obj = new Smliser_Plugin();
            $plugin     = $plugin_obj->get_plugin_by( 'slug', sanitize_and_normalize_path( $plugin_slug . '/' . $plugin_file . '.zip' ) );

            if ( ! $plugin ) {
                wp_die( 'Plugin not found.', 404 );
            }

            /**
             * Serve download for licensed plugin
             */
            if ( $this->is_licensed( $plugin ) ) {
                $api_key    = '';
                $authorization = smliser_get_authorization_header();

                if ( $authorization ) {
                    $parts = explode( ' ', $authorization );
                    $api_key = $parts[1];
                }
                

                if ( empty( $api_key ) ) {
                    wp_die( 'Licensed plugin, please provide download token.', 401 );
                }

                $item_id = $plugin->get_item_id();

                if ( ! smliser_verify_item_token( $api_key, $item_id ) ) {
                    wp_die( 'Invalid token, you may need to purchase a license for this plugin.', 401 );
                }

            }

            $slug           = $plugin->normalize_slug( trailingslashit( $plugin_slug ) . $plugin_file );
            $plugin_path    = $smliser_repo->get_plugin( $slug );       
            
            if ( ! $smliser_repo->exists( $plugin_path ) ) {
                wp_die( 'Plugin file does not exist.', 404 );
            }
    
            // Serve the file for download.
            if ( $smliser_repo->is_readable( $plugin_path ) ) {

                /**
                 * Fires for download stats syncronization.
                 * 
                 * @param string $context The context which the hook is fired.
                 * @param Smliser_Plugin The plugin object (optional).
                 */
                do_action( 'smliser_stats', 'plugin_download', $plugin );

                header( 'content-description: File Transfer' );
                header( 'content-type: application/zip' );
                header( 'content-disposition: attachment; filename="' . basename( $plugin_path ) . '"' );
                header( 'expires: 0' );
                header( 'cache-control: must-revalidate' );
                header( 'pragma: public' );
                header( 'content-length: ' . $smliser_repo->size( $plugin_path ) );
                $smliser_repo->readfile( $plugin_path );
                exit;
            } else {
                wp_die( 'Error: The file cannot be read.' );
            }
        }
    }

    /**
     * Serve admin plugin download.
     * 
     * @global Smliser_Repository $smliser_repo The Repository instance()
     */
    public static function serve_admin_download() {
        global $smliser_repo;
        if ( ! isset( $_GET['download_token'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['download_token'] ) ), 'smliser_download_token' ) ) {
            wp_die( 'Invalid token', 401 );
        }

        if ( ! is_admin() || ! current_user_can( 'install_plugins' ) ) {
            wp_die( 'You don\'t have the required permmission to download this plugin', 401 );
        }

        $item_id    = isset( $_GET['item_id'] ) ? absint( $_GET['item_id'] ) : 0;
        $plugin_obj = new Smliser_Plugin();
        $the_plugin = $plugin_obj->get_plugin( $item_id );

        if ( ! $the_plugin ) {
            wp_die( 'Invalid or deleted plugin', 404 );
        }

        $slug       = $the_plugin->get_slug();
        $file_path  = $smliser_repo->get_plugin( $slug );
        
        if ( ! $smliser_repo->exists( $file_path ) ) {
            wp_die( 'Plugin file does not exist.', 404 );
        }

        // Serve the file for download.
        if ( $smliser_repo->is_readable( $file_path ) ) {

            /**
             * Fires for download stats syncronization.
             * 
             * @param string $context The context which the hook is fired.
             * @param Smliser_Plugin The plugin object (optional).
             */
            do_action( 'smliser_stats', 'plugin_download', $the_plugin );

            header( 'content-description: File Transfer' );
            header( 'content-type: application/zip' );
            header( 'content-disposition: attachment; filename="' . basename( $file_path ) . '"' );
            header( 'expires: 0' );
            header( 'cache-control: must-revalidate' );
            header( 'pragma: public' );
            header( 'content-length: ' . $smliser_repo->size( $file_path ) );
            $smliser_repo->readfile( $file_path );
            exit;
        } else {
            wp_die( 'Error: The file cannot be read.' );
        }

        wp_safe_redirect( smliser_repository_admin_action_page( 'view', $item_id ) );
        exit;
    }

    /**
     * Authentication Tempalte file loader
     */
    public function load_auth_template( $template ) {
        global $wp_query;
        if ( isset( $wp_query->query_vars[ 'smliser_auth' ] ) ) {
            $oauth_template_handler = SMLISER_PATH . 'templates/auth/auth-controller.php';
            return $oauth_template_handler;
        }
        
        return $template;
    }

    /*
    |----------------
    |UTILITY METHODS
    |----------------
    */

    /**
     * Ban an IP from accessing our REST API Route.
     * 
     * @param string $ip The IP to ban
     */
    public function ban_ip( $ip ) {
        return update_option( 'smliser_ip_ban_list', $ip );
    }

    /**
     * Calculate the next validation time for a given task.
     *
     * @param int $task_timestamp The task timestamp.
     * @param int $cron_timestamp The next cron job timestamp.
     * @return string The next validation time in a human-readable format.
     */
    public function calculate_next_validation_time( $task_timestamp, $cron_timestamp ) {
        $tasks      = $this->scheduled_tasks();
        $cron_intvl = 3 * MINUTE_IN_SECONDS; // Runs every 3mins.
        $task_count = 0;

        foreach ( $tasks as $timestamp => $task ) {
            $task_count++;
            if ( $task_timestamp === $timestamp ) {
                $task_count = absint( $task_count - 1 ); // Looking for tasks ahead.
                break;
            }
        }
        
        if ( $task_count === 0 ) {
            $next_task_time = $cron_timestamp - time();
            return smliser_readable_duration( $next_task_time ); // The current cron countdown is for the next task.
        }

        $cron_intvl     = $cron_intvl * $task_count; // Cron will run for tasks ahead.
        $next_task_time = $cron_timestamp - time() + $cron_intvl;

        return smliser_readable_duration( $next_task_time );
    }

    /**
     * Set status when performing remote post.
     */
    public function doing_post() {
        set_transient( 'smliser_server_doing_post', true, 30 );
    }

    /**
     * Are we currently doing a post?
     */
    public function is_doing_post() {
        return get_transient( 'smliser_server_doing_post', true );
    }

    /**
     * Instance of Smiliser_Server
     * 
     * @return self
     */
    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Check if a given plugin is licensed.
     * 
     * @param mixed|Smliser_Plugin $plugin The plugin instance, ID, or slug.
     * @return bool true if plugin is licensed, false otherwise.
     */
    public function is_licensed( $plugin ) {
        if ( empty( $plugin ) ) {
            return false;
        }

        $item_id = 0;
        if ( $plugin instanceof Smliser_Plugin ) {
            $item_id = absint( $plugin->get_item_id() );
        } elseif ( is_int( $plugin ) ) {
            $item_id = absint( $plugin );
        } elseif ( is_string( $plugin ) ) {
            $plugin_slug = sanitize_text_field( $plugin );
            if ( strpos( $plugin_slug, '/' ) !== false ) {
                $obj        = new Smliser_Plugin();
                $plugin     = $obj->get_plugin_by( 'slug', $plugin_slug );
                $item_id    = ! empty( $plugin ) ? $plugin->get_item_id() : 0;
            }
        }

        if ( empty( $item_id ) ) {
            return false;
        }

        global $wpdb;
        $table_name = SMLISER_LICENSE_TABLE;
        $query      = $wpdb->prepare( "SELECT `item_id` FROM {$table_name} WHERE `item_id` = %d", $item_id );
        $result     = $wpdb->get_var( $query ); // phpcs:disable

        return ! empty( $result );
    }

    /**
     * Extract the value of the http authorization from request header.
     * 
     * @param WP_REST_Request $request The WordPress REST response object.
     * @param string $context           The context in which the token is extracted. 
     *                                  Pass "raw" for the raw data defaults to "decode"
     * 
     * @return string|null The value of the http authorization header, null otherwise.
     */
    public function extract_token( WP_REST_Request $request, $context = 'decode' ) {

        // Get the authorization header.
        $header = $request->get_header( 'authorization' );
        
        if ( ! empty( $header ) ) {
            $parts  = explode( ' ', $header );
            if ( 2 === count( $parts ) && 'Bearer' === $parts[0] ) {
                if ( 'raw' === $context ) {
                    return $parts[1];
                }
                
                return smliser_safe_base64_decode( $parts[1] );

            }            
        }

        // Return null if no valid token is found.
        return null;
    }

    /**
     * Validate Authorization token
     * 
     * @return bool true if valid, false otherwise.
     */
    public static function validate_token() {
        $token = sanitize_text_field( self::$instance->authorization );
        if ( empty( $token ) ) {
            return false;
        }

        $api_cred_obj   = new Smliser_API_Cred();
        $api_credential = $api_cred_obj->get_by_token( $token );

        if ( empty( $api_credential ) ) {
            return false;
        }

        $expiry     = ! empty( $api_credential->get_token( 'token_expiry' ) ) ? strtotime( $api_credential->get_token( 'token_expiry' ) ) : 0;
        
        if ( $expiry < time() ) {
            return false;
        }

        $real_token = sanitize_text_field( $api_credential->get_token( 'token' ) );
        self::$instance->permission = sanitize_text_field( $api_credential->get_permission( 'raw' ) );
        $api_credential->log_access();

        return hash_equals( $real_token, $token );

    }

    /**
     * Set headers for a denied response.
     *
     * @param WP_REST_Response $response The response object to set headers on.
     * @return WP_REST_Response The response object with headers set.
     */
    private static function set_denied_header( WP_REST_Response $response ) {
        $response->header( 'content-type', 'application/json' );
        $response->header( 'X-Smliser-REST-Response', 'AccessDenied' );
        $response->header( 'WWW-Authenticate', 'Bearer realm="example", error="invalid_token", error_description="Invalid access token supplied."' );

    }

    /**
     * Check scope of the given access token against request method.
     *
     * @param WP_REST_Request $request The REST request.
     * @return bool True if the token has permission, false otherwise.
     */
    public function permission_check( WP_REST_Request $request ) {
        $token_permission       = $this->permission;
        $allowed_permissions    = array( 'read', 'write', 'read_write' );

        // Ensure the token permission is valid.
        if ( ! in_array( $token_permission, $allowed_permissions, true ) ) {
            return false;
        }

        $request_method = $request->get_method();

        // Allow GET requests only for read or read_write permissions.
        if ( 'GET' === $request_method && in_array( $token_permission, array( 'read', 'read_write' ), true ) ) {
            return true;
        }

        // Allow POST requests only for write or read_write permissions.
        if ( 'POST' === $request_method && in_array( $token_permission, array( 'write', 'read_write' ), true ) ) {
            return true;
        }

        // Deny access for any other methods or conditions not explicitly checked.
        return false;
    }

}

Smliser_Server::instance();
