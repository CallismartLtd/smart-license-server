<?php
/**
 * Apps Analytics class file
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Analytics
 * @since 0.2.0
 */

namespace SmartLicenseServer\Analytics;

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
     * Increment download count for the given app
     * 
     * @param AbstractHostedApp $app
     * @return bool
     */
    public static function log_download( AbstractHostedApp $app ) : bool {

        // Increment total downloads
        $total = (int) $app->get_meta( self::DOWNLOAD_COUNT_META_KEY, 0 );
        $total++;
        $success_total = $app->update_meta( self::DOWNLOAD_COUNT_META_KEY, $total );

        // Track per-day downloads
        $today        = gmdate( 'Y-m-d' );
        $daily_counts = (array) $app->get_meta( self::DOWNLOAD_TIMESTAMP_META_KEY, [] );

        if ( isset( $daily_counts[ $today ] ) ) {
            $daily_counts[ $today ]++;
        } else {
            $daily_counts[ $today ] = 1;
        }

        // Keep only the last 365 days
        $one_year_ago = gmdate( 'Y-m-d', strtotime( '-365 days' ) );
        foreach ( $daily_counts as $date => $count ) {
            if ( $date < $one_year_ago ) {
                unset( $daily_counts[ $date ] );
            }
        }

        $success_daily = $app->update_meta( self::DOWNLOAD_TIMESTAMP_META_KEY, $daily_counts );

        return $success_total && $success_daily;
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
     * Get per-day download counts for the last $days days
     * 
     * @param AbstractHostedApp $app
     * @param int $days Number of days to fetch. Default 30.
     * @return array<string,int> Array keyed by 'Y-m-d' date
     */
    public static function get_downloads_per_day( AbstractHostedApp $app, int $days = 30 ) : array {
        $daily = (array) $app->get_meta( self::DOWNLOAD_TIMESTAMP_META_KEY, [] );

        // Slice the last $days days.
        return array_slice( $daily, -$days, $days, true );
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
     * 
     * @param AbstractHostedApp $app
     * @param string $date The date(Y-m-d) to search.
     * @return int
     */
    public static function get_downloads_on( AbstractHostedApp $app, string $date ) : int {
        $daily  = $app->get_meta( self::DOWNLOAD_TIMESTAMP_META_KEY, [] );

        return \intval( $daily[$date] ?? 0 );
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
     * 
     * @param AbstractHostedApp $app
     * @param int $days Number of days for comparison. Default 30.
     * @return float Growth percentage (positive = growth, negative = decline)
     */
    public static function get_download_growth_percentage( AbstractHostedApp $app, int $days = 30 ) : float {
        $daily_counts = (array) $app->get_meta( self::DOWNLOAD_TIMESTAMP_META_KEY, [] );

        if ( empty( $daily_counts ) ) {
            return 0.0;
        }

        $all_dates = array_keys( $daily_counts );
        sort( $all_dates );

        // Split into current and previous periods
        $current_period = array_slice( $daily_counts, -$days, $days, true );
        $previous_period = array_slice( $daily_counts, -2 * $days, $days, true );

        $current_total  = array_sum( $current_period );
        $previous_total = array_sum( $previous_period );

        if ( $previous_total === 0 ) {
            return $current_total === 0 ? 0.0 : 100.0;
        }

        return ( ( $current_total - $previous_total ) / $previous_total ) * 100;
    }

    /**
     * Log a client access event for the given app.
     *
     * Tracks:
     * - total access count
     * - per-day access count
     * - per-day event type distribution
     * - per-day unique client fingerprints (used to estimate active installations)
     *
     * @param AbstractHostedApp $app        The hosted app instance.
     * @param string            $event_type The type of event (e.g. 'update_check', 'app_info', 'download').
     *
     * @return bool True if all updates succeeded, false otherwise.
     */
    public static function log_client_access( AbstractHostedApp $app, string $event_type ) : bool {

        // ---------------------------------------------------------
        // 1. Increment total client access count
        // ---------------------------------------------------------
        $total = (int) $app->get_meta( self::CLIENT_ACCESS_META_KEY, 0 );
        $total++;
        $success_total = $app->update_meta( self::CLIENT_ACCESS_META_KEY, $total );

        $today        = gmdate( 'Y-m-d' );
        $one_year_ago = gmdate( 'Y-m-d', strtotime( '-365 days' ) );

        // ---------------------------------------------------------
        // 2. Track per-day access count
        // ---------------------------------------------------------
        $daily_access = (array) $app->get_meta( self::CLIENT_ACCESS_DAILY_META_KEY, [] );

        if ( isset( $daily_access[ $today ] ) ) {
            $daily_access[ $today ]++;
        } else {
            $daily_access[ $today ] = 1;
        }

        foreach ( $daily_access as $date => $count ) {
            if ( $date < $one_year_ago ) {
                unset( $daily_access[ $date ] );
            }
        }

        $success_daily = $app->update_meta( self::CLIENT_ACCESS_DAILY_META_KEY, $daily_access );

        // ---------------------------------------------------------
        // 3. Track per-day event-type distribution
        // ---------------------------------------------------------
        $event_daily = (array) $app->get_meta( self::CLIENT_ACCESS_EVENTS_DAILY_META_KEY, [] );

        if ( ! isset( $event_daily[ $today ] ) ) {
            $event_daily[ $today ] = [];
        }

        if ( isset( $event_daily[ $today ][ $event_type ] ) ) {
            $event_daily[ $today ][ $event_type ]++;
        } else {
            $event_daily[ $today ][ $event_type ] = 1;
        }

        foreach ( $event_daily as $date => $events ) {
            if ( $date < $one_year_ago ) {
                unset( $event_daily[ $date ] );
            }
        }

        $success_event = $app->update_meta( self::CLIENT_ACCESS_EVENTS_DAILY_META_KEY, $event_daily );

        // ---------------------------------------------------------
        // 4. Track unique client fingerprints per day
        // ---------------------------------------------------------
        $ip         = \smliser_get_client_ip();
        $user_agent = \smliser_get_user_agent( true );

        $fingerprint_raw = $ip . '|' . $user_agent;
        $fingerprint     = hash( 'sha256', $fingerprint_raw );

        $unique_daily = (array) $app->get_meta( self::CLIENT_ACCESS_UNIQUE_DAILY_META_KEY, [] );

        if ( ! isset( $unique_daily[ $today ] ) ) {
            $unique_daily[ $today ] = [];
        }

        $unique_daily[ $today ][ $fingerprint ] = true;

        foreach ( $unique_daily as $date => $clients ) {
            if ( $date < $one_year_ago ) {
                unset( $unique_daily[ $date ] );
            }
        }

        $success_unique = $app->update_meta( self::CLIENT_ACCESS_UNIQUE_DAILY_META_KEY, $unique_daily );

        // ---------------------------------------------------------
        // Final Result
        // ---------------------------------------------------------
        return $success_total && $success_daily && $success_event && $success_unique;
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
        $daily = (array) $app->get_meta( self::CLIENT_ACCESS_DAILY_META_KEY, [] );

        return array_slice( $daily, -$days, $days, true );
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
        $events = (array) $app->get_meta( self::CLIENT_ACCESS_EVENTS_DAILY_META_KEY, [] );

        return array_slice( $events, -$days, $days, true );
    }

    /**
     * Get estimated active installations.
     *
     * Uses unique fingerprint counts over the last $days days.
     *
     * @param AbstractHostedApp $app Hosted app instance.
     * @param int               $days Number of days to consider. Default 30.
     * @return int Estimated number of active installations.
     */
    public static function get_estimated_active_installations( AbstractHostedApp $app, int $days = 30 ) : int {
        $unique = (array) $app->get_meta( self::CLIENT_ACCESS_UNIQUE_DAILY_META_KEY, [] );

        $period = array_slice( $unique, -$days, $days, true );

        $fingerprints = [];

        foreach ( $period as $date => $clients ) {
            foreach ( $clients as $hash => $true_val ) {
                $fingerprints[ $hash ] = true;
            }
        }

        return count( $fingerprints );
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
        $daily = (array) $app->get_meta( self::CLIENT_ACCESS_DAILY_META_KEY, [] );

        if ( empty( $daily ) ) {
            return 0.0;
        }

        // Split data
        $current_period  = array_slice( $daily, -$days, $days, true );
        $previous_period = array_slice( $daily, -2 * $days, $days, true );

        $current_total  = array_sum( $current_period );
        $previous_total = array_sum( $previous_period );

        if ( $previous_total === 0 ) {
            return $current_total === 0 ? 0.0 : 100.0;
        }

        return ( ( $current_total - $previous_total ) / $previous_total ) * 100;
    }

}
