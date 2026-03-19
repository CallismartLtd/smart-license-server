<?php
/**
 * The admin menu class file.
 * 
 * @author Callistus Nwachukwu
 * @package Smliser\classes
 */

namespace SmartLicenseServer\Environments\WordPress;

use SmartLicenseServer\Admin\AccessControlPage;
use SmartLicenseServer\Admin\BulkMessagePage;
use SmartLicenseServer\Admin\LicensePage;
use SmartLicenseServer\Admin\OptionsPage;
use SmartLicenseServer\Admin\RepositoryPage;
use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Environments\WordPress\RESTAPI;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * The admin menu class handles all admin menu registry and routing.
 */
class AdminMenu {
    const MENU_ICON = 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAyMCAyMCI+CiAgPGcgdHJhbnNmb3JtPSJ0cmFuc2xhdGUoMTAsIDEwKSBzY2FsZSgxLjQpIHRyYW5zbGF0ZSgtMTAsIC0xMCkiPgogICAgPHJlY3QgeD0iNC40IiB5PSI1LjYiIHdpZHRoPSIxMS4yIiBoZWlnaHQ9IjIiIHJ4PSIwLjYiIGZpbGw9ImN1cnJlbnRDb2xvciIvPgogICAgPHJlY3QgeD0iNC40IiB5PSI4LjQiIHdpZHRoPSIxMS4yIiBoZWlnaHQ9IjIiIHJ4PSIwLjYiIGZpbGw9ImN1cnJlbnRDb2xvciIvPgogICAgPHJlY3QgeD0iNC40IiB5PSIxMS4yIiB3aWR0aD0iMTEuMiIgaGVpZ2h0PSIyIiByeD0iMC42IiBmaWxsPSJjdXJyZW50Q29sb3IiLz4KICAgIDxjaXJjbGUgY3g9IjUuNiIgY3k9IjYuNiIgcj0iMC41IiBmaWxsPSIjMDAwIiBvcGFjaXR5PSIwLjMiLz4KICAgIDxjaXJjbGUgY3g9IjUuNiIgY3k9IjkuNCIgcj0iMC41IiBmaWxsPSIjMDAwIiBvcGFjaXR5PSIwLjMiLz4KICAgIDxjaXJjbGUgY3g9IjUuNiIgY3k9IjEyLjIiIHI9IjAuNSIgZmlsbD0iIzAwMCIgb3BhY2l0eT0iMC4zIi8+CiAgICA8cGF0aCBkPSJNMTAgNCBMMTMuNiA1LjYgVjkuMiBDMTMuNiAxMiAxMS42IDE0IDEwIDE0LjggQzguNCAxNCA2LjQgMTIgNi40IDkuMiBWNS42IFoiIGZpbGw9ImN1cnJlbnRDb2xvciIvPgogICAgPGNpcmNsZSBjeD0iMTAiIGN5PSI4LjgiIHI9IjEiIGZpbGw9IiMwMDAiIG9wYWNpdHk9IjAuNCIvPgogICAgPHJlY3QgeD0iOS40IiB5PSI4LjgiIHdpZHRoPSIxLjIiIGhlaWdodD0iMi40IiByeD0iMC4zIiBmaWxsPSIjMDAwIiBvcGFjaXR5PSIwLjQiLz4KICA8L2c+Cjwvc3ZnPgo=';
    
    /**
     * The dashboard page ID.
     * 
     * @var string
     */
    public $dasboard_page_id;

    /**
     *  The repository page ID
     * 
     * @var string
     */
    public $repository_page_id;

    /**
     * License page ID.
     * 
     * @var string
     */
    public $license_page_id;

    /**
     * Bulk messaging page ID.
     * 
     * @var string
     */
    public $bulk_message_page_id;

    /**
     * REST API page ID.
     * 
     * @var string
     */
    public $rest_api_page_id;

    /**
     * Accounts page ID.
     * 
     * @var string
     */
    public $accounts_page_id;

    /**
     * Options page ID.
     * 
     * @var string
     */
    public $options_page_id;

    /**
     * The current request object
     * 
     * @var Request $request
     */
    private Request $request;

    /**
     * Constructor
     * 
     * @param Request $request The  current request object.
     */
    public function __construct( Request $request ) {
        $this->request = $request;
    }

    /**
     * Register admin menus.
     */
    public function register_menus() {
        $this->dasboard_page_id = add_menu_page(
            SMLISER_APP_NAME,
            SMLISER_APP_NAME,
            'manage_options',
            'smliser-admin',
            // array( DashboardPage::class, 'router' ),
            array( $this, 'router' ),
            self::MENU_ICON,
            3.1
        );

        $this->repository_page_id = add_submenu_page(
            'smliser-admin',
            'Repository',
            'Repository',
            'manage_options',
            'repository',
            array( RepositoryPage::class, 'router' )
        );

        $this->license_page_id = add_submenu_page(
            'smliser-admin',
            'Licenses',
            'Licenses',
            'manage_options',
            'licenses',
            array( LicensePage::class, 'router' )
        );

        $this->bulk_message_page_id = add_submenu_page(
            'smliser-admin',
            'Bulk Message',
            'Bulk Message',
            'manage_options',
            'smliser-bulk-message',
            array( BulkMessagePage::class, 'router' )
        );

        $this->rest_api_page_id = add_submenu_page(
            'smliser-admin',
            'API DOC',
            'API DOC',
            'manage_options',
            'smliser-doc',
            array( RESTAPI::class, 'index_rest_doc' )
        );

        $this->accounts_page_id = add_submenu_page(
            'smliser-admin',
            'Accounts',
            'Accounts',
            'manage_options',
            'smliser-access-control',
            array( AccessControlPage::class, 'router' )
        );

        $this->options_page_id = add_submenu_page(
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
    public function submenu_index_name() {
        global $submenu;

        if ( isset( $submenu['smliser-admin'] ) ) {
            $submenu['smliser-admin'][0][0] = 'Overview';
        }
    }

    /**
     * Render the Smart License Server admin top navigation header.
     *
     * @param array $args {
     *     @type array  $breadcrumbs
     *     @type array  $actions
     *     @type string $nav_class
     *     @type string $content_class
     *     @type array  $attributes
     * }
     * @param bool $echo
     *
     * @return string|null
     */
    public static function print_admin_top_menu( array $args = array(), bool $echo = true ) {

        $defaults = array(
            'breadcrumbs'   => array(),
            'actions'       => array(),
            'nav_class'     => '',
            'content_class' => '',
            'attributes'    => array(),
        );

        $args = parse_args( $args, $defaults );

        $print_active = fn ( bool $cond ) => $cond ? ' active' : '';

        /**
         * Helper to render arbitrary attributes safely.
         */
        $render_attributes = static function ( array $attributes ) : string {

            $html = '';

            foreach ( $attributes as $key => $value ) {

                if ( is_bool( $value ) ) {
                    if ( $value ) {
                        $html .= ' ' . esc_attr( $key );
                    }
                    continue;
                }

                $html .= sprintf(
                    ' %s="%s"',
                    esc_attr( $key ),
                    esc_attr( $value )
                );
            }

            return $html;
        };

        ob_start();
        ?>

        <nav
            class="smliser-top-nav <?php echo esc_attr( $args['nav_class'] ); ?>"
            <?php echo $render_attributes( $args['attributes'] ); // phpcs:ignore ?>
        >
            <div class="smliser-top-nav-content <?php echo esc_attr( $args['content_class'] ); ?>">

                <?php if ( ! empty( $args['breadcrumbs'] ) ) : ?>
                    <div class="smliser-breadcrumb">

                        <?php
                        $breadcrumb_count = count( $args['breadcrumbs'] );
                        $current_index    = 0;

                        foreach ( $args['breadcrumbs'] as $breadcrumb ) :

                            $current_index++;

                            $breadcrumb = parse_args(
                                $breadcrumb,
                                array(
                                    'label'      => '',
                                    'url'        => '',
                                    'icon'       => '',
                                    'class'      => '',
                                    'attributes' => array(),
                                )
                            );

                            $tag = ! empty( $breadcrumb['url'] ) ? 'a' : 'span';
                            ?>

                            <<?php echo esc_html( $tag ); ?>
                                class="<?php echo esc_attr( $breadcrumb['class'] ); ?>"
                                <?php if ( 'a' === $tag ) : ?>
                                    href="<?php echo esc_url( $breadcrumb['url'] ); ?>"
                                <?php endif; ?>
                                <?php echo $render_attributes( $breadcrumb['attributes'] ); // phpcs:ignore ?>
                            >

                                <?php if ( ! empty( $breadcrumb['icon'] ) ) : ?>
                                    <i class="<?php echo esc_attr( $breadcrumb['icon'] ); ?>"></i>
                                <?php endif; ?>

                                <?php echo esc_html( $breadcrumb['label'] ); ?>

                            </<?php echo esc_html( $tag ); ?>>

                            <?php if ( $current_index < $breadcrumb_count ) : ?>
                                <span>/</span>
                            <?php endif; ?>

                        <?php endforeach; ?>

                    </div>
                <?php endif; ?>


                <?php if ( ! empty( $args['actions'] ) ) : ?>
                    <div class="smliser-quick-actions">

                        <?php foreach ( $args['actions'] as $action ) :

                            $action = parse_args(
                                $action,
                                array(
                                    'label'      => '',
                                    'url'        => '',
                                    'title'      => '',
                                    'icon'       => '',
                                    'active'     => false,
                                    'class'      => '',
                                    'attributes' => array(),
                                    'target'     => '',
                                    'rel'        => '',
                                    'data'       => array(),
                                )
                            );

                            /**
                             * Merge data-* attributes
                             */
                            foreach ( (array) $action['data'] as $data_key => $data_value ) {
                                $action['attributes'][ 'data-' . $data_key ] = $data_value;
                            }

                            ?>

                            <a
                                class="smliser-menu-link<?php echo esc_attr( $print_active( (bool) $action['active'] ) ); ?> <?php echo esc_attr( $action['class'] ); ?>"
                                href="<?php echo esc_url( $action['url'] ); ?>"
                                title="<?php echo esc_attr( $action['title'] ); ?>"
                                <?php if ( ! empty( $action['target'] ) ) : ?>
                                    target="<?php echo esc_attr( $action['target'] ); ?>"
                                <?php endif; ?>
                                <?php if ( ! empty( $action['rel'] ) ) : ?>
                                    rel="<?php echo esc_attr( $action['rel'] ); ?>"
                                <?php endif; ?>
                                <?php echo $render_attributes( $action['attributes'] ); // phpcs:ignore ?>
                            >

                                <?php if ( ! empty( $action['icon'] ) ) : ?>
                                    <i class="<?php echo esc_attr( $action['icon'] ); ?>"></i>
                                <?php endif; ?>

                                <?php echo esc_html( $action['label'] ); ?>

                            </a>

                        <?php endforeach; ?>

                    </div>
                <?php endif; ?>

            </div>
        </nav>

        <?php
        $output = ob_get_clean();

        if ( true === $echo ) {
            echo $output; // phpcs:ignore
            return null;
        }

        return $output;
    }

    /**
     * Dispatch the WordPress menu call to the handler with the request object.
     */
    public function router() {
        pp( \get_current_screen() );
    }
}