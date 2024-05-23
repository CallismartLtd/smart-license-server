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

class SmartWoo_License_Server{

    private $tasks = array();
    private static $instance;

    public function __construct() {
        add_action( 'smartwoo_server_valid_license', array( $this, 'remote_validate' ) );
        add_filter( 'cron_schedules', array( $this, 'register_cron' ) );
        add_action( 'init', array( $this, 'run_automation' ) );

    }

    public function register_cron( $schedules ) {
        /** Add a new cron schedule interval for every 5 minutes. */
        $schedules['smartwoo_license_server_five_minutely'] = array(
            'interval' => 5 * MINUTE_IN_SECONDS,
            'display'  => 'Five Minutely',
        );
        return $schedules;
    }
    
    public function run_automation() {

        if ( ! wp_next_scheduled( 'smartwoo_server_valid_license' ) ) {
			wp_schedule_event( current_time( 'timestamp' ), 'smartwoo_license_server_five_minutely', 'smartwoo_server_valid_license' );
		}

    }

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
        $callback_url   = isset( $highest_priority_task['callback_url'] ) ? $highest_priority_task['callback_url'] : '';
        $licence_key = isset( $highest_priority_task['licence_key'] ) ? $highest_priority_task['licence_key'] : '';
        $token = isset( $highest_priority_task['token'] ) ? $highest_priority_task['token'] : '';
        $data = isset( $highest_priority_task['data'] ) ? $highest_priority_task['data'] : '';

        // Ensure task data is valid.
        if ( empty( $licence_key ) || empty( $token ) || empty( $data ) ) {

            return;
        }
        
        $expires_after  = strtotime( $highest_priority_task['expiry_date'] ) - current_time( 'timestamp' );
        $request_body   = array(
            'action'        => 'verified_license',
            'last_updated'  => $expires_after, 
            'licence_key'   => $licence_key,
            'token'         => $token,
            'data'          => $data,
        );

        // Prepare request arguments for wp_remote_post()
        $request_args = array(
            'body' => $request_body,
        );

        // Execute remote POST request using wp_remote_post()
        $response = wp_remote_post( $callback_url, $request_args );

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
        delete_transient( 'smartwoo_server_doing_post' );

        return true;
    }

    public function doing_post() {
        set_transient( 'smartwoo_server_doing_post', true, MINUTE_IN_SECONDS );
    }

    public function is_doing_post() {
        return get_transient( 'smartwoo_server_doing_post', true );
    }


    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
    }

    public function add_task_queue( $duration, $value ) {
        // add the duration which is in seconds to the current timestamp to enable us calculate expiry later.
        $duration   = current_time( 'timestamp' ) + $duration;
        $task_queue = get_option( 'smartwoo_task_queue', array() );
        
        // Any existing duplicate key(which is rear though) should be overwriten.
        $task_queue[ $duration ] = array( $value );
       return update_option( 'smartwoo_task_queue', $task_queue );
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
        $task_queue = get_option( 'smartwoo_task_queue', array() );

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
                    update_option( 'smartwoo_task_queue', $task_queue );
                    $highest_priority_timestamp = $timestamp;
                }
             
            } else {

                // move expired tasks to missed schedules.
                $this->move_to_missed_schedules( $timestamp, $tasks );
                // Remove expired tasks from the task queue
                unset( $task_queue[ $timestamp ] );
                update_option( 'smartwoo_task_queue', $task_queue ); // Update the task queue after removal
            }
        }

        // Return the highest priority task (if found)
        return  $highest_priority_task;
    }

    /**
     * Move expired tasks from the task queue to the 'smartwoo_missed_schedules' option.
     *
     * @return void
     */
    public function move_to_missed_schedules( $timestamp, $tasks ) {
        
        $missed_schedules[ $timestamp ] = $tasks;
        update_option( 'smartwoo_missed_schedules', $missed_schedules );
    }


    /**
     * Fetch all scheduled tasks from the task queue.
     *
     * @return array Array of all scheduled tasks with their expiration timestamps.
     */
    public function fetch_all_scheduled_tasks() {
        // Retrieve the task queue from the WordPress options
        $task_queue = get_option( 'smartwoo_task_queue', array() );

        // Return the entire task queue (tasks and their expiration timestamps)
        return $task_queue;
    }
}

SmartWoo_License_Server::instance();
