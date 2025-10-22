<?php
/**
 * Admin license page router class file
 * 
 * @author Callistus Nwachukwu
 * @package Smliser\class
 */

namespace SmartLicenseServer\admin;

defined( 'ABSPATH' ) || exit;

/**
 * The admin license page class
 */
class License_Page {
    /**
     * Page router
     */
    public static function router() {
        $tab = smliser_get_query_param( 'tab' );
        switch ( $tab ) {
            case 'add-new':
                self::add_license_page();
                break;
            case 'edit':
                self::edit_license_page();
                break;
            case 'view':
                self::license_view();
                break;
            case 'logs':
                self::logs_page();
                break;
            default:
            self::dashboard();
        }
    
    }

    /**
     * The license page dashbard
     */
    private static function dashboard() {
        $obj        = new \Smliser_license();
        $licenses   = $obj->get_licenses();

        $add_url     = smliser_license_admin_action_page( 'add-new' );
    
        include_once SMLISER_PATH . 'templates/admin/license/dashboard.php';
    
    }

    /**
     * Add license page
     */
    private static function add_license_page() {
        include_once SMLISER_PATH . 'templates/admin/license/license-add.php';
    }

    /**
     * License edit page
     */
    private static function edit_license_page() {

        $license_id = smliser_get_query_param( 'license_id' );        
        $license    = \Smliser_license::get_by_id( $license_id );
        $user_id    = ! empty( $license ) ? $license->get_user_id() : 0;
        include_once SMLISER_PATH . 'templates/admin/license/license-edit.php';
    
    }

    /**
     * License view page
     */
    private static function license_view() {
        $license_id = smliser_get_query_param( 'license_id' );        

        $license    = \Smliser_license::get_by_id( $license_id );
        if ( empty( $license ) ) {
            return wp_kses_post( smliser_not_found_container( 'Invalid or deleted license' ) );
        }

        $user               = get_userdata( $license->get_user_id() );
        $client_full_name   = $user ? $user->first_name . ' ' . $user->last_name : 'N/L';
        $plugin_obj         = new \Smliser_Plugin();
        $licensed_plugin    = $license->has_item() ? $plugin_obj->get_plugin( $license->get_item_id() ) : false;
        $delete_link        = wp_nonce_url( add_query_arg( array( 'action' => 'smliser_all_actions', 'real_action' => 'delete', 'license_id' => $license_id ), admin_url( 'admin-post.php' ) ), -1, 'smliser_nonce' );
        $plugin_name        = $licensed_plugin ? $licensed_plugin->get_name() : 'N/A';
        include_once SMLISER_PATH . 'templates/admin/license/license-admin-view.php';
    
    }

    /**
     * License activation log page.
     */
    private static function logs_page() {
        $all_tasks      = \Smliser_license::get_task_logs();
        $cron_handle    = wp_get_scheduled_event( 'smliser_validate_license' );
        $cron_timestamp = $cron_handle ? $cron_handle->timestamp : 0;
        $next_date      = smliser_tstmp_to_date( $cron_timestamp );

        include_once SMLISER_PATH . 'templates/admin/license/logs.php';
        return;
    }
}