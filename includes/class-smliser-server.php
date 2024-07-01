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

class Smliser_Server{

    /**
     * Remote validation tasks.
     * 
     * @var array $tasks
     */
    private $tasks = array();

    /**
     * Single instance of current class.
     * 
     * @var Smliser_Server $instance Instance.
     */
    private static $instance;

    /**
     * Class constructor.
     */
    public function __construct() {
        add_action( 'smliser_validate_license', array( $this, 'remote_validate' ) );
        add_action( 'template_redirect', array( $this, 'serve_package_download' ) );
        add_filter( 'template_include', array( $this, 'load_auth_template' ) );
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
        
        // Extract task data
        $callback_url   = isset( $highest_priority_task['callback_url'] ) ? sanitize_text_field( $highest_priority_task['callback_url'] ) : '';
        $license_key    = isset( $highest_priority_task['license_key'] ) ? sanitize_text_field( $highest_priority_task['license_key'] ) : '';
        $license_id     = isset( $highest_priority_task['license_id'] ) ? absint( $highest_priority_task['license_id'] ) : 0;
        $token          = isset( $highest_priority_task['token'] ) ? sanitize_text_field( $highest_priority_task['token'] ) : '';
        $data           = isset( $highest_priority_task['data'] ) ? sanitize_text_field( $highest_priority_task['data'] ) : '';
        $end_date       = isset( $highest_priority_task['end_date'] ) ? sanitize_text_field( $highest_priority_task['end_date'] ) : '' ;
    
        // Ensure task data is valid.
        if ( empty( $license_key ) || empty( $token ) || empty( $data ) ) {
            return;
        }

        // No matter the task, the license must be valid first.
        $license    = Smliser_license::get_by_id( absint( $license_id ) );
        if ( ! $license ) {
            return;
        }

        $expires_after  = strtotime( $end_date ) - current_time( 'timestamp' );

        if ( smliser_is_empty_date( $end_date ) ) {
            // Maybe it's a lifetime license.
            $status = $license->get_status();
            if ( 'Active' === $status ) {
                $expires_after = MONTH_IN_SECONDS; // Will require routine checks though.
            }
        }
        
        $request_args   = array(
            'action'        => 'verified_license',
            'last_updated'  => $expires_after, 
            'license_key'   => $license_key,
            'token'         => $token,
            'API_KEY'       => smliser_generate_api_key( $license->get_item_id(), $license_key ),
            'data'          => $data,
        );

        $request_body = array(
            'body' => $request_args,
        );

        $response = wp_remote_post( $callback_url, $request_body );
        // Check if remote request was successful
        if ( is_wp_error( $response ) ) {
            delete_transient( 'smliser_server_doing_post' );
            return new WP_Error( 'remote_request_failed', 'Failed to execute remote POST request.' );
        }

        if ( wp_remote_retrieve_response_code( $response ) === 200 ) {
            $license->update_active_sites( get_base_address( $callback_url ) );
        }

        $this->post_completed( $highest_priority_task );
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
     */
    public function add_task_queue( $duration, $value ) {
        // add the duration which is in seconds to the current timestamp to enable us calculate expiry later.
        $duration   = current_time( 'timestamp' ) + $duration;
        $task_queue = get_option( 'smliser_task_queue', array() );
        
        $task_queue[ $duration ] = array( $value );
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

        // Iterate through each task in the task queue
        foreach ( $task_queue as $timestamp => $tasks ) {
    
            if ( $timestamp >= $current_time ) {
                // Update the highest priority task if the current task's timestamp is closer to the current time
                if ( $timestamp < $highest_timest ) {
                    $the_task = reset( $tasks );
                    unset( $task_queue[ $timestamp ] );
                    update_option( 'smliser_task_queue', $task_queue );
                    $highest_timest = $timestamp;
                }
             
            } else {

                // move expired tasks to missed schedules.
                $this->move_to_missed_schedules( $timestamp, $tasks );
                // Remove expired tasks from the task queue
                unset( $task_queue[ $timestamp ] );
                update_option( 'smliser_task_queue', $task_queue ); // Update the task queue after removal.
            }
        }

        // Return the highest priority task (if found)
        return  $the_task;
    }

    /**
     * Move expired tasks from the task queue to the 'smliser_missed_schedules' option.
     *
     * @return void
     */
    public function move_to_missed_schedules( $timestamp, $tasks ) {
        $missed_schedules = $this->get_missed_schedules();
        $missed_schedules[ $timestamp ] = $tasks;
        set_transient( 'smliser_missed_schedules', $missed_schedules, DAY_IN_SECONDS );
    }

    /**
     * Get missed task for the last 24hrs
     */
    public function get_missed_schedules() {
        $schedules = get_transient( 'smliser_missed_schedules' );
        
        if ( false === $schedules ) {
            return array();
        }
        ksort( $schedules );
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
     * Check license activation permission
     * 
     * @param WP_REST_Request $request The current request object.
     */
    public static function validation_permission( $request ) {
        // Get the Authorization header from the request.
        $authorization_header = $request->get_header( 'authorization' );
        $service_id     =  ! empty( $request->get_param( 'service_id' ) ) ? urldecode( $request->get_param( 'service_id' ) ) : '';
        $item_id        =  ! empty( $request->get_param( 'item_id' ) ) ? urldecode( $request->get_param( 'item_id' ) ) : '';
        $license_key    =  ! empty( $request->get_param( 'license_key' ) ) ? urldecode( $request->get_param( 'license_key' ) ) : '';
        $callback_url   =  ! empty(  $request->get_param( 'callback_url' ) ) ? urldecode( $request->get_param( 'callback_url' ) ) : '';
        
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
                    'status_code'   => 401,
                    'request_data'  => 'license validation',
                    'response_data' => array( 'reason' => 'No authorization header' )
                )
            );
            do_action( 'smliser_stats', 'denied_access', '', '', $reasons );
           return false;
        }

        $authorization_parts = explode( ' ', $authorization_header );

        /** 
         * The authorization token should be prefixed with Bearer string. 
         */
        if ( count( $authorization_parts ) !== 2 && $authorization_parts[0] !== 'Bearer' ) {
            $reasons = array(
                '',
                array( 
                    'route'         => Smliser_Stats::$license_activation,
                    'status_code'   => 401,
                    'request_data'  => 'license validation',
                    'response_data' => array( 'reason' => 'No authorization header' )
                )
            );
            do_action( 'smliser_stats', 'denied_access', '', '', $reasons );
           return false;
        }

        /**
         * Required parameters expected during license validation are as follows.
         * 
         * @param string    service_id      The Service ID associated with the license.
         * @param int       item_id         The id of the licensed item.
         * @param string    license_key     The license Key.
         * @param string    callback_url    The client's callback URL where the license documents will be posted after we are done with the validation.
         * @return bool    False | continue
         */
        if ( 
            empty( $service_id ) 
            || empty( $item_id ) 
            || empty( $license_key ) 
            || empty( $callback_url ) 
            ) {
                $reasons = array(
                    '',
                    array( 
                        'route'         => Smliser_Stats::$license_activation,
                        'status_code'   => 401,
                        'request_data'  => 'license validation',
                        'response_data' => array( 'reason' => 'Required parameters not set.' )
                    )
                );
                do_action( 'smliser_stats', 'denied_access', '', '', $reasons );
            return false;
        }

        // We need to ensure the param inputs are not ill-intended.
         if ( 
            $service_id !== sanitize_text_field( $service_id )
            || $item_id !== sanitize_text_field( $item_id )
            || $license_key !== sanitize_text_field( $license_key ) 
            || ! filter_var( $callback_url, FILTER_VALIDATE_URL )
        ) {
            $reasons = array(
                '',
                array( 
                    'route'         => Smliser_Stats::$license_activation,
                    'status_code'   => 401,
                    'request_data'  => 'license validation',
                    'response_data' => array( 'reason' => 'Suspicious parameters supplied.' )
                )
            );
            do_action( 'smliser_stats', 'denied_access', '', '', $reasons );
            return false;
        }
        
        /** 
         * Since the basic requirement and validation convention is met,
         * client can access this endpoint.
         */
        return true;
    }

    /**
     * Handling immediate response to validation request.
     * 
     * @param WP_REST_Request $request The current request object.
     */
    public static function validation_response( $request ) {
        $request_params = $request->get_params();
        $service_id     = $request_params['service_id'];
        $license_key    = $request_params['license_key'];
        $item_id        = $request_params['item_id'];
        $callback_url   = $request_params['callback_url'];
        $token          = smliser_get_auth_token( $request );
        $smlicense      = Smliser_license::instance();
        $license        = $smlicense->get_license_data( $service_id, $license_key );
        
        if ( ! $license ) {
            $response_data = array(
                'code'      => 'license_error',
                'message'   => 'Invalid License key or service ID.'
            );
            $response = new WP_REST_Response( $response_data, 404 );
            $reasons = array(
                '',
                array( 
                    'route'         => Smliser_Stats::$license_activation,
                    'status_code'   => 404,
                    'request_data'  => 'license validation response',
                    'response_data' => array( 'reason' => $response_data['message'] )
                )
            );
            do_action( 'smliser_stats', 'denied_access', '', '', $reasons );
            $response->header( 'Content-Type', 'application/json' );
    
            return $response;
        }

        $access = $license->can_serve_license( $item_id, 'license activation' );

        if ( is_wp_error( $access ) ) {
            $response_data = array(
                'code'      => 'license_error',
                'message'   => $access->get_error_message()
            );
            $response = new WP_REST_Response( $response_data, 403 );
            $reasons = array(
                '',
                array( 
                    'route'         => Smliser_Stats::$license_activation,
                    'status_code'   => 403,
                    'request_data'  => 'license validation response',
                    'response_data' => array( 'reason' => $response_data['message'] )
                )
            );
            do_action( 'smliser_stats', 'denied_access', '', '', $reasons );
            $response->header( 'Content-Type', 'application/json' );
    
            return $response;
        }
    
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
            $response->header( 'Content-Type', 'application/json' );
    
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
        $response->header( 'Content-Type', 'application/json' );

        return $response;
    }

    /**
     * Deactivation permission.
     */
    public static function deactivation_permission( $request ) {
        // Retrieve the data.
        $api_key    = sanitize_text_field( smliser_get_auth_token( $request ) );
        $item_id    = ! empty( $request->get_param( 'item_id' ) ) ? sanitize_text_field( urldecode( $request->get_param( 'item_id' ) )  ) : '';

        if ( empty( $api_key ) ) {
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
            return false;
        }
        $license_key    = ! empty( $request->get_param( 'license_key' ) ) ? sanitize_text_field( urldecode( $request->get_param( 'license_key' ) ) ) : '';
        $service_id     = ! empty( $request->get_param( 'service_id') ) ? sanitize_text_field( urldecode( $request->get_param( 'service_id') ) ) : '';
        
        if ( empty( $service_id ) || empty( $license_key ) ) {
            $reasons = array(
                '',
                array( 
                    'route'         => Smliser_Stats::$license_deactivation,
                    'status_code'   => 401,
                    'request_data'  => 'license deactivation permission ',
                    'response_data' => array( 'reason' => 'Service ID or License key not supplied.' )
                )
            );
            do_action( 'smliser_stats', 'denied_access', '', '', $reasons );
            return false;
        }
        
        $obj        = new Smliser_license();
        $license    = $obj->get_license_data( $service_id, $license_key );

        if ( empty( $license ) ) {
            $reasons = array(
                '',
                array( 
                    'route'         => Smliser_Stats::$license_deactivation,
                    'status_code'   => 401,
                    'request_data'  => 'license deactivation permission ',
                    'response_data' => array( 'reason' => 'License not found' )
                )
            );
            do_action( 'smliser_stats', 'denied_access', '', '', $reasons );
            return false;
        }

        return smliser_verify_api_key( $api_key, $item_id );
    }

    /**
     * License deactivation route handler.
     */
    public static function deactivation_response( $request ) {
        $license_key    = sanitize_text_field( urldecode( $request->get_param( 'license_key' ) ) );
        $service_id     = sanitize_text_field( urldecode( $request->get_param( 'service_id') ) );
        $website_name   = sanitize_url( get_base_address( urldecode( $request->get_param( 'client' ) ) ), array( 'http', 'https' ) );
        $instance       = Smliser_license::instance();
        $obj            = $instance->get_license_data( $service_id, $license_key );

        if ( empty( $obj ) ) {
            $response_data = array(
                'status'    => 'failed',
                'message'   => 'License does not exist.'
            );

            $response = new WP_REST_Response( $response_data, 404 );
            $reasons = array(
                '',
                array( 
                    'route'         => Smliser_Stats::$license_deactivation,
                    'status_code'   => 404,
                    'request_data'  => 'license deactivation response',
                    'response_data' => array( 'reason' => $response_data['message'] )
                )
            );
            do_action( 'smliser_stats', 'denied_access', '', '', $reasons );
            $response->header( 'Content-Type', 'application/json' );
            return $response;
        }

        $obj->set_action( 'deactivate' );
        $obj->do_action();
        $response_data = array(
            'status'    => 'success',
            'message'   => 'License has been deactivated',
        );

        $obj->set_action( $website_name );
    
        $response = new WP_REST_Response( $response_data, 200 );
        $response->header( 'Content-Type', 'application/json' );
        $additional = array( 'args' => $response_data );
        /**
         * Fires for stats syncronization.
         * 
         * @param string $context The context which the hook is fired.
         * @param string Empty field for license object.
         * @param Smliser_License The license object (optional).
         */
        do_action( 'smliser_stats', 'license_deactivation', '', $obj, $additional );

        return $response;
    }

    /**
     * Update permission checker.
     */
    public static function update_permission( $request ) {
        $item_id    = absint( $request->get_param( 'item_id' ) );
        $api_key    = sanitize_text_field( smliser_get_auth_token( $request ) );

        if ( ! smliser_verify_api_key( $api_key, $item_id ) ) {
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
            return false;
        }

        return true;
    }

    /**
     * Update response route handler
     */
    public static function update_response( $request ) {
        $item_id    = absint( $request->get_param( 'item_id' ) );
        
        if ( self::$instance->is_licensed( $item_id ) ) {
            $license_key    = sanitize_text_field( $request->get_param( 'license_key' ) );
            $service_id     = sanitize_text_field( $request->get_param( 'service_id' ) );
            $instance       = Smliser_license::instance();
            $license        = $instance->get_license_data( $service_id, $license_key );
    
            if ( empty( $license ) || is_wp_error( $license->can_serve_license( $item_id ) ) ) {
                $response_data = array(
                    'code'      => 'license_error',
                    'message'   => 'You are not allowed to perform updates'
                );
                $reasons = array(
                    '',
                    array( 
                        'route'         => Smliser_Stats::$plugin_update,
                        'status_code'   => 403,
                        'request_data'  => 'Plugin Update response',
                        'response_data' => array( 'reason' => $response_data['message'] )
                    )
                );
                do_action( 'smliser_stats', 'denied_access', '', '', $reasons );
                $response = new WP_REST_Response( $response_data, 403 );
                $response->header( 'Content-Type', 'application/json' );
        
                return $response;
            }
        }

        $plugin_id  = $item_id;
        $pl_obj     = new Smliser_Plugin();
        $the_plugin = $pl_obj->get_plugin( $plugin_id );

        if ( ! $the_plugin ){
            $response_data = array(
                'code'      => 'plugin_not_found',
                'message'   => 'The plugin was not found'
            );
            $response = new WP_REST_Response( $response_data, 404 );
            $reasons = array(
                '',
                array( 
                    'route'         => Smliser_Stats::$plugin_update,
                    'status_code'   => 403,
                    'request_data'  => 'Plugin update response',
                    'response_data' => array( 'reason' => $response_data['message'] )
                )
            );
            do_action( 'smliser_stats', 'denied_access', '', '', $reasons );
            $response->header( 'Content-Type', 'application/json' );
    
            return $response;
        }
        
        /**
         * Fires for stats syncronization.
         * 
         * @param string $context The context which the hook is fired.
         * @param Smliser_Plugin.
         */
        do_action( 'smliser_stats', 'plugin_update', $the_plugin );
        $response = new WP_REST_Response( $the_plugin->formalize_response(), 200 );
        $response->header( 'Content-Type', 'application/json' );
        return $response;

    }

    /**
     * Repository REST API Route permission handler
     * 
     * @param WP_REST_Request $request The current request object.
     */
    public static function repository_access_permission( $request ) {


        return true;
    }

    /**
     * Repository REST API handler
     * 
     * @param WP_REST_Request $request The current request object.

     */
    public static function repository_response( $request ) {
        $slug = $request->get_param( 'slug' );
        $scope = $request->get_param( 'scope' );
    
        // Handle the request with the slug and scope values
        $response = array(
            'slug' => $slug,
            'scope' => $scope,
        );
    
        return new WP_REST_Response( $response, 200 );
    }

    /**
     * Serve plugin Download.
     */
    public function serve_package_download() {
        global $wp_query, $smliser_repo;

        if ( isset( $wp_query->query_vars['plugin_slug'] ) && isset( $wp_query->query_vars['plugin_file'] ) ) {
            $api_key        = sanitize_text_field( $wp_query->query_vars['api_key'] );
            $plugin_slug    = sanitize_text_field( $wp_query->query_vars['plugin_slug'] );
            $plugin_file    = sanitize_text_field( $wp_query->query_vars['plugin_file'] );
            
            if ( $plugin_slug !== $plugin_file ) {
                $wp_query->set_404();
                status_header( 404 );
                include( get_query_template( '404' ) );
                exit;
            }

            $plugin_obj = new Smliser_Plugin();
            $plugin     = $plugin_obj->get_plugin_by( 'slug', sanitize_and_normalize_path( $plugin_slug . '/' . $plugin_file . '.zip' ) );

            if ( ! $plugin ) {
                $wp_query->set_404();
                status_header( 404 );
                include( get_query_template( '404' ) );
                exit;
            }

            $item_id = $plugin->get_item_id();

            if ( ! smliser_verify_api_key( $api_key, $item_id ) ) {
                $wp_query->set_404();
                status_header( 404 );
                include( get_query_template( '404' ) );
                exit;
            }

            $slug           = sanitize_and_normalize_path( trailingslashit( $plugin_slug ) . $plugin_file . '.zip' );
            $plugin_path    = trailingslashit( $smliser_repo->get_repo_dir() ) . $slug;       
            
            if ( ! file_exists( $plugin_path ) ) {
                $wp_query->set_404();
                status_header( 404 );
                include( get_query_template( '404' ) );
                exit;
            }
    
            // Serve the file for download.
            if ( $smliser_repo->is_readable( $plugin_path ) ) {

                /**
                 * Fires for stats syncronization.
                 * 
                 * @param string $context The context which the hook is fired.
                 * @param Smliser_Plugin The plugin object (optional).
                 */
                do_action( 'smliser_stats', 'plugin_download', $plugin );

                header( 'Content-Description: File Transfer' );
                header( 'Content-Type: application/zip' );
                header( 'Content-Disposition: attachment; filename="' . basename( $plugin_path ) . '"' );
                header( 'Expires: 0' );
                header( 'Cache-Control: must-revalidate' );
                header( 'Pragma: public' );
                header( 'Content-Length: ' . $smliser_repo->size( $plugin_path ) );
                $smliser_repo->readfile( $plugin_path );
                exit;
            } else {
                wp_die( 'Error: The file cannot be read.' );
            }
        }
    }

    /**
     * Authentication Tempalte file loader
     */
    public function load_auth_template( $template ) {
        global $wp_query;
        if ( isset( $wp_query->query_vars[ 'smliser_auth' ] ) ) {

            $login_form    = SMLISER_PATH . 'templates/auth/auth-login.php';
            $auth_template = SMLISER_PATH . 'templates/auth/auth-temp.php';
            
            if ( ! is_user_logged_in() ) {
                return $login_form;
            } else {
                return $auth_template;
            }
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
     */
    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
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

}

Smliser_Server::instance();
