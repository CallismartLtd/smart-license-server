<?php
/**
 * filename class-smliser-plugin-stats.php
 * 
 * @author Callistus
 * @since 1.0.0
 * @package Smliser\classes
 */

defined( 'ABSPATH' ) || exit;

class Smliser_Stats {
    /**
     * ID
     * 
     * @var int $id The database table id of a stat
     */
    /**
     * Plugin
     * 
     * @var Smliser_Plugin $plugin The instance of Smliser Plugin.
     */
    private $plugin;

    /**
     * License
     * 
     * @var Smliser_license $license The license object.
     */
    private $license;

    /**
     * Endpoint Access log.
     * 
     * @var array $access_log Log for each endpoint access.
     */
    private $access_log = array(
        'validation'    => 0,
        'deactivation'  => 0,
        'plugin_update' => 0,
        'download_log'  => 0,
    );

    /**
     * Response Log.
     * 
     * @var array $response_log Log for responses given.
     */
    private $response_log = array(
        'time'          => '',
        'status code'   => 0,
        'client IP'     => 0,
        'website'       => '',
    );

    /**
     * Instance of current class.
     * 
     * @var Smliser_Stats
     */
    private static $instance = null;

    /**
     * Class constructor
     */
    public function __construct() {
        $this->plugin   = new Smliser_Plugin();
        $this->license  = new Smliser_license();
    }

    /**
     * Static instance of current class.
     */
    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Plugin property getter
     */
    public function plugin() {
        return $this->plugin;
    }

    /**
     * License Property getter
     */
    public function license() {
        return $this->license;
    }

    /*
    |---------
    | SETTERS
    |---------
    */

    /**
     * Set access log for a property
     * 
     * @param array $log Data to log.
     */
    public function set_access_log( $log ) {
        if ( isset( $log['validation'] ) ) {
            $this->access_log['validation'] = sanitize_text_field( $log['validation'] );
        }

        if ( isset( $log['deactivation'] ) ) {
            $this->access_log['deactivation'] = sanitize_text_field( $log['deactivation'] );
        }

        if ( isset( $log['plugin_update'] ) ) {
            $this->access_log['plugin_update'] = sanitize_text_field( $log['plugin_update'] );
        }

        if ( isset( $log['download_log'] ) ) {
            $this->access_log['download_log'] = sanitize_text_field( $log['download_log'] );
        }
    }

    /**
     * Set reponse logs
     * 
     * @param array $response The response data.
     */
    public function set_response_log( $response ) {
      $this->response_log['time'] = isset( $response['time'] ) ? sanitize_text_field( $response['time'] ) : current_time( 'mysql' );

      if ( isset( $response['status code'] ) ) {
        $this->response_log['status code'] = sanitize_text_field( $response['status code'] );
      }

      if ( isset( $response['client IP'] ) ) {
        $this->response_log['client IP'] = sanitize_text_field( $response['client IP'] );
      }

      if ( isset( $response['website'] ) ) {
        $this->response_log['website'] = sanitize_url( $response['website'], array( 'http', 'https' ) );
      }
    }

    /**
     * Log API route interaction.
     * 
     * @param string $route_name Name of the API route.
     * @return bool True on success, false on failure.
     */
    public function log_api_interaction( $route_name ) {
        // Increment the counter for the specified API route
        if ( isset( $this->access_log[ $route_name ] ) ) {
            $this->access_log[ $route_name ]++;
            // Update meta with the new access log
            return $this->update_access_log_meta();
        }
        return false;
    }

    /**
     * Update access log meta in the database.
     * 
     * @return bool True on success, false on failure.
     */
    private function update_access_log_meta() {
        // Update the meta with the updated access log array
        return $this->plugin()->update_meta( 'access_log', $this->access_log );
    }

    /**
     * Get API route interaction count.
     * 
     * @param string $route_name Name of the API route.
     * @return int Number of interactions for the specified route.
     */
    public function get_api_interaction_count( $route_name ) {
        return isset( $this->access_log[ $route_name ] ) ? $this->access_log[ $route_name ] : 0;
    }

    /*
    |--------------------------
    | PLUGIN STATISTIC METHODS
    |--------------------------
    */

    /**
     * Log Download.
     * 
     * @param int $plugin_id Plugin ID.
     * @return bool true on success| false otherwise.
     */
    public function log_download( $plugin_id ) {
        $this->plugin()->set_item_id( $plugin_id );
        
        $current_timestamp      = current_time( 'timestamp' );
        $download_timestamps    = $this->plugin()->get_meta( 'download_timestamps', array() );
        $download_timestamps[]  = $current_timestamp;
        $download_count         = count( $download_timestamps );
        
        // Update meta with download count and timestamps
        $updated_count      = $this->plugin()->update_meta( 'download_count', $download_count );
        $updated_timestamps = $this->plugin()->update_meta( 'download_timestamps', $download_timestamps );

        return $updated_count !== false && $updated_timestamps !== false;
    }

    /**
     * Get total downloads.
     * 
     * @param int $plugin_id The plugin ID.
     * @return int total downloads.
     */
    public function get_downloads( $plugin_id ) {
        
        $this->plugin()->set_item_id( $plugin_id );
        return $this->plugin()->get_meta( 'download_count', 0 );
    }

    /**
     * Get average daily downloads.
     * 
     * @param int $plugin_id Plugin ID.
     * @return float|int Average daily downloads.
     */
    public function get_average_daily_downloads( $plugin_id ) {
        $this->plugin()->set_item_id( $plugin_id );

        $download_count = $this->plugin()->get_meta( 'download_count', 0 );
        $download_timestamps = $this->plugin()->get_meta( 'download_timestamps', array() );

        // Calculate the number of days since the first download
        $first_timestamp = ! empty( $download_timestamps ) ? min( $download_timestamps ) : current_time( 'timestamp' );
        $days_since_first_download = max( 1, ceil( ( current_time( 'timestamp' ) - $first_timestamp ) / DAY_IN_SECONDS ) );

        // Calculate average daily downloads
        $average_daily_downloads = $download_count / $days_since_first_download;

        return $average_daily_downloads;
    }

    /**
     * Handles stats sycronization.
     * 
     * @param string $context The context which the hook is fired.
     * @param Smliser_Plugin The plugin object (optional).
     * @param Smliser_License The license object (optional).
     * @param array $additional An associative array(callable => arg)
     *                          only one argument is passed to the callback function
     */
    public static function action_handler() {

        // Retrieve the expected parameters.
        $params     = func_get_args();
        $context    = isset( $params[0] ) ? sanitize_text_field( $params[0] ) : '';
        
        if ( empty( $context ) || ! is_string( $context ) ) {
            return false;
        }

        $plugin     = isset( $params[1] ) ? $params[1] : '';
        $license    = isset( $params[2] ) ? $params[2] : '';
        $additional = isset( $params[3] ) ? $params[3] : array();
        $user_func  = isset ( $additional[0] ) ? $additional[0] : null;
        $arg        = isset( $additional[1]) ? $additional[1] : null;
        
        if ( 'plugin_download' === $context ) {
            self::instance()->log_download( $plugin->get_item_id() );

        } elseif ( 'license_deactivation' === $context ) {
            $license;

        }
    }
}