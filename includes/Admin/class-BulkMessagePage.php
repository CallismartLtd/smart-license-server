<?php
/**
 * Admin bulk message page router class file
 * 
 * @author Callistus Nwachukwu
 * @package Smliser\class
 */

namespace SmartLicenseServer\Admin;

use SmartLicenseServer\Messaging\BulkMessages;
use SmartLicenseServer\RESTAPI\Versions\V1;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * The admin bulk message page class
 */
class BulkMessagePage {
    /**
     * Page router
     */
    public static function router() {
        $tab = smliser_get_query_param( 'tab' );
        switch ( $tab ) {
            case 'compose-new':
                self::compose_message_page();
                break;
            case 'edit':
                self::edit_message_page();
                break;
            case 'delete':
                self::delete_page();
                break;
            default:
            self::dashboard();
        }
    
    }

    /**
     * Bulk messages page dashbard.
     */
    private static function dashboard() {
        $messages   = BulkMessages::get_all();
        $menu_args  = static::get_menu_args();
        include_once SMLISER_PATH . 'templates/admin/bulk-messages/dashboard.php';
    
    }

    /**
     * Compose message page.
     */
    private static function compose_message_page() {
        include_once SMLISER_PATH . 'templates/admin/bulk-messages/compose.php';
    }

    /**
     * Edit message page.
     */
    private static function edit_message_page() {
        $message_id = smliser_get_query_param( 'msg_id' );
        $menu_args  = static::get_menu_args();
        $message    = BulkMessages::get_message( $message_id );
   
        include_once SMLISER_PATH . 'templates/admin/bulk-messages/edit.php';
    }

    /**
     * Delete a given message.
     */
    private static function delete_page() {
        $message_id = smliser_get_query_param( 'msg_id' );
        
        $message    = BulkMessages::get_message( $message_id );

        include_once SMLISER_PATH . 'templates/admin/bulk-messages/delete.php';
       
    }

    /**
     * Get page menu args
     * 
     * @return array
     */
    protected static function get_menu_args() : array {
        $tab    = smliser_get_query_param( 'tab', '' );
        $title  = match( $tab ) {
            'edit'          => 'Edit Bulk Message',
            'compose-new'   => 'Compose Bulk Message',
            default         => 'Bulk Messages'
        };
        
        return [
            'breadcrumbs'   => array(
                array(
                    'label' => 'Repository',
                    'url'   => admin_url( 'admin.php?page=smliser-bulk-message' ),
                    'icon'  => 'ti ti-home-filled'
                ),

                array(
                    'label' => $title
                )
            ),
            'actions'   => array(
                array(
                    'title' => 'Compose new message',
                    'label' => 'Compose New',
                    'url'   => smliser_get_current_url()->add_query_param( 'tab', 'compose-new' ),
                    'icon'  => 'ti ti-plus',
                    'active'    => 'compose-new' === $tab
                ),

                array(
                    'title' => 'Settings',
                    'label' => 'Settings',
                    'url'   => admin_url( 'admin.php?page=smliser-options'),
                    'icon'  => 'dashicons dashicons-admin-generic'
                )
            )
        ];
    }
}
