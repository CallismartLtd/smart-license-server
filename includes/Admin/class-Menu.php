<?php
/**
 * The admin menu class file.
 * 
 * @author Callistus Nwachukwu
 * @package Smliser\classes
 */

namespace SmartLicenseServer\Admin;

use SmartLicenseServer\RESTAPI\Versions\V1;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * The admin menu class handles all admin menu registry and routing.
 */
class Menu {

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
     * REST API page ID.
     * 
     * @var string
     */
    public static $rest_api_page_id;

    /**
     * Options page ID.
     * 
     * @var string
     */
    public static $options_page_id;

    /**
     * Register admin menus.
     */
    public static function register_menus() {
        self::$dasboard_page_id = add_menu_page(
            SMLISER_APP_NAME,
            SMLISER_APP_NAME,
            'manage_options',
            'smliser-admin',
            array( DashboardPage::class, 'router' ),
            'dashicons-database-view',
            3.1
        );

        self::$repository_page_id = add_submenu_page(
            'smliser-admin',
            'Repository',
            'Repository',
            'manage_options',
            'repository',
            array( RepositoryPage::class, 'router' )
        );

        self::$license_page_id = add_submenu_page(
            'smliser-admin',
            'Licenses',
            'Licenses',
            'manage_options',
            'licenses',
            array( LicensePage::class, 'router' )
        );

        self::$bulk_message_page_id = add_submenu_page(
            'smliser-admin',
            'Bulk Message',
            'Bulk Message',
            'manage_options',
            'smliser-bulk-message',
            array( BulkMessagePage::class, 'router' )
        );

        self::$rest_api_page_id = add_submenu_page(
            'smliser-admin',
            'API DOC',
            'API DOC',
            'manage_options',
            'smliser-doc',
            array( V1::class, 'html_index' )
        );

        self::$options_page_id = add_submenu_page(
            'smliser-admin',
            'Settings',
            'Settings',
            'manage_options',
            'smliser-options',
            array( OptionsPage::class, 'router' )
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