<?php
/**
 * The admin dashboard page handler file
 * 
 * @author Callistus Nwachukwu
 * @package Smliser\classes
 */

namespace SmartLicenseServer\Admin;

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
        


        include_once SMLISER_PATH . 'templates/admin-dashboard.php';
    }
}