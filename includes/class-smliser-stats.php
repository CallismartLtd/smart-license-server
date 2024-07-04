<?php
/**
 * filename class-smliser-plugin-stats.php
 * 
 * @author Callistus
 * @package Smliser\classes
 * @since 1.0.0
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
    public static $license_activation = 'license-validator';
    
    /**
     * License deactivation route
     * 
     * @var string $license_deactivation License deactivation route
     */
    public static $license_deactivation = 'license-deactivator';

    /**
     * Plugin update route
     * 
     * @var string $plugin_update   Plugin update route.
     */
    public static $plugin_update  = 'software-update';

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
     * Get the total number of plugins in our repository
     * 
     * @return int $total_plugins The total uploaded plugins
     */
    public function total_plugins() {
        $total_plugins = get_transient( 'smliser_total_plugins' );

        if ( false === $total_plugins ) {
            $total_plugins = count( $this->plugin()->get_plugins() );
            set_transient( 'smliser_total_plugins', absint( $total_plugins ), 7 * DAY_IN_SECONDS );
        }

        return absint( $total_plugins );
    }

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
     * Get total downloads for a plugin.
     * 
     * @param int $plugin_id The plugin ID.
     * @return int total downloads.
     */
    public function get_downloads( $plugin_id ) {
        
        $this->plugin()->set_item_id( $plugin_id );
        return $this->plugin()->get_meta( 'download_count', 0 );
    }

    /**
     * Get total downloads served.
     * 
     * @return int $count The total plugin downloads served
     */
    public function get_total_downloads_served() {
        // delete_transient( 'smliser_total_served_downloads' );
        $count = get_transient( 'smliser_total_served_downloads' );

        if ( false === $count ) {
            global $wpdb;
            $query = $wpdb->prepare(
                "SELECT `meta_value` FROM " . SMLISER_PLUGIN_META_TABLE . " WHERE `meta_key` = %s AND `meta_value` > %d", 
                "download_count", 0
            );

            $results    = $wpdb->get_col( $query ); // phpcs:disable
            $count      = array_sum( $results );
            set_transient( 'smliser_total_served_downloads', $count, HOUR_IN_SECONDS );
        }

        return $count;
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

    /**
     * Estimate active installations for a plugin.
     * 
     * @param int $plugin_id The plugin ID.
     * @return int Estimated active installations.
     */
    public function estimate_active_installations( $plugin_id ) {
        
        $logs = $this->get_route_log( self::$plugin_update );

        if ( ! $logs ) {
            return 0;
        }

        $unique_ips = array();

        foreach ( $logs as $log ) {
            $plugin_id_in_log = isset( $log['request_data']['plugin_id'] ) ? absint( $log['request_data']['plugin_id'] ) : 0;

            if ( $plugin_id_in_log && $plugin_id_in_log === absint( $plugin_id ) ) {
                $unique_ips[ $log['client_ip'] ] = true;
            }
        }

        // Count unique IPs
        return count( $unique_ips );
    }

    /*
    |----------------------------
    | LICENSE STATISTIC METHODS
    |----------------------------
    */

    /**
     * Total Licenses
     */
    public function total_licenses() {
        $total_licenses = get_transient( 'smliser_total_licenses' );
        
        if ( false === $total_licenses ) {
            $total_licenses = count( $this->license()->get_licenses() );
        }

        return $total_licenses;
    }

    /*
    |-------------------------------
    | API ROUTE STATISTICS METHODS
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
        
        if ( defined( 'SMLISER_LOGGING' ) ) {
            return;
        }

        define( 'SMLISER_LOGGING', 'yes' );

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
            'request_data'  => isset( $args['request_data'] ) && is_array( $args['request_data'] ) ? maybe_serialize( $args['request_data'] ) : sanitize_text_field( ! empty( $args['request_data'] ) ? $args['request_data'] : '' ),
            'response_data' => isset( $args['response_data'] ) && is_array( $args['response_data'] ) ? maybe_serialize( $args['response_data'] ) : sanitize_text_field( ! empty( $args['response_data'] ) ? $args['response_data'] : '' ),
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
        
        $inserted = $wpdb->insert( $table_name, $data, $data_formats ); // phpcs:disable

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
        // delete_transient( 'smliser_'. $route . '_hits' );

        $total_hits = get_transient( 'smliser_' . $route . '_hits' );
        
        if ( false === $total_hits ) {
            global $wpdb;
            $table_name = SMLISER_API_ACCESS_LOG_TABLE;
            $route = sanitize_text_field( $route );

            $query = $wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name WHERE api_route = %s",
                $route
            );
            $total_hits = (int) $wpdb->get_var( $query ); // phpcs:disable
            set_transient( 'smliser_' . $route . '_hits', $total_hits, 24 * HOUR_IN_SECONDS );            
        }
        
        return $total_hits;
    }

    /**
     * Total visits to an API route today
     * 
     * @param string $route The Api route name.
     * @return int $total_visits
     */
    public function total_visits_today( $route ) {
        $logs           = $this->get_route_log( $route );

        if ( ! $logs ) {
            return 0;
        }

        $start_of_day   = strtotime( current_time( 'Y-m-d' ) . ' 00:00:00' );
        $end_of_day     = strtotime( current_time( 'Y-m-d' ) . ' 23:59:59' );

        $filtered_logs = array_filter( $logs, function( $log ) use ( $start_of_day, $end_of_day ) {
            $log_timestamp = strtotime( $log['access_time'] );
            return $log_timestamp >= $start_of_day && $log_timestamp <= $end_of_day;
        });
        $total_visits = count( $filtered_logs );

        return $total_visits;
    }

    /**
     * Get unique IP addresses for an API route.
     * 
     * @param string $route The API route.
     * @return int Total number of unique IP addresses.
     */
    public function get_unique_ips( $route ) {
        // delete_transient( 'smliser_'. $route . '_ips' );
        $ips = get_transient( 'smliser_' . $route . '_ips' );

        if ( false === $ips ) {
            global $wpdb;
            $table_name = SMLISER_API_ACCESS_LOG_TABLE;
            $route = sanitize_text_field( $route );

            $query = $wpdb->prepare(
                "SELECT COUNT(DISTINCT client_ip) FROM {$table_name} WHERE api_route = %s",
                $route
            );
            $ips = (int) $wpdb->get_var( $query ); // phpcs:disable
            set_transient( 'smliser_' . $route . '_ips', $ips, HOUR_IN_SECONDS );
            
        }

        return $ips;
    }

    /**
     * Get daily access frequency for an API route.
     * 
     * @param string $route The API route.
     * @return array Associative array with dates as keys and access counts as values.
     */
    public function get_daily_access_frequency( $route ) {
        
        $frequency = get_transient( 'smliser_' . $route .'_access_frequency' );
        
        if ( false === $frequency ) {
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

            $results = $wpdb->get_results( $query, ARRAY_A ); // phpcs:disable

            $frequency = array();
            foreach ( $results as $result ) {
                $frequency[ $result['date'] ] = (int) $result['count'];
            }
            set_transient( 'smliser_' . $route .'_access_frequency', $frequency, 3 * HOUR_IN_SECONDS );
        }


        return $frequency;
    }

    /**
     * Get the average time between visits for a given route.
     * 
     * @param string $route The API route.
     * @return string The average time in hours, minutes, and seconds between visits in a human-readable format (e.g., 1hr 15mins 10secs).
     */
    public function get_average_time_between_visits( $route ) {
        // Get the access logs for the route
        $logs = $this->get_route_log( $route );

        if ( ! $logs ) {
            return 'No visits'; // Clearer message for no visits
        }

        // Extract timestamps
        $timestamps = array();
        foreach ( $logs as $log ) {
            $timestamps[] = strtotime( $log['access_time'] );
        }

        // Sort timestamps in ascending order
        sort( $timestamps );

        // Calculate intervals between consecutive timestamps
        $intervals = array();
        for ( $i = 1; $i < count( $timestamps ); $i++ ) {
            $intervals[] = $timestamps[$i] - $timestamps[$i - 1];
        }

        // Calculate the average interval in seconds
        $average_interval = ! empty( $intervals ) ? array_sum( $intervals ) / count( $intervals ) : 0;

        return smliser_readable_duration( $average_interval );
    }

  

    /**
     * Get the average time between visits for a given route on a specific date.
     * 
     * @param string $route The API route.
     * @param string $date The date string (Y-m-d format) for which to calculate the average time between visits.
     * @return float|int The average time in seconds between visits for the specified date.
     */
    public function get_average_time_between_visits_for_date( $route, $date ) {
        if ( empty( $route || empty( $date ) ) ) {
            return '';
        }
        
        // Get the access logs for the route
        $logs = $this->get_route_log( $route );

        // If there are no logs, return 0
        if ( ! $logs ) {
            return 'No visits yet';
        }

        // Convert the date string to a timestamp range for the entire day
        $start_of_day   = strtotime( $date . ' 00:00:00' );
        $end_of_day     = strtotime( $date . ' 23:59:59' );

        // Filter the logs to include only those within the specified date range
        $filtered_logs = array_filter( $logs, function( $log ) use ( $start_of_day, $end_of_day ) {
            $log_timestamp = strtotime( $log['access_time'] );
            return $log_timestamp >= $start_of_day && $log_timestamp <= $end_of_day;
        });

        // If there are no logs for the specified date, return 0
        if ( empty( $filtered_logs ) ) {
            return 0;
        }

        // Extract timestamps from the filtered logs
        $timestamps = array();
        foreach ( $filtered_logs as $log ) {
            $timestamps[] = strtotime( $log['access_time'] );
        }

        // Sort timestamps in ascending order
        sort( $timestamps );

        // Calculate intervals between consecutive timestamps
        $intervals = array();
        for ( $i = 1; $i < count( $timestamps ); $i++ ) {
            $intervals[] = $timestamps[$i] - $timestamps[$i - 1];
        }

        // Calculate the average interval
        $average_interval = ! empty( $intervals ) ? array_sum( $intervals ) / count( $intervals ) : 0;
        return smliser_readable_duration( $average_interval );
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

        // delete_transient( 'smliser_'. $route . '_log' );
        $route_log = get_transient( 'smliser_'. $route . '_log' );

        if ( false === $route_log ) {
            global $wpdb;
            $table_name = SMLISER_API_ACCESS_LOG_TABLE;

            $query = $wpdb->prepare(
                "SELECT * FROM $table_name WHERE api_route = %s",
                sanitize_text_field( $route )
            );

            $results = $wpdb->get_results( $query, ARRAY_A ); // phpcs:disable

            if ( ! $results ) {
                return false;
            }

            // Unserialize request_data and response_data
            foreach ( $results as &$log ) {
                $log['request_data']    = maybe_unserialize( $log['request_data'] );
                $log['response_data']   = maybe_unserialize( $log['response_data'] );
            }
            set_transient( 'smliser_'. $route . '_log', $results, DAY_IN_SECONDS );
            $route_log = $results;            
        }
        return $route_log;
    }

    /**
     * Get total API requests count.
     * 
     * @return int Total number of API requests.
     */
    public function get_total_requests() {
        // delete_transient( 'smliser_total_requests' );
        global $wpdb;
        $total_requests = get_transient( 'smliser_total_requests' );

        if ( false === $total_requests ) {
            $total_requests = (int) $wpdb->get_var("SELECT COUNT(*) FROM " . SMLISER_API_ACCESS_LOG_TABLE );
            set_transient( 'smliser_total_requests', $total_requests, HOUR_IN_SECONDS );
        }

        return $total_requests;
    }

    /**
     * Get unique IP addresses count.
     * 
     * @return int Total number of unique IP addresses.
     */
    public function get_unique_ips_count() {
        // delete_transient( 'smliser_unique_ips' );
        global $wpdb;
        $unique_ips = get_transient( 'smliser_unique_ips' );

        if ( false === $unique_ips ) {
            $unique_ips = (int) $wpdb->get_var("SELECT COUNT(DISTINCT client_ip) FROM " . SMLISER_API_ACCESS_LOG_TABLE);
            set_transient( 'smliser_unique_ips', $unique_ips, HOUR_IN_SECONDS );
        }

        return $unique_ips;
    }

    /**
     * Get requests count per API route.
     * 
     * @return array Associative array with API routes as keys and request counts as values.
     */
    public static function get_requests_per_route() {
        // delete_transient( 'smliser_requests_per_route' );
        global $wpdb;
        $requests_per_route = get_transient( 'smliser_requests_per_route' );

        if ( false === $requests_per_route ) {
            $results = $wpdb->get_results("SELECT api_route, COUNT(*) as count FROM " . SMLISER_API_ACCESS_LOG_TABLE . " GROUP BY api_route", OBJECT_K);
            $requests_per_route = array_map( function( $result ) {
                return (int) $result->count;
            }, $results );
            set_transient( 'smliser_requests_per_route', $requests_per_route, HOUR_IN_SECONDS );
        }

        return $requests_per_route;
    }

    /**
     * Get HTTP status codes distribution.
     * 
     * @return array Associative array with status codes as keys and their counts as values.
     */
    public function get_status_codes_distribution() {
        // delete_transient( 'smliser_status_codes' );
        global $wpdb;
        $status_codes = get_transient( 'smliser_status_codes' );

        if ( false === $status_codes ) {
            $results = $wpdb->get_results("SELECT status_code, COUNT(*) as count FROM " . SMLISER_API_ACCESS_LOG_TABLE . " GROUP BY status_code", OBJECT_K);
            $status_codes = array_map( function( $result ) {
                return (int) $result->count;
            }, $results );
            set_transient( 'smliser_status_codes', $status_codes, HOUR_IN_SECONDS );
        }

        return $status_codes;
    }

    /**
     * Get top errors by status code.
     * 
     * @param int $limit Number of top errors to retrieve.
     * @return array Associative array with status codes as keys and their counts as values.
     */
    public function get_top_errors( $limit = 5 ) {
        // delete_transient( 'smliser_top_errors' );
        global $wpdb;
        $top_errors = get_transient( 'smliser_top_errors' );

        if ( false === $top_errors ) {
            $results = $wpdb->get_results( $wpdb->prepare(
                "SELECT status_code, COUNT(*) as count FROM " . SMLISER_API_ACCESS_LOG_TABLE . " WHERE status_code >= 400 GROUP BY status_code ORDER BY count DESC LIMIT %d", 
                $limit 
            ), OBJECT_K);
            $top_errors = array_map( function( $result ) {
                return (int) $result->count;
            }, $results );
            set_transient( 'smliser_top_errors', $top_errors, HOUR_IN_SECONDS );
        }

        return $top_errors;
    }

    /**
     * Get requests per user.
     * 
     * @return array Associative array with client IPs as keys and request counts as values.
     */
    public function get_requests_per_user() {
        // delete_transient( 'smliser_requests_per_user' );
        global $wpdb;
        $requests_per_user = get_transient( 'smliser_requests_per_user' );

        if ( false === $requests_per_user ) {
            $results = $wpdb->get_results( "SELECT client_ip, COUNT(*) as count FROM " . SMLISER_API_ACCESS_LOG_TABLE . " GROUP BY client_ip", OBJECT_K );
            $requests_per_user = array_map( function( $result ) {
                return (int) $result->count;
            }, $results );
            set_transient( 'smliser_requests_per_user', $requests_per_user, HOUR_IN_SECONDS );
        }

        return $requests_per_user;
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
            self::log_access( self::$license_deactivation, array( 
                'request_data' => array( 
                    'license_id' => $license->get_id(), 
                    'item_id' => $license->get_item_id(), 
                ),
                'response_data' => array(
                    $args
                ),
                'website' => isset( $args['callback_url'] ) ? isset( $args['callback_url'] ) : '',
                 
            ) );
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
            self::log_access( self::$plugin_update, array( 'request_data' => array( 'plugin_id' => $plugin->get_item_id() ) ) );
        } elseif( 'denied_access' === $context ) {
            $reasons = $args;
            self::log_access( $reasons['route'], array( 
                    'status_code'   => $reasons['status_code'],
                    'request_data'  => $reasons['request_data'],
                    'response_data' => $reasons['response_data'],
                )
            );

        }
    }
}