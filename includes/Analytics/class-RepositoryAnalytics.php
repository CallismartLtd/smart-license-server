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
        'software' => SMLISER_APPS_META_TABLE,
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
                [ \SmartLicenseServer\Analytics\AppsAnalytics::DOWNLOAD_COUNT_META_KEY ]
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
                $daily_counts = (array) json_decode( $value, true ); // assuming JSON storage

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
                $daily_counts = (array) json_decode( $value, true );

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
                $daily_counts = (array) json_decode( $value, true );

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
                $daily_clients = (array) json_decode( $value, true );

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
    | LICENSE STATS METHODS
    |------------------------------------------
    */


    /**
     * Record a license activity.
     * 
     * @param array $data Associative contanining the executed task.
     * @return void
     */
    public static function log_license_activity( $data ) {
        $logs = self::get_license_activity_logs();
        
        $logs[ current_time( 'mysql' ) ] = array(
            'license_id'    => $data['license_id'] ?? 'N/A',
            'ip_address'    => $data['ip_address'] ?? smliser_get_client_ip(),
            'user_agent'    => $data['user_agent'] ?? smliser_get_user_agent(),
            'website'       => $data['website'] ?? 'N/A',
            'comment'       => $data['comment'] ?? 'N/A',
            'duration'      => $data['duration'] ?? 'N/A',
        );
        
        \smliser_settings_adapter()->set( self::LICENSE_ACTIVITY_KEY, $logs );
    }

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

}
