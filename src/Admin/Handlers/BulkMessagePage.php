<?php
/**
 * Admin bulk message page router class file
 * 
 * @author Callistus Nwachukwu
 * @package Smliser\class
 */

namespace SmartLicenseServer\Admin\Handlers;

use SmartLicenseServer\Admin\Contracts\AdminPageInterface;
use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Messaging\BulkMessageService;

use function compact, smliser_render_template;

/**
 * The admin bulk message page class
 */
class BulkMessagePage implements AdminPageInterface{
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
    public static function dashboard( Request $request ) : void {
        $msg_data       = BulkMessageService::raw()->get_all();
        $messages       = $msg_data['items'] ?? [];
        $pagination     = $msg_data['pagination'] ?? [];
        $current_url    = smliser_get_current_url();
        $menu_args      = static::get_menu_args( $request );
        
        $vars           = compact( 'messages', 'current_url', 'menu_args', 'pagination', 'request' );
        smliser_render_template( 'admin.contents.broadcasts.index', $vars );
    }

    /**
     * Compose message page.
     * 
     * @param Request $request
     */
    public static function message_editor( Request $request ) : void {
        $message_id = $request->get( 'msg_id' );
        $menu_args  = static::get_menu_args( $request );
        $message    = BulkMessageService::raw()->get_message( $message_id );
        $vars       = compact( 'menu_args', 'request', 'message' );
        
        smliser_render_template( 'admin.contents.broadcasts.compose', $vars );
    }

    /**
     * Search messages page.
     * 
     * @param Request $request
     */
    public static function search_page( Request $request ) : void {
        $current_url    = smliser_get_current_url();
        $menu_args      = static::get_menu_args( $request );
        $search         = $request->get( 'msg_search' );
        $page           = $request->get( 'paged', 1 );
        $limit          = $request->get( 'limit', 10 );
        $msg_data       = BulkMessageService::raw()->search( \compact( 'page', 'search', 'limit' ) );
        $messages       = $msg_data['items'] ?? [];
        $pagination     = $msg_data['pagination'] ?? [];

        $vars           = compact( 'current_url', 'menu_args', 'search', 'messages', 'pagination', 'request' );

        smliser_render_template( 'admin.contents.broadcasts.search', $vars );
       
    }

    /**
     * Get page menu args
     * 
     * @param Request $request
     * @return array
     */
    protected static function get_menu_args( Request $request ) : array {
        $tab    = $request->get( 'tab' ) ?? $request->route_param( 'submenu' );
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
                    'url'   => \smliser_bulk_messages_url( 'compose-new' ),
                    'icon'  => 'ti ti-plus',
                    'active'    => 'compose-new' === $tab
                ),

                array(
                    'title' => 'Settings',
                    'label' => 'Settings',
                    'url'   => smliser_options_url(),
                    'icon'  => 'ti ti-settings'
                )
            )
        ];
    }

    /*
    |---------------------------
    | INTERFACE IMPLEMENTATION
    |---------------------------
    */

    public static function index_page_handler() : callable {
        return [static::class, 'dashboard'];
    }

    /**
     * @inheritdoc
     */
    public static function get_submenu() : array {
        return [
            [
                'title'         => 'All messages',
                'slug'          => 'index',
                'callback'      => [ static::class, 'dashboard'],
                'visibility'    => true,
            ],
            [
                'title'         => 'Compose New',
                'slug'          => 'compose-new',
                'callback'      => [ static::class, 'message_editor'],
                'visibility'    => true,
            ],
            [
                'title'         => 'Search Messages',
                'slug'          => 'search',
                'callback'      => [static::class, 'search_page'],
                'visibility'    => true,
            ],
            [
                'title'         => 'Edit Message',
                'slug'          => 'edit',
                'callback'      => [static::class, 'message_editor'],
                'visibility'    => false,
            ],
        ];
    }
    
    public static function get_menu_key() : string {
        return 'broadcasts';
    }

    public static function get_menu_data() : array {
        return [
            'title'         => 'Broadcasts',
            'slug'          => 'broadcasts',
            'handler'       => static::class,
            'icon'          => 'ti ti-message-code',
            'visibility'    => true
        ];
    }
}
