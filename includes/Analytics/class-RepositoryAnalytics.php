<?php
/**
 * Collection-wide Apps/Repo Analytics class
 * 
 * using the app’s internal DB abstraction.
 * 
 * @author Callistus
 * @package SmartLicenseServer\Analytics
 * @since 0.3.0
 */

namespace SmartLicenseServer\Analytics;

\defined( 'SMLISER_ABSPATH' ) || exit;

class RepositoryAnalytics {

    /**
     * Mapping of app types to their meta tables
     * 
     * @var array<string,string>
     */
    private static array $meta_tables = [
        'plugin'   => SMLISER_PLUGIN_META_TABLE,
        'theme'    => SMLISER_THEME_META_TABLE,
        'software' => SMLISER_SOFTWARE_META_TABLE,
    ];

    /**
     * The storage key for licence activity log.
     * 
     * @var string
     */
    const LICENSE_ACTIVITY_KEY = 'smliser_task_log';

    /**
     * Get total downloads across all apps (optionally filtered by type)
     * 
     * @param string|null $type 'plugin'|'theme'|'software' or null for all types
     * @return int
     */
    public static function get_total_downloads( ?string $type = null ) : int {
        $db = smliser_dbclass();
        $total = 0;

        $types = $type ? [$type] : array_keys(self::$meta_tables);

        foreach ( $types as $t ) {
            $table = self::$meta_tables[ $t ];

            $results = $db->get_col(
                "SELECT meta_value FROM {$table} WHERE meta_key = ?",
                [ AppsAnalytics::DOWNLOAD_COUNT_META_KEY ]
            );

            foreach ( $results as $value ) {
                $total += (int) $value;
            }
        }

        return $total;
    }

    /**
     * Get per-day aggregated downloads for the last $days days
     * 
     * @param int $days Number of days. Default 30.
     * @param string|null $type Optional app type filter
     * @return array<string,int> Array keyed by Y-m-d
     */
    public static function get_downloads_per_day( int $days = 30, ?string $type = null ) : array {
        $db = smliser_dbclass();
        $aggregated = [];

        $types = $type ? [$type] : array_keys(self::$meta_tables);
        $cutoff = gmdate( 'Y-m-d', strtotime( "-{$days} days" ) );

        foreach ( $types as $t ) {
            $table = self::$meta_tables[ $t ];

            $results = $db->get_col(
                "SELECT meta_value FROM {$table} WHERE meta_key = ?",
                [ \SmartLicenseServer\Analytics\AppsAnalytics::DOWNLOAD_TIMESTAMP_META_KEY ]
            );

            foreach ( $results as $value ) {
                $daily_counts = (array)\maybe_unserialize( $value ); // assuming JSON storage

                foreach ( $daily_counts as $date => $count ) {
                    if ( $date >= $cutoff ) {
                        $aggregated[ $date ] = ($aggregated[ $date ] ?? 0) + (int) $count;
                    }
                }
            }
        }

        ksort( $aggregated );
        return $aggregated;
    }

    /**
     * Get total client accesses across all apps
     * 
     * @param int $days Number of days. Default 30.
     * @param string|null $type Optional app type filter
     * @return int
     */
    public static function get_total_client_accesses( int $days = 30, ?string $type = null ) : int {
        $db = smliser_dbclass();
        $total = 0;

        $types = $type ? [$type] : array_keys(self::$meta_tables);
        $cutoff = gmdate( 'Y-m-d', strtotime( "-{$days} days" ) );

        foreach ( $types as $t ) {
            $table = self::$meta_tables[ $t ];

            $results = $db->get_col(
                "SELECT meta_value FROM {$table} WHERE meta_key = ?",
                [ \SmartLicenseServer\Analytics\AppsAnalytics::CLIENT_ACCESS_DAILY_META_KEY ]
            );

            foreach ( $results as $value ) {
                $daily_counts = (array) \maybe_unserialize( $value, true );

                foreach ( $daily_counts as $date => $count ) {
                    if ( $date >= $cutoff ) {
                        $total += (int) $count;
                    }
                }
            }
        }

        return $total;
    }

    /**
     * Get per-day aggregated client accesses
     * 
     * @param int $days Number of days. Default 30.
     * @param string|null $type Optional app type filter
     * @return array<string,int>
     */
    public static function get_client_accesses_per_day( int $days = 30, ?string $type = null ) : array {
        $db = smliser_dbclass();
        $aggregated = [];

        $types = $type ? [$type] : array_keys(self::$meta_tables);
        $cutoff = gmdate( 'Y-m-d', strtotime( "-{$days} days" ) );

        foreach ( $types as $t ) {
            $table = self::$meta_tables[ $t ];

            $results = $db->get_col(
                "SELECT meta_value FROM {$table} WHERE meta_key = ?",
                [ \SmartLicenseServer\Analytics\AppsAnalytics::CLIENT_ACCESS_DAILY_META_KEY ]
            );

            foreach ( $results as $value ) {
                $daily_counts = (array) \maybe_unserialize( $value, true );

                foreach ( $daily_counts as $date => $count ) {
                    if ( $date >= $cutoff ) {
                        $aggregated[ $date ] = ($aggregated[ $date ] ?? 0) + (int) $count;
                    }
                }
            }
        }

        ksort( $aggregated );
        return $aggregated;
    }

    /**
     * Get total active installations across all apps
     * 
     * Active installations are computed from unique daily client hashes
     * 
     * @param int $days Number of days. Default 30
     * @param string|null $type Optional app type filter
     * @return int
     */
    public static function get_active_installations( int $days = 30, ?string $type = null ) : int {
        $db = smliser_dbclass();
        $unique_clients = [];

        $types = $type ? [$type] : array_keys(self::$meta_tables);
        $cutoff = gmdate( 'Y-m-d', strtotime( "-{$days} days" ) );

        foreach ( $types as $t ) {
            $table = self::$meta_tables[ $t ];

            $results = $db->get_col(
                "SELECT meta_value FROM {$table} WHERE meta_key = ?",
                [ \SmartLicenseServer\Analytics\AppsAnalytics::CLIENT_ACCESS_UNIQUE_DAILY_META_KEY ]
            );

            foreach ( $results as $value ) {
                $daily_clients = (array) \maybe_unserialize( $value, true );

                foreach ( $daily_clients as $date => $hashes ) {
                    if ( $date >= $cutoff ) {
                        foreach ( $hashes as $hash ) {
                            $unique_clients[ $hash ] = true;
                        }
                    }
                }
            }
        }

        return count( $unique_clients );
    }

    /*
    |------------------------------------------
    | LICENSE ACTIVITY ANALYTICS
    |------------------------------------------
    */

    /**
     * Get license verification log for the last three month.
     * 
     * @return array $schedules An array of task logs
     */
    public static function get_license_activity_logs() {
        $schedules  = \smliser_settings_adapter()->get( self::LICENSE_ACTIVITY_KEY, false );
        
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
                \smliser_settings_adapter()->set( self::LICENSE_ACTIVITY_KEY, $schedules );
            }

        }

        return $schedules;
    }

    /**
     * Record a license activity with structured event type.
     * 
     * @param array $data Associative array containing executed task info
     * @return void
     */
    public static function log_license_activity( array $data ) {
        $logs = self::get_license_activity_logs();

        $logs[ current_time( 'mysql' ) ] = [
            'license_id' => $data['license_id'] ?? 'N/A',
            'event_type' => $data['event_type'] ?? 'activation', // structured event type
            'ip_address' => $data['ip_address'] ?? smliser_get_client_ip(),
            'user_agent' => $data['user_agent'] ?? smliser_get_user_agent(),
            'website'    => $data['website'] ?? 'N/A',
            'comment'    => $data['comment'] ?? 'N/A',
            'duration'   => $data['duration'] ?? 'N/A',
        ];

        \smliser_settings_adapter()->set( self::LICENSE_ACTIVITY_KEY, $logs );
    }

    /**
     * Aggregate license activity per day by event type
     * 
     * @param int $days Number of days to include. Default 30.
     * @return array<string,array<string,int>> Format: [ 'Y-m-d' => [ 'activation' => 5, 'deactivation' => 2, ... ] ]
     */
    public static function get_license_activity_per_day( int $days = 30 ) : array {
        $logs = self::get_license_activity_logs();
        $aggregated = [];
        $cutoff = strtotime( "-{$days} days" );

        foreach ( $logs as $time => $log ) {
            $timestamp = strtotime( $time );
            if ( $timestamp < $cutoff ) continue;

            $day = substr( $time, 0, 10 );
            $event_type = $log['event_type'] ?? 'unknown';

            if ( ! isset( $aggregated[ $day ] ) ) {
                $aggregated[ $day ] = [];
            }

            $aggregated[ $day ][ $event_type ] = ($aggregated[ $day ][ $event_type ] ?? 0) + 1;
        }

        ksort( $aggregated );
        return $aggregated;
    }

    /**
     * Compute growth percentage of a specific license event type
     *
     * @param string $event_type Event type: activation|deactivation|uninstallation
     * @param int $days Number of days for comparison
     * @return float Growth percentage (positive = increase, negative = decline)
     */
    public static function get_license_event_growth_percentage( string $event_type, int $days = 30 ) : float {
        $per_day = self::get_license_activity_per_day( $days * 2 ); // include previous period
        $all_days = array_keys( $per_day );

        $current_period = array_slice($all_days, -$days, $days);
        $previous_period = array_slice($all_days, -2*$days, $days);

        $current_total = 0;
        foreach ($current_period as $day) {
            $current_total += $per_day[$day][$event_type] ?? 0;
        }

        $previous_total = 0;
        foreach ($previous_period as $day) {
            $previous_total += $per_day[$day][$event_type] ?? 0;
        }

        if ($previous_total === 0) {
            return $current_total === 0 ? 0.0 : 100.0;
        }

        return (($current_total - $previous_total) / $previous_total) * 100;
    }

    /**
     * Get total counts for a specific license event type over the last $days days
     *
     * @param string $event_type Event type
     * @param int $days Number of days to include
     * @return int Total count
     */
    public static function get_license_event_total( string $event_type, int $days = 30 ) : int {
        $per_day = self::get_license_activity_per_day( $days );
        $total = 0;

        foreach ($per_day as $day => $events) {
            $total += $events[$event_type] ?? 0;
        }

        return $total;
    }

    /**
     * Get total apps hosted in the repository, optionally filtered by type.
     * 
     * @param string|null $type 'plugin'|'theme'|'software' or null for all types
     * @return int
     */
    public static function get_total_apps( ?string $type = null ) : int {
        $db = smliser_dbclass();
        $types = $type ? [$type] : array_keys(self::$meta_tables);
        $total = 0;

        foreach ( $types as $t ) {
            $table = match( $t ) {
                'plugin'   => SMLISER_PLUGIN_ITEM_TABLE,
                'theme'    => SMLISER_THEME_ITEM_TABLE,
                'software' => SMLISER_SOFTWARE_TABLE,
            };

            $row = $db->get_row( "SELECT COUNT(*) AS total FROM {$table}", [] );
            $total += (int) $row['total'];
        }

        return $total;
    }

    /**
     * Get apps count grouped by status.
     * 
     * @param string|null $type Optional filter by app type
     * @return array<string,array<string,int>> [type][status] => count
     */
    public static function get_apps_by_status( ?string $type = null ) : array {
        $db = smliser_dbclass();
        $types = $type ? [$type] : array_keys(self::$meta_tables);
        $status_counts = [];

        foreach ( $types as $t ) {
            $table = match( $t ) {
                'plugin'   => SMLISER_PLUGIN_ITEM_TABLE,
                'theme'    => SMLISER_THEME_ITEM_TABLE,
                'software' => SMLISER_SOFTWARE_TABLE,
            };

            $results = $db->get_results(
                "SELECT status, COUNT(*) AS total FROM {$table} GROUP BY status",
                []
            );

            foreach ( $results as $row ) {
                $status_counts[ $t ][ $row['status'] ] = (int) $row['total'];
            }
        }

        return $status_counts;
    }

    /**
     * Get top apps by metric (downloads or client accesses)
     * 
     * @param int $limit Number of apps to return
     * @param string $metric 'downloads'|'client_accesses'
     * @param string|null $type Optional app type filter
     * @return array<string,array<int,array<string,mixed>>> [type] => list of apps
     */
    public static function get_top_apps( int $limit = 10, string $metric = 'downloads', ?string $type = null ) : array {
        $db = smliser_dbclass();
        $types = $type ? [$type] : array_keys(self::$meta_tables);
        $top_apps = [];

        foreach ( $types as $t ) {
            $item_table = match( $t ) {
                'plugin'   => SMLISER_PLUGIN_ITEM_TABLE,
                'theme'    => SMLISER_THEME_ITEM_TABLE,
                'software' => SMLISER_SOFTWARE_TABLE,
            };
            $meta_table = self::$meta_tables[ $t ];
            $meta_key   = $metric === 'downloads'
                ? \SmartLicenseServer\Analytics\AppsAnalytics::DOWNLOAD_COUNT_META_KEY
                : \SmartLicenseServer\Analytics\AppsAnalytics::CLIENT_ACCESS_DAILY_META_KEY;

            $sql = "
                SELECT i.name, i.slug, SUM(CAST(m.meta_value AS UNSIGNED)) AS metric_total
                FROM {$item_table} i
                INNER JOIN {$meta_table} m ON m.{$t}_id = i.id
                WHERE m.meta_key = ?
                GROUP BY i.id
                ORDER BY metric_total DESC
                LIMIT {$limit}
            ";

            $results = $db->get_results( $sql, [$meta_key] );
            $top_apps[ $t ] = $results;
        }

        return $top_apps;
    }

    /**
     * Get apps maintained per month with app info and status breakdown.
     * 
     * @param int $months Number of months to look back
     * @param string|null $type Optional app type filter
     * @return array<string,array<string,array<string,mixed>>> [type][YYYY-MM] => ['count'=>int,'apps'=>array]
     */
    public static function get_apps_maintained_by_month( int $months = 6, ?string $type = null ) : array {
        $db = smliser_dbclass();
        $types = $type ? [$type] : array_keys(self::$meta_tables);
        $maintained = [];

        foreach ( $types as $t ) {
            $table = match( $t ) {
                'plugin'   => SMLISER_PLUGIN_ITEM_TABLE,
                'theme'    => SMLISER_THEME_ITEM_TABLE,
                'software' => SMLISER_SOFTWARE_TABLE,
            };

            $sql = "
                SELECT name, slug, status, DATE_FORMAT(last_updated, '%Y-%m') AS month
                FROM {$table}
                WHERE last_updated >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
                ORDER BY last_updated ASC
            ";

            $results = $db->get_results( $sql, [$months] );

            foreach ( $results as $row ) {
                $month = $row['month'];
                $maintained[ $t ][ $month ]['count'] = ($maintained[ $t ][ $month ]['count'] ?? 0) + 1;
                $maintained[ $t ][ $month ]['apps'][] = [
                    'name'   => $row['name'],
                    'slug'   => $row['slug'],
                    'status' => $row['status'],
                ];
            }
        }

        return $maintained;
    }



}
