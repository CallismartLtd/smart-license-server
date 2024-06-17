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
     * License Activation API route.
     * 
     * @var string $license_activation License Activation route.
     */
    private static $license_activation = 'license-validator';
    
    /**
     * License deactivation route
     * 
     * @var string $license_deactivation License deactivation route
     */
    private static $license_deactivation = 'license-deactivator';

    /**
     * Plugin update route
     * 
     * @var string $plugin_update   Plugin update route.
     */
    private static $plugin_update  = 'software-update';

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

        $download_count         = $this->plugin()->get_meta( 'download_count', 0 );
        $download_timestamps    = $this->plugin()->get_meta( 'download_timestamps', array() );

        // Calculate the number of days since the first download
        $first_timestamp            = ! empty( $download_timestamps ) ? min( $download_timestamps ) : current_time( 'timestamp' );
        $days_since_first_download  = max( 1, ceil( ( current_time( 'timestamp' ) - $first_timestamp ) / DAY_IN_SECONDS ) );

        // Calculate average daily downloads
        $average_daily_downloads = $download_count / $days_since_first_download;

        return $average_daily_downloads;
    }

    /*
    |-------------------------------
    | API ROUTE ACCESS LOG METHODS
    |-------------------------------
    */

    /**
     * Log API access.
     * 
     * @param string $route The API route.
     * @param array $args   Associative array of data to log.
     */
    public static function log_access( $route, $args ) {
        if ( empty( $route ) ) {
            return false;
        }
        $our_registered_routes = array(
            self::$license_activation,
            self::$license_deactivation,
            self::$plugin_update,
        );

        if ( ! in_array( $route, $our_registered_routes, true ) ) {
            return false;
        }

        global $wpdb;
        $table_name = SMLISER_API_ACCESS_LOG_TABLE;
        $data = array(
            'api_route'     => sanitize_text_field( $route ),
            'client_ip'     => smliser_get_client_ip(),
            'access_time'   => current_time( 'mysql' ),
            'status_code'   => isset( $args['status_code'] ) ? absint( $args['status_code'] ) : 200,
            'website'       => isset( $args['website'] ) ? sanitize_url( $args['website'] ) : '',
            'request_data'  => isset( $args['request_data'] ) && is_array( $args['request_data'] ) ? maybe_serialize( $args['request_data'] ) : '',
            'response_data' => isset( $args['response_data'] ) && is_array( $args['response_data'] ) ? maybe_serialize( $args['response_data'] ) : '',
        );

        $data_formats = array(
            '%s', // API route.
            '%s', // Client IP.
            '%s', // Access time.
            '%d', // Status code.
            '%s', // Website.
            '%s', // Request data.
            '%s', // Response data.
        );
        
        $inserted = $wpdb->insert( $table_name, $data, $data_formats );

        if ( $inserted ) {
            return true;
        }

        return false;
    }

    /**
     * Get total hits for an API route.
     * 
     * @param string $route The API route.
     * @return int Total number of hits.
     */
    public function get_total_hits( $route ) {
        global $wpdb;
        $table_name = SMLISER_API_ACCESS_LOG_TABLE;
        $route = sanitize_text_field( $route );

        $query = $wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE api_route = %s",
            $route
        );

        return (int) $wpdb->get_var( $query );
    }

    /**
     * Get unique IP addresses for an API route.
     * 
     * @param string $route The API route.
     * @return int Total number of unique IP addresses.
     */
    public function get_unique_ips( $route ) {
        global $wpdb;
        $table_name = SMLISER_API_ACCESS_LOG_TABLE;
        $route = sanitize_text_field( $route );

        $query = $wpdb->prepare(
            "SELECT COUNT(DISTINCT client_ip) FROM {$table_name} WHERE api_route = %s",
            $route
        );

        return (int) $wpdb->get_var( $query );
    }

    /**
     * Get daily access frequency for an API route.
     * 
     * @param string $route The API route.
     * @return array Associative array with dates as keys and access counts as values.
     */
    public function get_daily_access_frequency( $route ) {
        global $wpdb;
        $table_name = SMLISER_API_ACCESS_LOG_TABLE;
        $route = sanitize_text_field( $route );

        $query = $wpdb->prepare(
            "SELECT DATE(access_time) as date, COUNT(*) as count
            FROM $table_name
            WHERE api_route = %s
            GROUP BY DATE(access_time)",
            $route
        );

        $results = $wpdb->get_results( $query, ARRAY_A );

        $frequency = array();
        foreach ( $results as $result ) {
            $frequency[ $result['date'] ] = (int) $result['count'];
        }

        return $frequency;
    }

    /**
     * Get all access logs for a given API route.
     * 
     * @param string $route The API route.
     * @return array|false An array of logs with unserialized request and response data, or false on failure.
     */
    public function get_route_log( $route ) {
        if ( empty( $route ) ) {
            return false;
        }

        global $wpdb;
        $table_name = SMLISER_API_ACCESS_LOG_TABLE;

        $query = $wpdb->prepare(
            "SELECT * FROM $table_name WHERE api_route = %s",
            sanitize_text_field( $route )
        );

        $results = $wpdb->get_results( $query, ARRAY_A );

        if ( ! $results ) {
            return false;
        }

        // Unserialize request_data and response_data
        foreach ( $results as &$log ) {
            $log['request_data'] = maybe_unserialize( $log['request_data'] );
            $log['response_data'] = maybe_unserialize( $log['response_data'] );
        }

        return $results;
    }


    /**
     * Estimate active installations for a plugin.
     * 
     * @param int $plugin_id The plugin ID.
     * @return int Estimated active installations.
     */
    public function estimate_active_installations( $plugin_id ) {
        $logs = $this->get_route_log( 'plugin_update' );

        if ( ! $logs ) {
            return 0;
        }

        // Initialize an array to keep track of unique IPs for the given plugin_id
        $unique_ips = array();

        // Process each log entry
        foreach ( $logs as $log ) {
            if ( isset( $log['request_data']['plugin_id'] ) && $log['request_data']['plugin_id'] == $plugin_id ) {
                $unique_ips[ $log['client_ip'] ] = true;
            }
        }

        // Count unique IPs
        return count( $unique_ips );
    }


    /*
    |-------------------------
    | ACTION HANDLER METHODS
    |-------------------------
    */

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
        $args       = isset( $additional[1]) ? $additional[1] : null;
        
        if ( 'plugin_download' === $context ) {
            self::instance()->log_download( $plugin->get_item_id() );

        } elseif ( 'license_deactivation' === $context ) {
            $website = $license->get_action();
            $license->remove_activated_website( $website );
        } elseif( 'license_activation' === $context ) {
            self::log_access( self::$license_activation, array( 
                    'request_data' => array( 
                        'license_id' => $license->get_id(), 
                        'item_id' => $license->get_item_id(), 
                    ),
                    'response_data' => array(
                        $args
                    ),
                    'website' => $args['callback_url'],
                     
                ) );
        } elseif( 'plugin_update' === $context ) {
            self::log_access( self::$plugin_update, array( 'request_data' => $plugin->get_item_id() ) );
        }
    }
}