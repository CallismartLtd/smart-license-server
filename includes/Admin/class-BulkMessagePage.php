<?php
/**
 * Admin bulk message page router class file
 * 
 * @author Callistus Nwachukwu
 * @package Smliser\class
 */

namespace SmartLicenseServer\Admin;

use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Messaging\BulkMessages;
use SmartLicenseServer\RESTAPI\Versions\V1;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * The admin bulk message page class
 */
class BulkMessagePage {
    /**
     * Page router
     * 
     * @param Request $request
     */
    public static function router( Request $request ) : void {
        $tab = $request->get( 'tab' );
        switch ( $tab ) {
            case 'compose-new':
                self::compose_message_page( $request );
                break;
            case 'edit':
                self::edit_message_page( $request );
                break;
            case 'search':
                self::search_page( $request );
                break;
            default:
            self::dashboard( $request );
        }
    
    }

    /**
     * Bulk messages page dashbard.
     * 
     * @param Request $request
     */
    private static function dashboard( Request $request ) : void {
        $msg_data       = BulkMessages::get_all();
        $messages       = $msg_data['items'] ?? [];
        $pagination     = $msg_data['pagination'] ?? [];
        $current_url    = smliser_get_current_url();
        $menu_args      = static::get_menu_args( $request );
        include_once SMLISER_PATH . 'templates/admin/bulk-messages/dashboard.php';
    
    }

    /**
     * Compose message page.
     * 
     * @param Request $request
     */
    private static function compose_message_page( Request $request ) : void {
        $menu_args      = static::get_menu_args( $request );
        include_once SMLISER_PATH . 'templates/admin/bulk-messages/compose.php';
    }

    /**
     * Edit message page.
     * 
     * @param Request $request
     */
    private static function edit_message_page( Request $request ) : void {
        $message_id = $request->get( 'msg_id' );
        $menu_args  = static::get_menu_args( $request );
        $message    = BulkMessages::get_message( $message_id );
   
        include_once SMLISER_PATH . 'templates/admin/bulk-messages/edit.php';
    }

    /**
     * Search messages page.
     * 
     * @param Request $request
     */
    private static function search_page( Request $request ) : void {
        $current_url    = smliser_get_current_url();
        $menu_args      = static::get_menu_args( $request );
        $search         = $request->get( 'msg_search' );
        $page           = $request->get( 'paged', 1 );
        $limit          = $request->get( 'limit', 10 );
        $msg_data       = BulkMessages::search( \compact( 'page', 'search', 'limit' ) );
        $messages       = $msg_data['items'] ?? [];
        $pagination     = $msg_data['pagination'] ?? [];

        include_once SMLISER_PATH . 'templates/admin/bulk-messages/search.php';
       
    }

    /**
     * Get page menu args
     * 
     * @param Request $request
     * @return array
     */
    protected static function get_menu_args( Request $request ) : array {
        $tab    = $request->get( 'tab', '' );
        $title  = match( $tab ) {
            'edit'          => 'Edit Bulk Message',
            'compose-new'   => 'Compose Bulk Message',
            'search'        => 'Search Bulk Messages',
            default         => 'Bulk Messages'
        };
        
        return [
            'breadcrumbs'   => array(
                array(
                    'label' => 'Bulk Messages',
                    'url'   => smliser_bulk_messages_url(),
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
                    'url'   => smliser_options_url(),
                    'icon'  => 'dashicons dashicons-admin-generic'
                )
            )
        ];
    }
}
