<?php
/**
 * file name class-license-server.php
 * Licenser server class file
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

    }

    /**
     * Provide validation of license data to client's website.
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
            'API_KEY'       => smliser_generate_api_key( $license->get_service_id() ),
            'data'          => $data,
        );

        $request_body = array(
            'body' => $request_args,
        );

        $response = wp_remote_post( $callback_url, $request_body );
        // Check if remote request was successful
        if ( is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) !== 200 ) {

            return new WP_Error( 'remote_request_failed', 'Failed to execute remote POST request.' );
        }

        // Task completed successfully, update status and store in 'completed_tasks'
        $highest_priority_task['status'] = 'completed';

        // Retrieve or initialize 'completed_tasks' option
        $completed_tasks = get_option( 'completed_tasks', array() );

        // Add completed task to 'completed_tasks' option
        $completed_tasks[] = $highest_priority_task;

        // Update 'completed_tasks' option
        update_option( 'completed_tasks', $completed_tasks );
        delete_transient( 'smliser_server_doing_post' );

        return true;
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
     * Add validation tasks to queue.
     */
    public function add_task_queue( $duration, $value ) {
        // add the duration which is in seconds to the current timestamp to enable us calculate expiry later.
        $duration   = current_time( 'timestamp' ) + $duration;
        $task_queue = get_option( 'smliser_task_queue', array() );
        
        // Any existing duplicate key(which is rear though) should be overwriten.
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
        $current_time = current_time( 'timestamp' );

        // Retrieve the task queue from the WordPress options
        $task_queue = get_option( 'smliser_task_queue', array() );

        // Initialize variables for the highest priority task and its expiration timestamp
        $highest_priority_task = null;
        $highest_priority_timestamp = PHP_INT_MAX; // Start with the highest possible integer value
        $tsp = '';

        // Iterate through each task in the task queue
        foreach ( $task_queue as $timestamp => $tasks ) {
            // Check if the task's expiration timestamp is valid (greater than or equal to current time)
            if ( $timestamp >= $current_time ) {
                // Update the highest priority task if the current task's timestamp is closer to the current time
                if ( $timestamp < $highest_priority_timestamp ) {
                    $highest_priority_task = reset( $tasks );
                    unset( $task_queue[ $timestamp ] );
                    update_option( 'smliser_task_queue', $task_queue );
                    $highest_priority_timestamp = $timestamp;
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
        return  $highest_priority_task;
    }

    /**
     * Move expired tasks from the task queue to the 'smliser_missed_schedules' option.
     *
     * @return void
     */
    public function move_to_missed_schedules( $timestamp, $tasks ) {
        
        $missed_schedules[ $timestamp ] = $tasks;
        update_option( 'smliser_missed_schedules', $missed_schedules );
    }


    /**
     * Fetch all scheduled tasks from the task queue.
     *
     * @return array Array of all scheduled tasks with their expiration timestamps.
     */
    public function fetch_all_scheduled_tasks() {
        // Retrieve the task queue from the WordPress options
        $task_queue = get_option( 'smliser_task_queue', array() );

        // Return the entire task queue (tasks and their expiration timestamps)
        return $task_queue;
    }

    /**
     * Check license activation permission
     */
    public static function validation_permission( $request ) {
        // Get the Authorization header from the request.
        $authorization_header = $request->get_header( 'authorization' );
        $service_id     =  $request->get_param( 'service_id' );
        $item_id        =  $request->get_param( 'item_id' );
        $license_key    =  $request->get_param( 'license_key' );
        $callback_url   =  $request->get_param( 'callback_url' );
        
        /**
         * Authorization token in this regard is the token from the client
         * server that they can use to validate our response in other to avoid CSRF and XSS.
         * Basically, we ensure the security of both our server and the client's too.
         */
        if ( empty( $authorization_header ) ) {
           return false;
        }

        $authorization_parts = explode( ' ', $authorization_header );

        // Additional checks.
        if ( count( $authorization_parts ) !== 2 && $authorization_parts[0] !== 'Bearer' ) {
           return false;
        }

        // All required parameters must be met.
        if ( 
            empty( $service_id ) 
            || empty( $item_id ) 
            || empty( $license_key ) 
            || empty( $callback_url ) 
            ) {
            return false;
        }

        // We need to ensure the param inputs are not ill-intended.
         if ( 
            $service_id !== sanitize_text_field( $service_id )
            || $item_id !== sanitize_text_field( $item_id )
            || $license_key !== sanitize_text_field( $license_key ) 
            || ! filter_var( $callback_url, FILTER_VALIDATE_URL )
        ) {
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
            $response->header( 'Content-Type', 'application/json' );
    
            return $response;
        }

        if ( absint( $item_id ) !== absint( $license->get_item_id() ) ) {
            $response_data = array(
                'code'      => 'license_error',
                'message'   => 'Invalid License key or service ID.'
            );
            $response = new WP_REST_Response( $response_data, 404 );
            $response->header( 'Content-Type', 'application/json' );
    
            return $response;
        }
    
        if ( 'Expired' === $license->get_status() ) {
            $response_data = array(
                'code'      => 'license_expired',
                'message'   => 'License has expired, log into you account to renew your license.'
            );
            $response = new WP_REST_Response( $response_data, 402 );
            $response->header( 'Content-Type', 'application/json' );
    
            return $response;
        }

        if ( 'Suspended' === $license->get_status() ) {
            $response_data = array(
                'code'      => 'license_suspended',
                'message'   => 'License has been suspended, log into you account to resolve issues.'
            );
            $response = new WP_REST_Response( $response_data, 403 );
            $response->header( 'Content-Type', 'application/json' );
    
            return $response;
        }

        if ( 'Revoked' === $license->get_status() ) {
            $response_data = array(
                'code'      => 'license_revoked',
                'message'   => 'License is currently revoked, log into you account to resolve issues.'
            );
            $response = new WP_REST_Response( $response_data, 403 );
            $response->header( 'Content-Type', 'application/json' );
    
            return $response;
        }

        if ( 'Deactivated' === $license->get_status() ) {
            $response_data = array(
                'code'      => 'license_deactivated',
                'message'   => 'License has been deactivated, log into you account to regenrate or purchase new one.'
            );
            $response = new WP_REST_Response( $response_data, 403 );
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
            $response->header( 'Content-Type', 'application/json' );
    
            return $response;
        }

        $waiting_period = smliser_wait_period();
        $local_duration = preg_replace( '/\D/', '', $waiting_period );
        // add new task.
        $license_server = new Smliser_Server();
        $license_server->add_task_queue(
            $local_duration, array(
                'license_id'    => $license->get_id(),
                'license_key'   => $license_key,
                'token'         => $token,
                'end_date'      => $license->get_end_date(),
                'callback_url'  => $callback_url,
                'data'          => $encoded_data

            )
        );
    
    
        $response_data = array(
            'waiting_period'    => $waiting_period,
            'message'           => 'License is being validated',
        );
        $response = new WP_REST_Response( $response_data, 200 );
        return $response;
    }

    /**
     * Deactivation permission.
     */
    public static function deactivation_permission( $request ) {
        // Retrieve the datas.
        $license_key    = sanitize_text_field( urldecode( $request->get_param( 'license_key' ) ) );
        $service_id     = sanitize_text_field( urldecode( $request->get_param( 'service_id') ) );
        
        if ( empty( $service_id ) || empty( $license_key ) ) {
            return false;
        }
        
        $obj        = new Smliser_license();
        $license    = $obj->get_license_data( $service_id, $license_key );

        if ( empty( $license ) ) {
            return false;
        }

        return true;
    }

    /**
     * License deactivation route handler.
     */
    public static function deactivation_response( $request ) {
        $license_key    = sanitize_text_field( urldecode( $request->get_param( 'license_key' ) ) );
        $service_id     = sanitize_text_field( urldecode( $request->get_param( 'service_id') ) );
        $instance       = Smliser_license::instance();
        $obj            = $instance->get_license_data( $service_id, $license_key );
        $obj->set_action( 'deactivate' );
        $obj->do_action();
        $response_data = array(
            'status'    => 'success',
            'message'   => 'License has been deactivated',
        );
    
        $response = new WP_REST_Response( $response_data, 200 );
        $response->header( 'Content-Type', 'application/json' );
        return $response;
    }

    /**
     * Update permission checker.
     */
    public static function update_permission( $request ) {
        $service_id = sanitize_text_field( $request->get_param( 'service_id' ) );
        $api_key    = sanitize_text_field( smliser_get_auth_token( $request ) );

        if ( ! smliser_verify_api_key( $api_key, $service_id ) ) {
            return false;
        }

        return true;
    }

    /**
     * Update response route handler
     */
    public static function update_response( $request ) {
        $item_id        = absint( $request->get_param( 'item_id' ) );
        $license_key    = sanitize_text_field( $request->get_param( 'license_key' ) );
        $service_id     = sanitize_text_field( $request->get_param( 'service_id' ) );
        $instance       = Smliser_license::instance();
        $license        = $instance->get_license_data( $service_id, $license_key );

        if ( empty( $license ) || is_wp_error( $license->can_serve_license( $item_id ) ) ) {
            $response_data = array(
                'code'      => 'license_error',
                'message'   => 'You are not allowed to perform updates'
            );
            $response = new WP_REST_Response( $response_data, 401 );
            $response->header( 'Content-Type', 'application/json' );
    
            return $response;
        }

        global $smliser_repo;
        $plugin_id  = $license->get_item_id();
        $pl_obj = new Smliser_Plugin();
        $the_plugin = $pl_obj->get_plugin( $plugin_id );

        if ( ! $the_plugin ){
            $response_data = array(
                'code'      => 'plugin_not_found',
                'message'   => 'The plugin was not found'
            );
            $response = new WP_REST_Response( $response_data, 404 );
            $response->header( 'Content-Type', 'application/json' );
    
            return $response;
        }
        
        $response = new WP_REST_Response( $the_plugin->encode(), 200 );
        $response->header( 'Content-Type', 'application/json' );
        return $response;

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


            if ( ! smliser_verify_api_key( $api_key, $service_id ) ) {
                wp_die( '', 401 );
            }
            
            $slug           = trailingslashit( $plugin_slug ) . $plugin_file . '.zip';
            $plugin_path    = $smliser_repo->get_repo_dir() . '/' . $slug;       
            
            if ( ! file_exists( $plugin_path ) ) {
                wp_die('', 403 );
            }
    
            // Serve the file for download
            if ( is_readable( $plugin_path ) ) {
                header( 'Content-Description: File Transfer' );
                header( 'Content-Type: application/zip' );
                header( 'Content-Disposition: attachment; filename="' . basename( $plugin_path ) . '"' );
                header( 'Expires: 0' );
                header( 'Cache-Control: must-revalidate' );
                header( 'Pragma: public' );
                header( 'Content-Length: ' . filesize( $plugin_path ) );
                readfile( $plugin_path );
                exit;
            } else {
                wp_die( 'Error: The file cannot be read.' );
            }
        }
    }
}

Smliser_Server::instance();
