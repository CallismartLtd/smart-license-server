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
     * Accounts page ID.
     * 
     * @var string
     */
    public static $accounts_page_id;

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

        self::$accounts_page_id = add_submenu_page(
            'smliser-admin',
            'Accounts',
            'Accounts',
            'manage_options',
            'smliser-access-control',
            array( AccessControlPage::class, 'router' )
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
            $submenu['smliser-admin'][0][0] = 'Overview';
        }
    }

    /**
     * Render the Smart License Server admin top navigation header.
     *
     * @param array $args {
     *     Optional. Arguments to customize the header output.
     *
     *     @type array $breadcrumbs Array of breadcrumb items.
     *     @type array $actions     Array of action button definitions.
     * }
     * @param bool  $echo Whether to echo the output or return it.
     *
     * @return string|null Rendered HTML markup or null when echoed.
     */
    public static function print_admin_top_menu( array $args = array(), bool $echo = true ) {

        $defaults = array(
            'breadcrumbs' => array(),
            'actions'     => array(),
        );

        $args = wp_parse_args( $args, $defaults );
        $print_active   = fn ( bool $cond ) => $cond ? ' active' : '';

        ob_start(); ?>
        
        <nav class="smliser-top-nav">
            <div class="smliser-top-nav-content">
                <?php if ( ! empty( $args['breadcrumbs'] ) ) : ?>
                    <div class="smliser-breadcrumb">
                        <?php
                        $breadcrumb_count = count( $args['breadcrumbs'] );
                        $current_index    = 0;

                        foreach ( $args['breadcrumbs'] as $breadcrumb ) :
                            $current_index++;

                            if ( ! empty( $breadcrumb['url'] ) ) :
                                ?>
                                <a href="<?php echo esc_url( $breadcrumb['url'] ); ?>">
                                    <?php if ( ! empty( $breadcrumb['icon'] ) ) : ?>
                                        <i class="<?php echo esc_attr( $breadcrumb['icon'] ); ?>"></i>
                                    <?php endif; ?>
                                    <?php echo esc_html( $breadcrumb['label'] ); ?>
                                </a>
                            <?php else : ?>
                                <span><?php echo esc_html( $breadcrumb['label'] ); ?></span>
                            <?php endif; ?>

                            <?php if ( $current_index < $breadcrumb_count ) : ?>
                                <span>/</span>
                            <?php endif; ?>

                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ( ! empty( $args['actions'] ) ) : ?>
                    <div class="smliser-quick-actions">
                        <?php foreach ( $args['actions'] as $action ) : ?>
                            <a
                                class="smliser-menu-link<?php echo esc_attr( $print_active( $action['active'] ?? '' ) ); ?>"
                                href="<?php echo esc_url( $action['url'] ); ?>"
                                title="<?php echo esc_attr( $action['title'] ); ?>"
                            >
                                <?php if ( ! empty( $action['icon'] ) ) : ?>
                                    <i class="<?php echo esc_attr( $action['icon'] ); ?>"></i>
                                <?php endif; ?>
                                <?php echo esc_html( $action['label'] ?? '' ); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </nav>
        

        <?php $output = ob_get_clean();

        if ( true === $echo ) {
            echo $output; // phpcs:ignore
            return null;
        }

        return $output;
    }

}