<?php
/**
 * The admin repository page handler class.
 * 
 * @author Callistus Nwachukwu
 * @package Smliser\class
 */

namespace SmartLicenseServer\admin;

/**
 * The Admin repository page handler
 */
class Repository_Page {
    /**
     * Page router
     */
    public static function router() {
        $tab    = smliser_get_query_param( 'tab' );
        switch( $tab ) {
            case 'add-new':
                self::upload_page();
                break;
            case 'edit':
                self::edit_page();
                break;
            case 'view':
                self::view_page();
                break;
            default:
            self::dashboard();
        }
    }

    /**
     * The repository dashboard page
     */
    private static function dashboard() {
        $plugins     = \Smliser_Plugin::get_plugins();
        $add_url     = smliser_admin_repo_tab( 'add-new' );
        include SMLISER_PATH . 'templates/admin/repository/dashboard.php';

    }

    /**
     * The upload page
     */
    private static function upload_page() {
        include_once SMLISER_PATH . 'templates/admin/repository/repo-add.php';
    }

    /**
     * The edit page
     */
    private static function edit_page() {
        $id     = smliser_get_query_param( 'item_id' );
        $obj    = new \Smliser_Plugin();
        $plugin = $obj->get_plugin( $id );

        include_once SMLISER_PATH . 'templates/admin/repository/plugin-edit.php';
    }

    /**
     * View items page
     */
    private static function view_page() {
        $id     = smliser_get_query_param( 'item_id' );

        $obj    = new \Smliser_Plugin();
        $plugin = $obj->get_plugin( $id );

        if ( ! empty( $plugin ) ) {
            $delete_link    = wp_nonce_url( add_query_arg( array( 'action' => 'smliser_plugin_action', 'real_action' => 'delete', 'item_id' => $id ), admin_url( 'admin-post.php' ) ), -1, 'smliser_nonce' );
        }

        $stats = new \Smliser_Stats();
        include_once SMLISER_PATH . 'templates/admin/repository/plugin-view.php';
    }
}