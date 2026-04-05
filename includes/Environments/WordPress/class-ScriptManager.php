<?php
/**
 * Script manager class file.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Environments\WordPress
 */

namespace SmartLicenseServer\Environments\WordPress;

use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Environment;
use SmartLicenseServer\Security\Permission\Capability;
use SmartLicenseServer\Security\Permission\Role;

use function wp_register_script, wp_register_style, is_admin, wp_enqueue_script, wp_enqueue_style, sprintf,
wp_localize_script;
/**
 * Manages JavaScript and CSS files.
 */
final class ScriptManager {
    /**
     * Class constructor.
     */
    public function __construct( private Request $request ) {}

    /**
     * All CSS files and their dependencies.
     */
    private function allCSS() {
        return array(
            'smliser-styles'    => array(
                'src'   => SetUp::assets_url( sprintf( 'css/smliser-styles%s.css', $this->script_suffix() ) ) ,
                'deps'  => array(),
                'ver'   => SMLISER_VER,
                'media' => 'all'
                
            ),
            'smliser-apps-uploader'   => array(
                'src'   => SetUp::assets_url( sprintf( 'css/apps-uploader%s.css', $this->script_suffix() ) ),
                'deps'  => array(),
                'ver'   => SMLISER_VER,
                'media' => 'all'
                
            ),
            'smliser-form-styles'   => array(
                'src'   => SetUp::assets_url( sprintf( 'css/smliser-forms%s.css', $this->script_suffix() ) ),
                'deps'  => array(),
                'ver'   => SMLISER_VER,
                'media' => 'all'
                
            ),
            'select2'       => array(
                'src'   => SetUp::assets_url( sprintf( 'css/select2%s.css', $this->script_suffix() ) ),
                'deps'  => array(),
                'ver'   => SMLISER_VER,
                'media' => 'all'
                
            ),

            'smliser-tabler-icons'    => array(
                'src'   => SetUp::assets_url( sprintf( 'icons/tabler-icons%s.css', $this->script_suffix() ) ),
                'deps'  => array(),
                'ver'   => SMLISER_VER,
                'media' => 'all'
                
            ),
            'smliser-role-builder'    => array(
                'src'   => SetUp::assets_url( sprintf( 'css/role-builder%s.css', $this->script_suffix() ) ),
                'deps'  => array(),
                'ver'   => SMLISER_VER,
                'media' => 'all'
                
            ),
            'smliser-modal'    => array(
                'src'   => SetUp::assets_url( sprintf( 'css/smliser-modal%s.css', $this->script_suffix() ) ),
                'deps'  => array(),
                'ver'   => SMLISER_VER,
                'media' => 'all'
                
            ),

            'smliser-json-editor'   => array(
                'src'   => SetUp::assets_url( sprintf( 'css/smliser-json-editor%s.css', $this->script_suffix() ) ),
                'deps'  => array( 'smliser-styles', 'smliser-modal' ),
                'ver'   => SMLISER_VER,
                'media' => 'all',
            ),

            'smliser-datetime-picker'   => array(
                'src'   => SetUp::assets_url( sprintf( 'css/smliser-datetime-picker%s.css', $this->script_suffix() ) ),
                'deps'  => array(),
                'ver'   => SMLISER_VER,
                'media' => 'all'
            ),
            'smliser-email-editor' => array(
                'src'   => SetUp::assets_url( sprintf( 'css/email-editor%s.css', $this->script_suffix() ) ),
                'deps'  => [ 'smliser-styles', 'smliser-modal' ],
                'ver'   => SMLISER_VER,
                'media' => 'all',
            ),
            'smliser-cache-stats' => array(
                'src'   => SetUp::assets_url( sprintf( 'css/cache-stats%s.css', $this->script_suffix() ) ),
                'deps'  => [ 'smliser-styles', 'smliser-modal' ],
                'ver'   => SMLISER_VER,
                'media' => 'all',
            ),
        );
    }

    /**
     * All JavaScript files and their dependencies.
     */
    private function allJS() {
        return array(
            'smliser-script'    => array(
                'src'       => SetUp::assets_url( sprintf( 'js/main-script%s.js' , $this->script_suffix() ) ),
                'deps'      => array( 'jquery' ),
                'ver'       => SMLISER_VER,
                'footer'    => true
            ),

            'smliser-apps-uploader'    => array(
                'src'       => SetUp::assets_url( sprintf( 'js/apps-uploader%s.js' , $this->script_suffix() ) ),
                'deps'      => array( 'jquery', 'smliser-script' ),
                'ver'       => SMLISER_VER,
                'footer'    => true
            ),
            'select2'           => array(
                'src'       => SetUp::assets_url( sprintf( 'js/Select2/select2%s.js' , $this->script_suffix() ) ),
                'deps'      => array( 'jquery' ),
                'ver'       => SMLISER_VER,
                'footer'    => true
            ),
            'smliser-tinymce'   => array(
                'src'       => SetUp::assets_url( 'js/tinymce/tinymce.min.js' ),
                'deps'      => array( 'jquery' ),
                'ver'       => SMLISER_VER,
                'footer'    => true
            ),
            'smliser-admin-repository'    => array(
                'src'       => SetUp::assets_url( sprintf( 'js/admin-repository%s.js' , $this->script_suffix() ) ),
                'deps'      => array( 'jquery', 'smliser-script' ),
                'ver'       => SMLISER_VER,
                'footer'    => true
            ),
            
            'smliser-role-builder'    => array(
                'src'       => SetUp::assets_url( sprintf( 'js/role-builder%s.js' , $this->script_suffix() ) ),
                'deps'      => array('jquery' ),
                'ver'       => SMLISER_VER,
                'footer'    => true
            ),

            'smliser-chart'    => array(
                'src'       => SetUp::assets_url( 'js/Chartjs/chart.min.js' ),
                'deps'      => array('jquery' ),
                'ver'       => SMLISER_VER,
                'footer'    => true
            ),

            'smliser-modal'    => array(
                'src'       => SetUp::assets_url( sprintf( 'js/smliser-modal%s.js' , $this->script_suffix() ) ),
                'deps'      => array('jquery' ),
                'ver'       => SMLISER_VER,
                'footer'    => true
            ),

            'smliser-json-editor'    => array(
                'src'       => SetUp::assets_url( sprintf( 'js/smliser-json-editor%s.js' , $this->script_suffix() ) ),
                'deps'      => array( 'jquery', 'smliser-script', 'smliser-modal' ),
                'ver'       => SMLISER_VER,
                'footer'    => true
            ),

            'smliser-datetime-picker'   => array(
                'src'       => Setup::assets_url( sprintf( 'js/smliser-datetime-picker%s.js' , $this->script_suffix() ) ),
                'deps'      => array(),
                'ver'       => SMLISER_VER,
                'footer'    => true,
            ),
            'smliser-email-editor' => array(
                'src'    => SetUp::assets_url( sprintf( 'js/email-editor%s.js' , $this->script_suffix() ) ),
                'deps'   => [ 'jquery', 'smliser-script', 'smliser-modal' ],
                'ver'    => SMLISER_VER,
                'footer' => true,
            ),
            'smliser-cache-stats' => array(
                'src'    => SetUp::assets_url( sprintf( 'js/cache-stats%s.js' , $this->script_suffix() ) ),
                'deps'   => [ 'jquery', 'smliser-script', 'smliser-modal' ],
                'ver'    => SMLISER_VER,
                'footer' => true,
            ),

            'smliser-jquery'    => array(
                'src'    => SetUp::assets_url( sprintf( 'js/jQuery/jQuery%s.js' , $this->script_suffix() ) ),
                'deps'   => [],
                'ver'    => SMLISER_VER,
                'footer' => true,
            )
        );
    }

    /**
     * Get all localizable vairables.
     */
    public function allVars() {
        return array(
            'ajaxURL'           => adminUrl( 'admin-ajax.php' )->get_href(),
            'nonce'             => wp_create_nonce( 'smliser_nonce' ),
            'admin_url'         => \adminUrl()->get_href(),
            'wp_spinner_gif'    => \adminUrl( 'images/spinner.gif' )->get_href(),
            'wp_spinner_gif_2x' => \adminUrl( 'images/spinner-2x.gif' )->get_href(),
            'app_search_api'    => \restAPIUrl( smliser_envProvider()->rest_namespace() . '/repository/' ),
            'default_roles'     => [
                'roles'         => Role::all( true ),
                'capabilities'  => Capability::get_caps()
            ]
        );
    }

    /**
     * Registers CSS files.
     */
    public function register_styles() : void {
        foreach ( $this->allCSS() as $handle => $style ) {
            wp_register_style( $handle, $style['src'], $style['deps'], $style['ver'], $style['media'] );
        }
    }

    /**
     * Registers JavaScript files.
     */
    public function register_scripts() : void {
        foreach( $this->allJS() as $handle => $script ) {
            wp_register_script( $handle, $script['src'], $script['deps'], $script['ver'], $script['footer'] );
        }
    }

    /**
     * Enqueues Scripts
     */
    public function enqueue_scripts( $s ) {
        wp_enqueue_script( 'smliser-datetime-picker' );
        wp_enqueue_script( 'select2' );
        wp_enqueue_script( 'smliser-script' );
        
        if ( is_admin() ) {
            wp_enqueue_script( 'smliser-modal' );
        }
        
        $enqueue_chart  = is_admin() && in_array( $this->request->get( 'page'), ['smliser-overview', 'smliser-repository'] );

        if ( $enqueue_chart ) {
            wp_enqueue_script( 'smliser-chart' );
        }

        if ( 'smliser-repository' === $this->request->get( 'page' ) ) {
            wp_enqueue_media();
            wp_enqueue_script( 'smliser-apps-uploader' );
            wp_enqueue_script( 'smliser-json-editor' );
            wp_enqueue_script( 'smliser-admin-repository' );
        }

        if ( 'smliser-access-control' === $this->request->get( 'page' ) ) {
            wp_enqueue_script( 'smliser-role-builder' );
        }

        if ( 'smliser-settings' === $this->request->get( 'page' ) ) {
            wp_enqueue_script( 'smliser-cache-stats' );
        }

        // Script localizer.
        wp_localize_script( 'smliser-script', 'smliser_var', $this->allVars() );
    }

    /**
     * Load styles
     */
    public function enqueue_styles() {
        wp_enqueue_style( 'smliser-datetime-picker' );
        wp_enqueue_style( 'select2' );
        wp_enqueue_style( 'smliser-styles' );
        wp_enqueue_style( 'smliser-form-styles' );

        wp_enqueue_style( 'smliser-modal' );
        

        if ( 'smliser-repository' === $this->request->get( 'page' ) ) {
            wp_enqueue_style( 'smliser-apps-uploader' );
            wp_enqueue_style( 'smliser-json-editor' );
        }

        if ( is_admin() ) {
            wp_enqueue_style( 'smliser-tabler-icons' );
        }

        if ( 'smliser-access-control' === $this->request->get( 'page' ) ) {
            wp_enqueue_style( 'smliser-role-builder' );
        }

        if ( 'smliser-settings' === $this->request->get( 'page' ) ) {
            wp_enqueue_style( 'smliser-cache-stats' );
        }
    
    }

    /**
     * Return the asset definitions required by the standalone email editor page.
     *
     * Because the editor renders as a full HTML document outside the normal
     * WordPress admin shell, wp_head() / wp_footer() never fire and nothing
     * enqueued through wp_enqueue_script / wp_enqueue_style reaches the page.
     * This method exposes the raw src URLs so the editor template can render
     * its own <link> and <script> tags in the correct order.
     *
     * Only the assets the editor actually needs are returned — the full
     * smliser asset registry is not exposed.
     *
     * Return shape:
     *   [
     *     'styles'  => [ [ 'handle' => string, 'src' => string ], ... ],
     *     'scripts' => [ [ 'handle' => string, 'src' => string ], ... ],
     *   ]
     *
     * Scripts are ordered so that dependencies come before dependants:
     *   jquery → smliser-script → smliser-modal → smliser-email-editor
     *
     * @return array<string, array<int, array<string, string>>>
     */
    public function get_email_editor_assets(): array {
        $all_css = $this->allCSS();
        $all_js  = $this->allJS();

        $styles = [
            'smliser-tabler-icons',
            'smliser-styles',
            'smliser-form-styles',
            'smliser-modal',
            'smliser-datetime-picker',
            'smliser-email-editor',
        ];

        $scripts = [
            'smliser-jquery',
            'select2',
            'smliser-datetime-picker',
            'smliser-script',
            'smliser-modal',
            'smliser-email-editor',
        ];

        return [
            'styles'  => array_map(
                fn( $handle ) => [
                    'handle' => $handle,
                    'src'    => $all_css[ $handle ]['src'],
                ],
                $styles
            ),
            'scripts' => array_map(
                fn( $handle ) => [
                    'handle' => $handle,
                    'src'    => $all_js[ $handle ]['src'],
                ],
                $scripts
            ),
        ];
    }

    /**
     * Get script suffix depending on whether debug is enabled or not.
     * 
     * @return string
     */
    private function script_suffix() : string {
        return defined( 'SCRIPT_DEBUG' ) && \SCRIPT_DEBUG ? '' : '.min';
    }
}