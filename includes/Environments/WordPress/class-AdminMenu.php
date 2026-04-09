<?php
/**
 * The admin menu class file.
 * 
 * @author Callistus Nwachukwu
 * @package Smliser\classes
 */

namespace SmartLicenseServer\Environments\WordPress;

use SmartLicenseServer\Admin\AdminConfiguration;
use SmartLicenseServer\Core\Request;

use function add_submenu_page, add_menu_page, parse_args, is_bool, sprintf;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * The admin menu class handles all admin menu registry and routing.
 */
class AdminMenu {
    const MENU_ICON = 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAyMCAyMCI+CiAgPGcgdHJhbnNmb3JtPSJ0cmFuc2xhdGUoMTAsIDEwKSBzY2FsZSgxLjQpIHRyYW5zbGF0ZSgtMTAsIC0xMCkiPgogICAgPHJlY3QgeD0iNC40IiB5PSI1LjYiIHdpZHRoPSIxMS4yIiBoZWlnaHQ9IjIiIHJ4PSIwLjYiIGZpbGw9IiNhN2FhYWQiLz4KICAgIDxyZWN0IHg9IjQuNCIgeT0iOC40IiB3aWR0aD0iMTEuMiIgaGVpZ2h0PSIyIiByeD0iMC42IiBmaWxsPSIjYTdhYWFkIi8+CiAgICA8cmVjdCB4PSI0LjQiIHk9IjExLjIiIHdpZHRoPSIxMS4yIiBoZWlnaHQ9IjIiIHJ4PSIwLjYiIGZpbGw9IiNhN2FhYWQiLz4KICAgIDxjaXJjbGUgY3g9IjUuNiIgY3k9IjYuNiIgcj0iMC41IiBmaWxsPSIjMzMzIiBvcGFjaXR5PSIwLjQiLz4KICAgIDxjaXJjbGUgY3g9IjUuNiIgY3k9IjkuNCIgcj0iMC41IiBmaWxsPSIjMzMzIiBvcGFjaXR5PSIwLjQiLz4KICAgIDxjaXJjbGUgY3g9IjUuNiIgY3k9IjEyLjIiIHI9IjAuNSIgZmlsbD0iIzMzMyIgb3BhY2l0eT0iMC40Ii8+CiAgICA8cGF0aCBkPSJNMTAgNCBMMTMuNiA1LjYgVjkuMiBDMTMuNiAxMiAxMS42IDE0IDEwIDE0LjggQzguNCAxNCA2LjQgMTIgNi40IDkuMiBWNS42IFoiIGZpbGw9IiNhN2FhYWQiLz4KICAgIDxjaXJjbGUgY3g9IjEwIiBjeT0iOC44IiByPSIxIiBmaWxsPSIjMzMzIiBvcGFjaXR5PSIwLjQiLz4KICAgIDxyZWN0IHg9IjkuNCIgeT0iOC44IiB3aWR0aD0iMS4yIiBoZWlnaHQ9IjIuNCIgcng9IjAuMyIgZmlsbD0iIzMzMyIgb3BhY2l0eT0iMC40Ii8+CiAgPC9nPgo8L3N2Zz4=';
    
    /**
     * The current request object
     * 
     * @var Request $request
     */
    private Request $request;

    private string $prefix  = 'smliser';

    /**
     * 
     */
    private AdminConfiguration $config;

    /**
     * Constructor
     * 
     * @param Request $request The  current request object.
     */
    public function __construct( AdminConfiguration $config, Request $request ) {
        $this->config   = $config;
        $this->request  = $request;
    }

    /**
     * Register admin menus.
     */
    public function register_menus() {
        $new_menu   = array(
            'slug'      => 'api-doc',
            'title'     => 'API Doc',
            'handler'   => 'smliser_rest_documentation'
        );

        $this->config->register( 'api_doc', $new_menu );

        $slug   = sprintf( '%s-overview', $this->prefix );
        add_menu_page( SMLISER_APP_NAME, SMLISER_APP_NAME, 'manage_options', $slug, array( $this, 'dispatch_request' ), self::MENU_ICON, 3.1 );

        foreach ( $this->config->all() as $key => $menu ) {
            if ( $this->config->is_root_menu( $key ) ) continue; // Already registered.

            $base_slug   = sprintf( '%s-%s', $this->prefix, $menu['slug'] );
            add_submenu_page( $slug, $menu['title'], $menu['title'], 'manage_options', $base_slug, [$this, 'dispatch_request'] );
        }
    }

    /**
     * Rename First menu item to Dashboard.
     */
    public function submenu_index_name() {
        global $submenu;
        
        $slug   = sprintf( '%s-overview', $this->prefix );
        if ( isset( $submenu[$slug] ) ) {
            $submenu[$slug][0][0] = 'Overview';
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
    public function dispatch_request() : void {
        if ( ! $this->request->hasValue( 'page' ) ) {
            return;
        }

        $page   = (string) $this->request->get( 'page' );
        $prefix = "{$this->prefix}-";

        if ( strpos( $page, $prefix ) === 0 ) {

            $slug = substr( $page, strlen( $prefix ) );
            $menu = $this->config->get( $slug );
            $handler = $menu['handler'] ?? null;
        } else {
            $dashboard  = $this->config->get( 'overview' );
            $handler    = $dashboard['handler'];
        }


        if ( is_callable( $handler ) ) {
            $handler( $this->request );
        }
    }
}