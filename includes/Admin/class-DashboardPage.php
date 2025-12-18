<?php
/**
 * The admin dashboard page handler file
 * 
 * @author Callistus Nwachukwu
 * @package Smliser\classes
 */

namespace SmartLicenseServer\Admin;

use SmartLicenseServer\Analytics\RepositoryAnalytics;

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
        $totals = [
            'apps'      => RepositoryAnalytics::get_total_apps(),
            'plugins'   => RepositoryAnalytics::get_total_apps( 'plugin' ),
            'themes'    => RepositoryAnalytics::get_total_apps( 'theme' ),
            'software'  => RepositoryAnalytics::get_total_apps( 'software' )
        ];
        

        $metrics    = [
            'repository'    => [
                'downloads'     => RepositoryAnalytics::get_total_downloads(),
                'access'
            ]
        ];


        include_once SMLISER_PATH . 'templates/admin-dashboard.php';
    }
}