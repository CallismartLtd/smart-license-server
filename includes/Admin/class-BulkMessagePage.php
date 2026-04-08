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

use function compact, smliser_render_template;

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
            case 'edit':
            case 'compose-new':
                self::message_editor( $request );
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
        
        $vars           = compact( 'messages', 'current_url', 'menu_args', 'pagination' );
        smliser_render_template( 'admin.bulk-messages.index', $vars );
    }

    /**
     * Compose message page.
     * 
     * @param Request $request
     */
    private static function message_editor( Request $request ) : void {
        $message_id = $request->get( 'msg_id' );
        $menu_args  = static::get_menu_args( $request );
        $message    = BulkMessages::get_message( $message_id );
        $vars       = compact( 'menu_args', 'request', 'message' );
        
        smliser_render_template( 'admin.bulk-messages.compose', $vars );
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
        $vars   = compact( 'menu_args', 'message', 'request',  );
        smliser_render_template( 'admin.bulk-messages.edit', $vars );
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

        $vars           = compact( 'current_url', 'menu_args', 'search', 'messages', 'pagination' );

        smliser_render_template( 'admin.bulk-messages.search', $vars );
       
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
                    'url'   => \smliser_bulk_messages_url()->add_query_param( 'tab', 'compose-new' ),
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
