<?php
/**
 * The admin menu class file.
 * 
 * @author Callistus Nwachukwu
 * @package Smliser\classes
 */

namespace Callismart\Smliser\admin;

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
            'Dashboard',
            'manage_options',
            'smliser-admin',
            array( self::$instance, 'dashboard_page_controller' ),
            'dashicons-database-view',
            3.1
        );
    }

    /**
     * Dashboard page controller
     */
    public function dashboard_page_controller() {
        
    }



}

Menu::instance();