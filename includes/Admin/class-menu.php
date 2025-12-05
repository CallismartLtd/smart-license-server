<?php
/**
 * The admin menu class file.
 * 
 * @author Callistus Nwachukwu
 * @package Smliser\classes
 */

namespace SmartLicenseServer\admin;

defined( 'ABSPATH' ) || exit;

/**
 * The admin menu class handles all admin menu registry and routing.
 */
class Menu {
    /**
     * The instance of the class.
     *
     * @var Menu
     */
    private static $instance = null;

    /**
     * The dashboard page ID.
     * 
     * @var string
     */
    public static $dasboard_page_id;

    /**
     *  The repository page ID
     * 
     * @var string
     */
    public static $repository_page_id;

    /**
     * License page ID.
     * 
     * @var string
     */
    public static $license_page_id;

    /**
     * Bulk messaging page ID.
     * 
     * @var string
     */
    public static $bulk_message_page_id;

    /**
     * Options page ID.
     * 
     * @var string
     */
    public static $options_page_id;


    /**
     * Class constructor.
     */
    private function __construct() {

        self::$instance = $this;
        add_action( 'admin_menu', array( self::$instance, 'register_menus' ) );
        add_action( 'admin_menu', array( __CLASS__, 'modify_sw_menu' ), 999 );
    }

    /**
     * Get the instance of the class.
     *
     * @return Menu
     */
    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Register admin menus.
     */
    public static function register_menus() {
        self::$dasboard_page_id = add_menu_page(
            'Smart License Server',
            'Smart License Server',
            'manage_options',
            'smliser-admin',
            array( Dashboard_Page::class, 'router' ),
            'dashicons-database-view',
            3.1
        );

        self::$repository_page_id = add_submenu_page(
            'smliser-admin',
            'Repository',
            'Repository',
            'manage_options',
            'repository',
            array( Repository_Page::class, 'router' )
        );

        self::$license_page_id = add_submenu_page(
            'smliser-admin',
            'Licenses',
            'Licenses',
            'manage_options',
            'licenses',
            array( License_Page::class, 'router' )
        );

        self::$bulk_message_page_id = add_submenu_page(
            'smliser-admin',
            'Bulk Message',
            'Bulk Message',
            'manage_options',
            'smliser-bulk-message',
            array( Bulk_Message_Page::class, 'router' )
        );

        self::$options_page_id = add_submenu_page(
            'smliser-admin',
            'Settings',
            'Settings',
            'manage_options',
            'smliser-options',
            array( Options_Page::class, 'router' )
        );
    }

    /**
     * Rename First menu item to Dashboard.
     */
    public static function modify_sw_menu() {
        global $submenu;

        if ( isset( $submenu['smliser-admin'] ) ) {
            $submenu['smliser-admin'][0][0] = 'Dashboard';
        }
    }
}

Menu::instance();