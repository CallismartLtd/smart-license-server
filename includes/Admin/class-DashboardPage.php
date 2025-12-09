<?php
/**
 * The admin dashboard page handler file
 * 
 * @author Callistus Nwachukwu
 * @package Smliser\classes
 */

namespace SmartLicenseServer\Admin;

use SmartLicenseServer\SmliserStats;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * The admin dashboard page handler.
 */
class DashboardPage {

    /**
     * Page router
     */
    public static function router() {
        $tab = smliser_get_query_param( 'tab' );

        switch( $tab ) {
            default :
            self::dashboard();
        }
    }

    /**
     * Dashboard Callback method
     */
    private static function dashboard() {
        
        $stats          = new SmliserStats();
        $status_codes   = $stats->get_status_codes_distribution();
        $error_codes    = $stats->get_top_errors( wp_rand( 50, 100 ) );
        // Prepare data for Chart.js
        $plugin_update_hits         = $stats->get_total_hits( $stats::$plugin_update );
        $license_activation_hits    = $stats->get_total_hits( $stats::$license_activation );
        $license_deactivation_hits  = $stats->get_total_hits( $stats::$license_deactivation );

        $plugin_update_visits       = $stats->total_visits_today( $stats::$plugin_update );
        $license_activation_visits  = $stats->total_visits_today( $stats::$license_activation );
        $license_deactivation_visits = $stats->total_visits_today( $stats::$license_deactivation );

        $plugin_unique_visitors                 = $stats->get_unique_ips( $stats::$plugin_update );
        $license_activation_unique_visitors     = $stats->get_unique_ips( $stats::$license_activation );
        $license_deactivation_unique_visitors   = $stats->get_unique_ips( $stats::$license_deactivation );

        include_once SMLISER_PATH . 'templates/admin-dashboard.php';
    }
}