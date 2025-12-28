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
        $route_descriptions = V1::describe_routes('bulk-messages');   
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
}
