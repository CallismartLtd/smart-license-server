<?php
/**
 * file name class-license-server.php
 * License server class file
 * 
 * @author Callistus
 * @package SmartLicenseServer\classes
 * @since 1.0.0
 */

defined( 'ABSPATH'  ) || exit;

/**
 * The Smliser_Server class handles most of the API requests for this plugin.
 * It's core purpose is to serve requests for the licenses and plugins on this server.
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
        add_action( 'smliser_validate_license', array( $this, 'remote_validate' ) );
        add_action( 'template_redirect', array( $this, 'serve_package_download' ) );
        add_action( 'admin_post_smliser_authorize_app', array( 'Smliser_Api_Cred', 'oauth_client_consent_handler' ) );
        add_filter( 'template_include', array( $this, 'load_auth_template' ) );
        add_filter( 'rest_request_before_callbacks', array( __CLASS__, 'set_props' ), 10, 3 );
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
    public static function set_props( $response, $handler, $request ) {
        // Ensure this request is for our route
        if ( ! str_contains( $request->get_route(), self::instance()->namespace ) ) {
            return $response;
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
    |--------------------------------------------------
    | LICENSE VALIDATION SERVICE PROVISION METHODS
    |--------------------------------------------------
    */

    /**
     * Perform license validation by providing client website with license documents.
     */
    public function remote_validate() {

        if ( $this->is_doing_post() ) {
            return;
        }

        $this->doing_post();
        // Fetch the highest priority task data
        $highest_priority_task = $this->fetch_highest_priority_task();

        if ( empty( $highest_priority_task ) ) {
            return;
        }
        
        // Extract task data.
        $callback_url   = isset( $highest_priority_task['callback_url'] ) ? sanitize_text_field( wp_unslash( $highest_priority_task['callback_url'] ) ) : '';
        $license_key    = isset( $highest_priority_task['license_key'] ) ? sanitize_text_field( wp_unslash( $highest_priority_task['license_key'] ) ) : '';
        $license_id     = isset( $highest_priority_task['license_id'] ) ? absint( $highest_priority_task['license_id'] ) : 0;
        $token          = isset( $highest_priority_task['token'] ) ? sanitize_text_field( wp_unslash( $highest_priority_task['token'] ) ) : '';
        $data           = isset( $highest_priority_task['data'] ) ? sanitize_text_field( wp_unslash( $highest_priority_task['data'] ) ) : '';
        $end_date       = isset( $highest_priority_task['end_date'] ) ? sanitize_text_field( wp_unslash( $highest_priority_task['end_date'] ) ) : '' ;
    
        // Ensure task data is valid.
        if ( empty( $license_key ) || empty( $token ) || empty( $data ) ) {
            return;
        }

        // No matter the task, the license must be valid first.
        $license    = Smliser_license::get_by_id( absint( $license_id ) );
        if ( ! $license ) {
            return;
        }

        $request = array(
            'headers'   => array( 
                'Content-Type' => 'application/json',

            ),

            'body'      =>  $data,
            'timeout'   => 30,

        );

        $token_expiry   = wp_date( 'Y-m-d', time() + DAY_IN_SECONDS ); // The plugin needs to reauthenticate within 24hrs.
        $params = http_build_query( 
            array(
                'action'        => 'smliser_verified_license_action',
                'last_updated'  => $end_date,
                'API_KEY'       => smliser_generate_item_token( $license->get_item_id(), $license_key, 40 * HOUR_IN_SECONDS ), // API key will be invalidated 1 day and 16hrs, allowing 16hrs for reauth.
                'token_expiry'  => $token_expiry,
                'token'         => $token,
            ) 
        );

        $client_url = trailingslashit( $callback_url ) . '?' . $params;
        $response   = wp_remote_post( $client_url, $request );
        // Check if remote request was successful
        if ( is_wp_error( $response ) ) {
            delete_transient( 'smliser_server_doing_post' );
            $this->log_executed_task( $highest_priority_task, array( 'comment' => $response->get_error_message(), 'status_code' => $response->get_error_code() ) );
            return;
        }

        $status_code    = wp_remote_retrieve_response_code( $response );
        $response_data  = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( 200 === $status_code ) {
            $license->update_active_sites( smliser_get_base_address( $callback_url ) );
            $this->log_executed_task( $highest_priority_task, array(
                'comment'       => ! empty( $response_data['data'] ) ? sanitize_text_field( wp_unslash( $response_data['data']['message'] ) ) : 'License Activated, no response from client.', 
                'status_code' => $status_code ) );
        
        } else {
            $reasons        = array( 
                'comment'       => ! empty( $response_data['data'] ) ? sanitize_text_field( wp_unslash( $response_data['data']['message'] ) ) : 'No response from host', 
                'status_code'   => $status_code
            );
            $this->log_executed_task( $highest_priority_task, $reasons );
        }

        delete_transient( 'smliser_server_doing_post' );

        return true;
    }

    /*
    |------------------------------------------
    | TASK MANAGEMENT FEATURE METHODS
    |------------------------------------------
    */
    
    /**
     * Add validation tasks to queue.
     * 
     * @param int $wait_period Wait period.
     * @param array $value an associative array containing task data.
     * @return bool true on success, false otherwise.
     */
    public function add_task_queue( $duration, $value ) {
        // add the duration which is in seconds to the current timestamp to enable us calculate expiry later.
        $duration   = current_time( 'timestamp' ) + $duration;
        $task_queue = get_option( 'smliser_task_queue', array() );
        
        $task_queue[ $duration ] = $value;
       return update_option( 'smliser_task_queue', $task_queue );
    }

   /**
     * Fetch the highest priority task from the task queue based on current timestamp.
     *
     * @return mixed|null Highest priority task value, or null if no valid task is found.
     */
    public function fetch_highest_priority_task() {
        // Retrieve the current timestamp
        $current_time   = current_time( 'timestamp' );
        $task_queue     = get_option( 'smliser_task_queue', array() );
        $the_task       = array();
        $highest_timest = PHP_INT_MAX;
        ksort( $task_queue );

        foreach ( $task_queue as $timestamp => $tasks ) {
    
            if ( $timestamp >= $current_time ) {

                if ( $timestamp < $highest_timest ) {
                    $the_task = reset( $task_queue );
                    unset( $task_queue[$timestamp] );
                    update_option( 'smliser_task_queue', $task_queue );
                    $highest_timest = $timestamp;
                }
             
            } else {

                $this->log_executed_task( $task_queue[$timestamp], array( 'comment' => 'Task time elapsed' ) );
                // Remove expired tasks from the task queue
                unset( $task_queue[ $timestamp ] );
                update_option( 'smliser_task_queue', $task_queue ); // Update the task queue after removal.
            }
        }

        // Return the highest priority task (if found)
        return  $the_task;
    }

    /**
     * Move tasks from the task queue to the 'smliser_task_log' option.
     * 
     * @param array $the_task Associative contanining the missed task.
     * @param array $comment    Comment on logged data.
     * @return void
     */
    public function log_executed_task( $the_task, $comment = array() ) {
        $missed_schedules = $this->get_task_logs();
        
        $missed_schedules[ current_time( 'mysql' ) ] = array(
            'license_id'    => $the_task['license_id'],
            'ip_address'    => $the_task['IP Address'],
            'website'       => $the_task['callback_url'],
            'comment'        => ! empty( $comment['comment'] ) ? $comment['comment'] : 'N/A',
            'status_code'   => ! empty( $comment['status_code'] ) ? $comment['status_code'] : 'N/A',
        );
        
        update_option( 'smliser_task_log', $missed_schedules );
    }

    /**
     * Get license verification log for the last three month.
     * 
     * @return array $schedules An array of task logs
     */
    public function get_task_logs() {
        $schedules  = get_option( 'smliser_task_log', false );
        
        if ( false === $schedules ) {
            return array(); // Returns empty array.
        }

        if ( ! is_array( $schedules ) ) {
            $schedules = (array) $schedules;
        }

        ksort( $schedules );

        foreach ( $schedules as $time => $schedule ) {
            $timestamp  = strtotime( $time );
            $expiration = time() - ( 3 * MONTH_IN_SECONDS );

            if ( $timestamp < $expiration ) {
                unset( $schedules[$time] );
                update_option( 'smliser_task_log', $schedules );
            }

        }

        return $schedules;
    }


    /**
     * Fetch all scheduled tasks from the task queue.
     *
     * @return array Array of all scheduled tasks with their expiration timestamps.
     */
    public function scheduled_tasks() {
        // Retrieve the task queue from the WordPress options
        $task_queue = get_option( 'smliser_task_queue', array() );
        if ( ! empty( $task_queue ) ) {
            ksort( $task_queue );
        }
        
        return $task_queue;
    }

    /*
    |-----------------------------------------------------
    | API ROUTE SERVER METHODS
    | Handle all requests to our registered API routes.
    |-----------------------------------------------------
    */

    /**
     * The license activation endpoint permission handler.
     * 
     * @param WP_REST_Request $request The current request object.
     */
    public static function license_activation_permission_callback( WP_REST_Request $request ) {
        /**
         * Get the Authorization header from the request, this token serves as a CSRF token when
         * posting the result of the license validation to the callback URL.
         *
         */
        $authorization_header = self::instance()->extract_token( $request, 'raw' );
        
        /**
         * Authorization token in this regard is the token from the client
         * that they can use to validate our response in other to avoid CSRF and XSS.
         * Basically, we ensure the security of both our route and the expected result from client.
         * Clients should verify any expected response using the token they provided, if we can't find this token,
         * the license validation cannot proceed.
         */
        if ( empty( $authorization_header ) ) {
            
            $reasons = array(
                '',
                array( 
                    'route'         => Smliser_Stats::$license_activation,
                    'status_code'   => 400,
                    'request_data'  => 'license validation',
                    'response_data' => array( 'reason' => 'No authorization header' )
                )
            );
            do_action( 'smliser_stats', 'denied_access', '', '', $reasons );
           return new WP_Error( 'smliser_rest_error_invalid_authorization', 'CSRF token must be on request header', array( 'status' => 400 ) );
        }

        $service_id     = $request->get_param( 'service_id' );
        $license_key    = $request->get_param( 'license_key' );
        $license        = self::instance()->license->get_license_data( $service_id, $license_key );

        if ( ! $license ) {
 
            $response = new WP_Error( 'license_error', 'Invalid license', array( 'status' => 404 ) );
            $reasons = array(
                '',
                array( 
                    'route'         => Smliser_Stats::$license_activation,
                    'status_code'   => $response->get_error_code(),
                    'request_data'  => 'license validation response',
                    'response_data' => array( 'reason' => $response->get_error_message() )
                )
            );
            do_action( 'smliser_stats', 'denied_access', '', '', $reasons );
    
            return $response;
        }

        $item_id    = $request->get_param( 'item_id' );
        $access     = $license->can_serve_license( $item_id, 'license activation' );

        if ( is_wp_error( $access ) ) {
            $reasons = array(
                '',
                array( 
                    'route'         => Smliser_Stats::$license_activation,
                    'status_code'   => 403,
                    'request_data'  => 'license validation response',
                    'response_data' => array( 'reason' => $access->get_error_message() )
                )
            );
            do_action( 'smliser_stats', 'denied_access', '', '', $reasons );
    
            return $access;
        }

        self::instance()->license = $license;

        return true;
    }

    /**
     * Handling immediate response to license validation request.
     * 
     * @param WP_REST_Request $request The current request object.
     */
    public static function license_activation_response( WP_REST_Request $request ) {
        $service_id     = $request->get_param( 'service_id' );
        $license_key    = $request->get_param( 'license_key' );
        $item_id        = $request->get_param( 'item_id' );
        $callback_url   = $request->get_param( 'callback_url' );
        $token          = smliser_get_auth_token( $request );
        $license        = self::instance()->license;
        $encoded_data   = $license->encode();

        if ( is_wp_error( $encoded_data ) ) {
            $response_data = array(
                'code'      => 'license_server_busy',
                'message'   => 'Server is currently busy please retry. Contact support if the issue persists.'
            );

            $response = new WP_REST_Response( $response_data, 503 );
            $reasons = array(
                '',
                array( 
                    'route'         => 'license-validator',
                    'status_code'   => 503,
                    'request_data'  => 'license validation response',
                    'response_data' => array( 'reason' => $response_data['message'] )
                )
            );
            do_action( 'smliser_stats', 'denied_access', '', '', $reasons );
            $response->header( 'content-type', 'application/json' );
    
            return $response;
        }

        $waiting_period = smliser_wait_period();
        $local_duration = preg_replace( '/\D/', '', $waiting_period );
        // add new task.
        $tasks = array(
            'license_id'    => $license->get_id(),
            'license_key'   => $license_key,
            'IP Address'    => smliser_get_client_ip(),
            'token'         => $token,
            'end_date'      => $license->get_end_date(),
            'callback_url'  => $callback_url,
            'data'          => $encoded_data
        );
        self::$instance->add_task_queue( $local_duration, $tasks );
        unset( $tasks['data'] );
        $additional = array( 'args',  $tasks );

        /**
         * Handles stats sycronization.
         * 
         * @param string $context The context which the hook is fired.
         * @param Smliser_Plugin The plugin object (optional).
         * @param Smliser_License The license object (optional).
         * @param array $additional An associative array(callable => arg)
         *                          only one argument is passed to the callback function
         */
        do_action( 'smliser_stats', 'license_activation', '', $license, $additional );
        $response_data = array(
            'waiting_period'    => $waiting_period,
            'message'           => 'License is being validated',
        );
        $response = new WP_REST_Response( $response_data, 200 );
        $response->header( 'content-type', 'application/json' );

        return $response;
    }

    /**
     * License deactivation permission.
     * We issued an api key as part of the license document during activation and subsequent reauth, same key will be required
     * during remote deactivation.
     * 
     * @param WP_REST_Request $request
     */
    public static function license_deactivation_permission( WP_REST_Request $request ) {
        $api_key    = self::instance()->extract_token( $request, 'raw' );
        $item_id    = $request->get_param( 'item_id' );

        if ( ! smliser_verify_item_token( $api_key, absint( $item_id ) ) ) {
            $reasons = array(
                '',
                array( 
                    'route'         => Smliser_Stats::$license_deactivation,
                    'status_code'   => 401,
                    'request_data'  => 'license deactivation permission ',
                    'response_data' => array( 'reason' => 'API Key not supplied.' )
                )
            );
            do_action( 'smliser_stats', 'denied_access', '', '', $reasons );
            return new WP_Error( 'smliser_rest_error_invalid_authorization', 'Invalid authorization token', array( 'status' => 400 ) );
        }

        $license_key    = $request->get_param( 'license_key' );
        $service_id     = $request->get_param( 'service_id' );
        self::instance()->license   = self::instance()->license->get_license_data( $service_id, $license_key );

        return self::instance()->license !== false;
    }

    /**
     * License deactivation route handler.
     * 
     * @param WP_REST_Request $request
     */
    public static function license_deactivation_response( WP_REST_Request $request ) {
        $license_key    = $request->get_param( 'license_key' );
        $service_id     = $request->get_param( 'service_id' );
        $website_name   = smliser_get_base_address( $request->get_param( 'callback_url' ) );
        $obj            = self::instance()->license;
        $original_status = $obj->get_status();
        $obj->set_status( 'Deactivated' );
        

        if ( $obj->save() ) {
            $response_data = array(
                'success'   => true,
                'message'   => 'License has been deactivated',
                'data'  => array(
                    'license_status'    => $obj->get_status(),
                    'date'              => gmdate( 'Y-m-d' )
                ),
            );
            $obj->set_action( $website_name );
            $additional = array( 'args' => $response_data );
            /**
             * Fires for stats syncronization.
             * 
             * @param string $context The context which the hook is fired.
             * @param string Empty field for license object.
             * @param Smliser_License The license object (optional).
             */
            do_action( 'smliser_stats', 'license_deactivation', '', $obj, $additional );
        } else {
            $response_data = array(
                'success'    => false,
                'message'   => 'Unable to process this request at the moment.',
                'data'  => array(
                    'license_status'    => $original_status,
                    'date'              => gmdate( 'Y-m-d' )
                ),
            );
        }
        $response = new WP_REST_Response( $response_data, 200 );
        $response->header( 'content-type', 'application/json' );

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
    public static function plugin_update_permission_checker( WP_REST_Request $request ) {
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
                $wp_query->set_404();
                status_header( 404, 'File not found' );
                include( get_query_template( '404' ) );
                exit;
            }

            $plugin_obj = new Smliser_Plugin();
            $plugin     = $plugin_obj->get_plugin_by( 'slug', sanitize_and_normalize_path( $plugin_slug . '/' . $plugin_file . '.zip' ) );

            if ( ! $plugin ) {
                $wp_query->set_404();
                status_header( 404, 'File not found' );
                include( get_query_template( '404' ) );
                exit;
            }

            /**
             * Serve download for licensed plugin
             */
            if ( $this->is_licensed( $plugin ) ) {
                $api_key    = ! empty( $wp_query->query_vars['download_token'] ) ? sanitize_text_field( wp_unslash( $wp_query->query_vars['download_token'] ) ) : '';

                // If not provided in the url, we check in the header.
                if ( empty( $api_key ) ) {
                    $authorization = smliser_get_authorization_header();
                    if ( $authorization ) {
                        $parts = explode( ' ', $authorization );
                        $api_key = $parts[1];
                    }
                }

                if ( empty( $api_key ) ) {
                    wp_die( 'Licensed plugin, please provide token', 401 );
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
