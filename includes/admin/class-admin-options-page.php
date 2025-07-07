<?php
/**
 * The admin options page handler class
 * 
 * @author Callistus Nwachukwu
 * @package Smliser\classes
 */
namespace Callismart\Smliser\admin;

defined( 'ABSPATH' ) || exit;

class Admin_Options_Page {
    /**
     * Page router
     */
    public static function router() {
        $tab = smliser_get_query_param( 'tab' );

        switch( $tab ) {
            

            default:
            self::general_settings();
        }
    }

    /**
     * The general settings page
     */
    private static function general_settings() {
        include_once SMLISER_PATH . 'templates/admin/options/options.php';
    }
}