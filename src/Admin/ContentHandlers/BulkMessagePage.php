<?php
/**
 * Admin bulk message page router class file
 * 
 * @author Callistus Nwachukwu
 * @package Smliser\class
 */

namespace SmartLicenseServer\Admin\ContentHandlers;

use SmartLicenseServer\Admin\Contracts\AdminPageInterface;
use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\URLManager;
use SmartLicenseServer\Messaging\BulkMessageService;
use SmartLicenseServer\Templates\TemplateLocator;

use function compact;

/**
 * The admin bulk message page class
 */
class BulkMessagePage implements AdminPageInterface{
    
    public function __construct(
        protected TemplateLocator $locator,
        protected URLManager $urlmanager
    ) {}
    /**
     * Page router
     * 
     * @param Request $request
     */
    public function router( Request $request ) : void {
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
    public function dashboard( Request $request ) : void {
        $msg_data       = BulkMessageService::raw()->get_all();
        $messages       = $msg_data['items'] ?? [];
        $pagination     = $msg_data['pagination'] ?? [];
        $current_url    = smliser_get_current_url();
        $menu_args      = static::get_menu_args( $request );
        
        $vars           = compact( 'messages', 'current_url', 'menu_args', 'pagination', 'request' );
        $this->locator->render( 'admin.contents.broadcasts.index', $vars );
    }

    /**
     * Compose message page.
     * 
     * @param Request $request
     */
    public function message_editor( Request $request ) : void {
        $message_id = $request->get( 'msg_id' );
        $menu_args  = static::get_menu_args( $request );
        $message    = BulkMessageService::raw()->get_message( $message_id );
        $vars       = compact( 'menu_args', 'request', 'message' );
        
        $this->locator->render( 'admin.contents.broadcasts.compose', $vars );
    }

    /**
     * Search messages page.
     * 
     * @param Request $request
     */
    public function search_page( Request $request ) : void {
        $current_url    = smliser_get_current_url();
        $menu_args      = static::get_menu_args( $request );
        $search         = $request->get( 'msg_search' );
        $page           = $request->get( 'paged', 1 );
        $limit          = $request->get( 'limit', 10 );
        $msg_data       = BulkMessageService::raw()->search( \compact( 'page', 'search', 'limit' ) );
        $messages       = $msg_data['items'] ?? [];
        $pagination     = $msg_data['pagination'] ?? [];

        $vars           = compact( 'current_url', 'menu_args', 'search', 'messages', 'pagination', 'request' );

        $this->locator->render( 'admin.contents.broadcasts.search', $vars );
       
    }

    /**
     * Get page menu args
     * 
     * @param Request $request
     * @return array
     */
    protected function get_menu_args( Request $request ) : array {
        $tab    = $request->get( 'tab' ) ?? $request->route_param( 'tab' );
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
                    'url'   => $this->urlmanager->admin_broadcats_page_url(),
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
                    'url'   => $this->urlmanager->admin_broadcats_page_url( 'compose-new' ),
                    'icon'  => 'ti ti-plus',
                    'active'    => 'compose-new' === $tab
                ),

                array(
                    'title' => 'Settings',
                    'label' => 'Settings',
                    'url'   => $this->urlmanager->admin_options_url(),
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

    public function index_page_handler() : callable {
        return [$this, 'dashboard'];
    }

    /**
     * @inheritdoc
     */
    public function get_submenu() : array {
        return [
            [
                'title'         => 'All messages',
                'slug'          => 'index',
                'callback'      => [ $this, 'dashboard'],
                'visibility'    => true,
            ],
            [
                'title'         => 'Compose New',
                'slug'          => 'compose-new',
                'callback'      => [ $this, 'message_editor'],
                'visibility'    => true,
            ],
            [
                'title'         => 'Search Messages',
                'slug'          => 'search',
                'callback'      => [$this, 'search_page'],
                'visibility'    => true,
            ],
            [
                'title'         => 'Edit Message',
                'slug'          => 'edit',
                'callback'      => [$this, 'message_editor'],
                'visibility'    => false,
            ],
        ];
    }
    
    public function get_menu_key() : string {
        return 'broadcasts';
    }

    public function get_menu_data() : array {
        return [
            'title'         => 'Broadcasts',
            'slug'          => 'broadcasts',
            'handler'       => $this,
            'icon'          => 'ti ti-message-code',
            'visibility'    => true
        ];
    }
}
