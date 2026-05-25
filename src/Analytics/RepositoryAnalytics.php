<?php
/**
 * Collection-wide Apps/Repo Analytics class
 * 
 * using the app’s internal DB abstraction.
 * 
 * @author Callistus
 * @package SmartLicenseServer\Analytics
 * @since 0.2.0
 */

namespace SmartLicenseServer\Analytics;

use Override;
use SmartLicenseServer\Background\Jobs\Analytics\LogLicenseActivityJob;
use SmartLicenseServer\Background\Queue\JobDTO;
use SmartLicenseServer\Core\Dates\TimestampValue;
use SmartLicenseServer\Utils\CommonQueryTrait;

class RepositoryAnalytics {
    use CommonQueryTrait;

    /**
     * Mapping of app types to their meta tables
     * 
     * @var array<string,string>
     */
    private static array $meta_tables = [
        'plugin'   => SMLISER_PLUGINS_META_TABLE,
        'theme'    => SMLISER_THEMES_META_TABLE,
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
        $db = smliser_db();
        $sql    = static::query()
            ->select( 'COUNT(*)' )->from( SMLISER_ANALYTICS_LOGS_TABLE )
            ->where( 'event_type', '=', 'download' );

        if ( $type ) {
            $sql->where( 'app_type', '=', $type );
        }

        return (int) $db->get_var( $sql->build(), $sql->get_bindings() );
    }

    /**
     * Get per-day aggregated downloads for the last $days days
     * 
     * @param int $days Number of days. Default 30.
     * @param string|null $type Optional app type filter
     * @return array<string,int> Array keyed by Y-m-d
     */
    public static function get_downloads_per_day( int $days = 30, ?string $type = null ) : array {
        $db = smliser_db();

        $date = TimestampValue::now()->subtractDays( $days )->format( 'Y-m-d H:i:s' );

        // Standardized explicit 'as' aliases to ensure your custom column normalizer functions flawlessly
        $sql = static::query()->select( 'created_at as log_date', 'COUNT(*) as count' )
            ->from( \SMLISER_ANALYTICS_LOGS_TABLE )
            ->where( 'event_type', '=', 'download' )
            ->where( 'created_at', '>=', $date );
        
        if ( $type ) {
            $sql->where( 'app_type', '=', $type );
        }

        $sql->group_by( 'created_at' )->order_by( 'created_at', 'ASC' );
        
        $results = $db->get_results( $sql->build(), $sql->get_bindings() );

        if ( empty( $results ) ) {
            return [];
        }

        $aggregated = [];

        foreach ( $results as $row ) {
            // Truncate 'YYYY-MM-DD HH:MM:SS' down to just 'YYYY-MM-DD'
            $day = substr( $row['log_date'], 0, 10 ); 
            
            if ( ! isset( $aggregated[ $day ] ) ) {
                $aggregated[ $day ] = 0;
            }
            
            $aggregated[ $day ] += (int) $row['count'];
        }

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
        $db = smliser_db();

        $date   = TimestampValue::now()->subtractDays( $days )->format( 'Y-m-d H:i:s' );
        $sql    = static::query()
            ->select( 'COUNT(*)' )->from( SMLISER_ANALYTICS_LOGS_TABLE )
            ->where( 'event_type', '!=', 'download' )
            ->where( 'created_at', '>=', $date );

        if ( $type ) {
            $sql->where( 'app_type', '=', $type );
        }

        return (int) $db->get_var( $sql->build(), $sql->get_bindings() );
    }

    /**
     * Get per-day aggregated client accesses
     * 
     * @param int $days Number of days. Default 30.
     * @param string|null $type Optional app type filter
     * @return array<string,int>
     */
    public static function get_client_accesses_per_day( int $days = 30, ?string $type = null ) : array {
        $db   = smliser_db();
        $date = TimestampValue::now()->subtractDays( $days )->format( 'Y-m-d H:i:s' );

        $sql = static::query()
            ->select( 'created_at as log_date', 'COUNT(*) as count' )
            ->from( \SMLISER_ANALYTICS_LOGS_TABLE )
            ->where( 'event_type', '!=', 'download' )
            ->where( 'created_at', '>=', $date );

        if ( $type ) {
            $sql->where( 'app_type', '=', $type );
        }

        $sql->group_by( 'created_at' )->order_by( 'created_at', 'ASC' );
        
        $results = $db->get_results( $sql->build(), $sql->get_bindings() );

        if ( empty( $results ) ) {
            return [];
        }

        $aggregated = [];

        foreach ( $results as $row ) {
            $day = substr( $row['log_date'], 0, 10 ); 
            
            if ( ! isset( $aggregated[ $day ] ) ) {
                $aggregated[ $day ] = 0;
            }
            
            $aggregated[ $day ] += (int) $row['count'];
        }

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
        $db   = smliser_db();
        $date = TimestampValue::now()->subtractDays( $days )->format( 'Y-m-d H:i:s' );
        
        $sql = static::query()
            ->select( 'COUNT(DISTINCT fingerprint) as active_count' )
            ->from( \SMLISER_ANALYTICS_LOGS_TABLE )
            ->where( 'created_at', '>=', $date );

        if ( $type ) {
            $sql->where( 'app_type', '=', $type );
        }

        $result = $db->get_row( $sql->build(), $sql->get_bindings() );

        return ! empty( $result['active_count'] ) ? (int) $result['active_count'] : 0;
    }

    /*
    |------------------------------------------
    | LICENSE ACTIVITY ANALYTICS
    |------------------------------------------
    */

    /**
     * Get license verification log for the last three month.
     * 
     * @return array{int, array{
     *      license_id: int, 
     *      event_type: string,
     *      ip_address: string,
     *      user_agent: string,
     *      website: string,
     *      comment: string,
     *      duration: string,
     *      created_at: int
     *  }
     * } An array of task logs
     */
    public static function get_license_activity_logs() : array {
        $logs   = \smliser_settings()->get( self::LICENSE_ACTIVITY_KEY, [] );
        
        if ( empty( $logs ) ) {
            return [];
        }

        if ( ! is_array( $logs ) ) {
            $logs = (array) $logs;
        }

        return $logs;
    }

    /**
     * Record a license activity with structured event type.
     *
     * Dispatches the read-modify-write to the background queue so
     * license API responses are never blocked by activity logging.
     *
     * The duration and all request-context values must be computed
     * by the caller before passing them in — the worker has no access
     * to the original request by the time it executes.
     *
     * Callers (activation_response, deactivation_response, etc.)
     * are unaffected — the method signature is unchanged.
     *
     * @param array $data Associative array containing executed task info.
     * @return void
     */
    public static function log_license_activity( array $data ): void {
        smliser_job_queue()->dispatch(
            JobDTO::make(
                job_class : LogLicenseActivityJob::class,
                payload   : [
                    'license_id'    => $data['license_id'] ?? 'N/A',
                    'event_type'    => $data['event_type'] ?? 'activation',
                    'ip_address'    => $data['ip_address'] ?? smliser_get_client_ip(),
                    'user_agent'    => $data['user_agent'] ?? smliser_get_user_agent(),
                    'website'       => $data['website']    ?? 'N/A',
                    'comment'       => $data['comment']    ?? 'N/A',
                    'duration'      => $data['duration']   ?? 'N/A',
                    'created_at'    => time()
                ],
            )
        );
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

        foreach ( $logs as $log ) {
            $timestamp = $log['created_at'];
            if ( $timestamp < $cutoff ) continue;

            $day = TimestampValue::fromTimestamp( $timestamp )->format( 'Y-m-d' );
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
        $db = smliser_db();
        $total = 0;

        if ( $type ) {
            $table = match( $type ) {
                'plugin'   => SMLISER_PLUGINS_TABLE,
                'theme'    => SMLISER_THEMES_TABLE,
                'software' => SMLISER_SOFTWARE_TABLE,
            };

            $sql    = static::query()->select( 'COUNT(*)' )->from( $table );

            return (int) $db->get_var( $sql->build() );
        }

        $plugins_sql    = static::query()->select( 'COUNT(*) as total' )->from( SMLISER_PLUGINS_TABLE );
        $themes_sql     = static::query()->select( 'COUNT(*) as total' )->from( SMLISER_THEMES_TABLE );
        $software_sql   = static::query()->select( 'COUNT(*) as total' )->from( SMLISER_SOFTWARE_TABLE );

        $compound_sql = $plugins_sql->union( $themes_sql )->union( $software_sql );
        $results = $db->get_results( $compound_sql->build() );

        foreach ( $results as $row ) {
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
        $db             = smliser_db();
        $status_counts  = [];

        if ( $type ) {
            $table = match( $type ) {
                'plugin'   => SMLISER_PLUGINS_TABLE,
                'theme'    => SMLISER_THEMES_TABLE,
                'software' => SMLISER_SOFTWARE_TABLE,
                default    => null,
            };

            if ( ! $table ) {
                return [];
            }

            $sql = static::query()
                ->select( 'status', 'COUNT(*) as total' )
                ->from( $table )
                ->group_by( 'status' );

            $results = $db->get_results( $sql->build(), $sql->get_bindings() );
            
            foreach ( $results as $row ) {
                $status_counts[ $type ][ $row['status'] ] = (int) $row['total'];
            }

            return $status_counts;
        }

        $base_sql   = static::query()
            ->select( 'status', 'COUNT(*) as total', "'plugin' as app_type" )
            ->from( SMLISER_PLUGINS_TABLE )->group_by( 'status' );

        $themes_sql = static::query()
            ->select( 'status', 'COUNT(*) as total', "'theme' as app_type" )
            ->from( SMLISER_THEMES_TABLE )->group_by( 'status' );

        $software_sql = static::query()
            ->select( 'status', 'COUNT(*) as total', "'software' as app_type" )
            ->from( SMLISER_SOFTWARE_TABLE )->group_by( 'status' );

        $compound_sql = $base_sql->union_all( $themes_sql )->union_all( $software_sql );
        
        $results = $db->get_results( $compound_sql->build(), $compound_sql->get_bindings() );

        foreach ( $results as $row ) {
            $status_counts[ $row['app_type'] ][ $row['status'] ] = (int) $row['total'];
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
        $db = smliser_db();

        $sql    = static::query()
            ->select( 'app_slug', 'app_type', 'COUNT(*) as metric_total' )
            ->from( \SMLISER_ANALYTICS_LOGS_TABLE );

        if ( 'downloads' === $metric ) {
            $sql->where( 'event_type', '=', 'download' );
        } else {
            $sql->where( 'event_type', '!=', 'download' );
        }


        if ( $type ) {
            $sql->where( 'app_type', '=', $type );
        }

        $sql->group_by( 'app_slug', 'app_type' )
            ->order_by( 'metric_total', 'DESC' )->limit( $limit );

        $results = $db->get_results( $sql->build(), $sql->get_bindings() );

        $top_apps = [];
        foreach ( $results as $row ) {
            $top_apps[ $row['app_type'] ][] = $row;
        }

        return $top_apps;
    }

    /**
     * Get apps maintained per month with app info and status breakdown.
     * * @param int $months Number of months to look back
     * @param string|null $type Optional app type filter
     * @return array<string,array<string,array<string,mixed>>> [type][YYYY-MM] => ['count'=>int,'apps'=>array]
     */
    public static function get_apps_maintained_by_month( int $months = 6, ?string $type = null ) : array {
        $db         = smliser_db();
        $maintained = [];

        // Corrected time calculation mapping to your TimestampValue value object framework
        $date = TimestampValue::now()->subtractMonths( $months )->format( 'Y-m-d H:i:s' );

        if ( $type ) {
            $table = match( $type ) {
                'plugin'   => SMLISER_PLUGINS_TABLE,
                'theme'    => SMLISER_THEMES_TABLE,
                'software' => SMLISER_SOFTWARE_TABLE,
            };

            $sql = static::query()
                ->select( 'name', 'slug', 'status', 'updated_at', "'{$type}' as app_type" )
                ->from( $table )
                ->where( 'updated_at', '>=', $date )
                ->order_by( 'updated_at', 'ASC' );

            $results = $db->get_results( $sql->build(), $sql->get_bindings() );
            
            // Critical Fix: Explicitly initialize the requested type index to guarantee structural consistency
            $maintained[ $type ] = [];
        } else {
            // No type filter? Compile ALL tables using our brand new CompoundQueryIntent engine!
            $plugins_sql = static::query()
                ->select( 'name', 'slug', 'status', 'updated_at', "'plugin' as app_type" )
                ->from( SMLISER_PLUGINS_TABLE )
                ->where( 'updated_at', '>=', $date );

            $themes_sql = static::query()
                ->select( 'name', 'slug', 'status', 'updated_at', "'theme' as app_type" )
                ->from( SMLISER_THEMES_TABLE )
                ->where( 'updated_at', '>=', $date );

            $software_sql = static::query()
                ->select( 'name', 'slug', 'status', 'updated_at', "'software' as app_type" )
                ->from( SMLISER_SOFTWARE_TABLE )
                ->where( 'updated_at', '>=', $date );

            // Captured using $compound_sql to extract unified bindings and strings safely
            $compound_sql = $plugins_sql->union_all( $themes_sql )->union_all( $software_sql )
                ->order_by( 'updated_at', 'ASC' );

            $results = $db->get_results( $compound_sql->build(), $compound_sql->get_bindings() );
        }

        // Structural Safety Check: If the DB returns nothing, safely exit with our predictable layout
        if ( empty( $results ) ) {
            return $maintained;
        }

        foreach ( $results as $row ) {
            $t = $row['app_type'];
            
            // Truncate 'YYYY-MM-DD HH:MM:SS' down to just 'YYYY-MM' via substring extraction matching your style
            $month = substr( $row['updated_at'], 0, 7 ); 
            
            $maintained[ $t ][ $month ]['count'] = ( $maintained[ $t ][ $month ]['count'] ?? 0 ) + 1;
            $maintained[ $t ][ $month ]['apps'][] = [
                'name'   => $row['name'],
                'slug'   => $row['slug'],
                'status' => $row['status'],
            ];
        }

        return $maintained;
    }

    #[Override]
    public static function from_array( array $data ): static {
        throw new \Exception('Not implemented');
    }
}
