<?php
/**
 * Apps Analytics class file
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Analytics
 * @since 0.2.0
 */

namespace SmartLicenseServer\Analytics;

use SmartLicenseServer\Background\Jobs\Analytics\LogClientAccessJob;
use SmartLicenseServer\Background\Jobs\Analytics\LogDownloadJob;
use SmartLicenseServer\Background\Queue\JobDTO;
use SmartLicenseServer\HostedApps\AbstractHostedApp;

\defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Apps analytics class is used to track analytical data for the given app instance.
 * 
 * Supports multiple hosted application types.
 */
class AppsAnalytics {

    const DOWNLOAD_COUNT_META_KEY               = 'download_count';
    const DOWNLOAD_TIMESTAMP_META_KEY           = 'download_timestamps';
    const CLIENT_ACCESS_META_KEY                = 'client_access_count';
    const CLIENT_ACCESS_DAILY_META_KEY          = 'client_access_daily';
    const CLIENT_ACCESS_EVENTS_DAILY_META_KEY   = 'client_access_events_daily';
    const CLIENT_ACCESS_UNIQUE_DAILY_META_KEY   = 'client_access_unique_daily';

    /**
     * Log a download event asynchronously.
     *
     * Dispatches LogDownloadJob to handle the DB insert and download
     * counter increment in the background. Intentionally does NOT
     * delegate to log_client_access() — download count and client
     * access count are distinct metrics tracked independently.
     *
     * The fingerprint is computed here during the request because
     * the worker has no access to the original request context.
     *
     * @param AbstractHostedApp $app The app being downloaded.
     * @return bool Always true — dispatch is fire-and-forget.
     */
    public static function log_download( AbstractHostedApp $app ) : bool {
        smliser_job_queue()->dispatch(
            JobDTO::make(
                job_class : LogDownloadJob::class,
                payload   : [
                    'app_type'    => $app->get_type(),
                    'app_slug'    => $app->get_slug(),
                    'fingerprint' => hash( 'sha256', \smliser_get_client_ip() . '|' . \smliser_get_user_agent( true ) ),
                    'created_at'  => gmdate( 'Y-m-d H:i:s' ),
                ],
            )
        );
 
        return true;
    }

    /**
     * Get total lifetime downloads for the app
     * 
     * @param AbstractHostedApp $app
     * @return int Total download count
     */
    public static function get_total_downloads( AbstractHostedApp $app ) : int {
        return (int) $app->get_meta( self::DOWNLOAD_COUNT_META_KEY, 0 );
    }

    /**
     * Get per-day download counts (Optimized SQL)
     */
    public static function get_downloads_per_day( AbstractHostedApp $app, int $days = 30 ) : array {
        $db = smliser_dbclass();
        $query = "SELECT DATE(created_at) as log_date, COUNT(*) as count 
                  FROM " . \SMLISER_ANALYTICS_LOGS_TABLE . " 
                  WHERE app_slug = ? AND event_type = 'download' 
                  AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                  GROUP BY log_date ORDER BY log_date ASC";
        
        $results = $db->get_results( $query, [ $app->get_slug(), $days ] );
        return array_column( $results, 'count', 'log_date' );
    }

    /**
     * Get today's downloads
     * 
     * @param AbstractHostedApp $app
     * @return int
     */
    public static function get_todays_downloads( AbstractHostedApp $app ) : int {
        $today  = gmdate( 'Y-m-d' );
        return self::get_downloads_on( $app, $today );
    }

    /**
     * Get yesterday's downloads
     * 
     * @param AbstractHostedApp $app
     * @return int
     */
    public static function get_yesterdays_downloads( AbstractHostedApp $app ) : int {
        $yesterday  = gmdate( 'Y-m-d', strtotime( 'yesterday' ) );
        return self::get_downloads_on( $app, $yesterday );
    }

    /**
     * Get the number of downloads for a given day.
     * * @param AbstractHostedApp $app
     * @param string $date The date(Y-m-d) to search.
     * @return int
     */
    public static function get_downloads_on( AbstractHostedApp $app, string $date ) : int {
        $db = smliser_dbclass();
        $query = "SELECT COUNT(*) FROM " . \SMLISER_ANALYTICS_LOGS_TABLE . " 
                  WHERE app_slug = ? AND event_type = 'download' 
                  AND DATE(created_at) = ?";
        
        return (int) $db->get_var( $query, [ $app->get_slug(), $date ] );
    }

    /**
     * Get average daily downloads over the last $days days
     * 
     * @param AbstractHostedApp $app
     * @param int $days Number of days to consider. Default 30.
     * @return float Average downloads per day
     */
    public static function get_average_daily_downloads( AbstractHostedApp $app, int $days = 30 ) : float {
        $daily_counts = self::get_downloads_per_day( $app, $days );

        if ( empty( $daily_counts ) ) {
            return 0.0;
        }

        return array_sum( $daily_counts ) / count( $daily_counts );
    }

    /**
     * Get the date with the highest downloads in the last $days days
     * 
     * @param AbstractHostedApp $app
     * @param int $days Number of days to consider. Default 365.
     * @return string|null Date in 'Y-m-d' format or null if no downloads
     */
    public static function get_peak_download_day( AbstractHostedApp $app, int $days = 365 ) : ?string {
        $daily_counts = self::get_downloads_per_day( $app, $days );

        if ( empty( $daily_counts ) ) {
            return null;
        }

        return array_search( max( $daily_counts ), $daily_counts, true );
    }

    /**
     * Get download growth percentage comparing last $days days to previous $days days
     * * @param AbstractHostedApp $app
     * @param int $days Number of days for comparison.
     * @return float
     */
    public static function get_download_growth_percentage( AbstractHostedApp $app, int $days = 30 ) : float {
        $db = smliser_dbclass();
        
        $current_total = (int) $db->get_var(
            "SELECT COUNT(*) FROM " . \SMLISER_ANALYTICS_LOGS_TABLE . " 
             WHERE app_slug = ? AND event_type = 'download' 
             AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)",
            [ $app->get_slug(), $days ]
        );

        $previous_total = (int) $db->get_var(
            "SELECT COUNT(*) FROM " . \SMLISER_ANALYTICS_LOGS_TABLE . " 
             WHERE app_slug = ? AND event_type = 'download' 
             AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY) 
             AND created_at < DATE_SUB(NOW(), INTERVAL ? DAY)",
            [ $app->get_slug(), $days * 2, $days ]
        );

        if ( $previous_total === 0 ) {
            return $current_total === 0 ? 0.0 : 100.0;
        }

        return ( ( $current_total - $previous_total ) / $previous_total ) * 100;
    }

    /**
     * Unified entry point for all analytics writes.
     *
     * Dispatches the DB insert and meta counter update to the background
     * queue so API requests are never blocked by analytics writes.
     *
     * The fingerprint is computed here — during the request — because
     * the worker has no access to the original IP and user agent context
     * by the time it executes.
     *
     * Callers (including log_download()) are unaffected — the method
     * signature and return type are unchanged.
     *
     * @param AbstractHostedApp $app        The hosted app being accessed.
     * @param string            $event_type The event type e.g. 'download', 'plugin_info'.
     * @return bool Always true — dispatch is fire-and-forget.
     */
    public static function log_client_access( AbstractHostedApp $app, string $event_type ) : bool {
        smliser_job_queue()->dispatch(
            JobDTO::make(
                job_class : LogClientAccessJob::class,
                payload   : [
                    'app_type'    => $app->get_type(),
                    'app_slug'    => $app->get_slug(),
                    'event_type'  => $event_type,
                    'fingerprint' => hash( 'sha256', \smliser_get_client_ip() . '|' . \smliser_get_user_agent( true ) ),
                    'created_at'  => gmdate( 'Y-m-d H:i:s' ),
                ],
            )
        );
 
        return true;
    }

    /**
     * Get total lifetime client accesses.
     *
     * @param AbstractHostedApp $app Hosted app instance.
     * @return int Total number of client access events.
     */
    public static function get_total_client_accesses( AbstractHostedApp $app ) : int {
        return (int) $app->get_meta( self::CLIENT_ACCESS_META_KEY, 0 );
    }

    /**
     * Get daily client access counts for the last $days days.
     *
     * @param AbstractHostedApp $app Hosted app instance.
     * @param int               $days Number of days to retrieve. Default 30.
     * @return array<string,int> Array keyed by 'Y-m-d'.
     */
    public static function get_client_access_per_day( AbstractHostedApp $app, int $days = 30 ) : array {
        $db = smliser_dbclass();
        $query = "SELECT DATE(created_at) as log_date, COUNT(*) as count 
                  FROM " . \SMLISER_ANALYTICS_LOGS_TABLE . " 
                  WHERE app_slug = ? AND event_type != 'download' 
                  AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                  GROUP BY log_date ORDER BY log_date ASC";
        
        $results = $db->get_results( $query, [ $app->get_slug(), $days ] );
        return array_column( $results, 'count', 'log_date' );
    }

    /**
     * Get average daily client accesses for the last $days days.
     *
     * @param AbstractHostedApp $app Hosted app instance.
     * @param int               $days Number of days to consider. Default 30.
     * @return float Average client access count per day.
     */
    public static function get_average_daily_client_accesses( AbstractHostedApp $app, int $days = 30 ) : float {
        $daily = self::get_client_access_per_day( $app, $days );

        if ( empty( $daily ) ) {
            return 0.0;
        }

        return array_sum( $daily ) / count( $daily );
    }

    /**
     * Get event-type breakdown for the last $days days.
     *
     * @param AbstractHostedApp $app Hosted app instance.
     * @param int               $days Number of days to include. Default 30.
     * @return array<string,array<string,int>> Format: [ 'Y-m-d' => [ 'event_type' => count ] ]
     */
    public static function get_client_access_event_breakdown( AbstractHostedApp $app, int $days = 30 ) : array {
        $db = smliser_dbclass();
        $query = "SELECT DATE(created_at) as log_date, event_type, COUNT(*) as count 
                  FROM " . \SMLISER_ANALYTICS_LOGS_TABLE . " 
                  WHERE app_slug = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                  GROUP BY log_date, event_type ORDER BY log_date ASC";
        
        $results = $db->get_results( $query, [ $app->get_slug(), $days ] );
        
        $formatted = [];
        foreach ( $results as $row ) {
            $formatted[ $row['log_date'] ][ $row['event_type'] ] = (int) $row['count'];
        }
        return $formatted;
    }

    /**
     * Get estimated active installations (Optimized SQL)
     */
    public static function get_estimated_active_installations( AbstractHostedApp $app, int $days = 30 ) : int {
        $db = smliser_dbclass();
        $query = "SELECT COUNT(DISTINCT fingerprint) FROM " . \SMLISER_ANALYTICS_LOGS_TABLE . " 
                  WHERE app_slug = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)";
        
        return (int) $db->get_var( $query, [ $app->get_slug(), $days ] );
    }

    /**
     * Get the peak client access day for the last $days days.
     *
     * @param AbstractHostedApp $app Hosted app instance.
     * @param int               $days Number of days to consider. Default 365.
     * @return string|null Date string 'Y-m-d' or null if no access data.
     */
    public static function get_peak_client_access_day( AbstractHostedApp $app, int $days = 365 ) : ?string {
        $daily = self::get_client_access_per_day( $app, $days );

        if ( empty( $daily ) ) {
            return null;
        }

        return array_search( max( $daily ), $daily, true );
    }

    /**
     * Get client access growth percentage comparing the last $days days 
     * to the previous $days days.
     *
     * @param AbstractHostedApp $app Hosted app instance.
     * @param int               $days Number of days to compare. Default 30.
     * @return float Growth percentage (positive = growth, negative = decline).
     */
    public static function get_client_access_growth_percentage( AbstractHostedApp $app, int $days = 30 ) : float {
        $db = smliser_dbclass();
        
        $current_total = (int) $db->get_var(
            "SELECT COUNT(*) FROM " . \SMLISER_ANALYTICS_LOGS_TABLE . " 
             WHERE app_slug = ? AND event_type != 'download' 
             AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)",
            [ $app->get_slug(), $days ]
        );

        $previous_total = (int) $db->get_var(
            "SELECT COUNT(*) FROM " . \SMLISER_ANALYTICS_LOGS_TABLE . " 
             WHERE app_slug = ? AND event_type != 'download' 
             AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY) 
             AND created_at < DATE_SUB(NOW(), INTERVAL ? DAY)",
            [ $app->get_slug(), $days * 2, $days ]
        );

        if ( $previous_total === 0 ) return $current_total === 0 ? 0.0 : 100.0;
        return ( ( $current_total - $previous_total ) / $previous_total ) * 100;
    }

}
