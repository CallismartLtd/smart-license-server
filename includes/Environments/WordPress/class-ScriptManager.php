<?php
/**
 * Script manager class file.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Environments\WordPress
 */

namespace SmartLicenseServer\Environments\WordPress;

use SmartLicenseServer\Security\Permission\Capability;
use SmartLicenseServer\Security\Permission\Role;

use function wp_register_script, wp_register_style, is_admin, wp_enqueue_script, wp_enqueue_style,
wp_localize_script;
/**
 * Manages JavaScript and CSS files.
 */
final class ScriptManager {
    /**
     * All CSS files and their dependencies.
     */
    private function allCSS() {
        return array(
            'smliser-styles'    => array(
                'src'   => SetUp::assets_url() . 'css/smliser-styles.css',
                'deps'  => array(),
                'ver'   => SMLISER_VER,
                'media' => 'all'
                
            ),
            'smliser-apps-uploader'   => array(
                'src'   => SetUp::assets_url() . 'css/apps-uploader.css',
                'deps'  => array(),
                'ver'   => SMLISER_VER,
                'media' => 'all'
                
            ),
            'smliser-form-styles'   => array(
                'src'   => SetUp::assets_url() . 'css/smliser-forms.css',
                'deps'  => array(),
                'ver'   => SMLISER_VER,
                'media' => 'all'
                
            ),
            'select2'       => array(
                'src'   => SetUp::assets_url() . 'css/select2.min.css',
                'deps'  => array(),
                'ver'   => SMLISER_VER,
                'media' => 'all'
                
            ),
            'smliser-nanojson'    => array(
                'src'   => SetUp::assets_url() . 'css/nanojson.min.css',
                'deps'  => array(),
                'ver'   => SMLISER_VER,
                'media' => 'all'
                
            ),
            'smliser-tabler-icons'    => array(
                'src'   => SetUp::assets_url() . 'icons/tabler-icons.min.css',
                'deps'  => array(),
                'ver'   => SMLISER_VER,
                'media' => 'all'
                
            ),
            'smliser-role-builder'    => array(
                'src'   => SetUp::assets_url() . 'css/role-builder.css',
                'deps'  => array(),
                'ver'   => SMLISER_VER,
                'media' => 'all'
                
            ),
            'smliser-modal'    => array(
                'src'   => SetUp::assets_url() . 'css/smliser-modal.css',
                'deps'  => array(),
                'ver'   => SMLISER_VER,
                'media' => 'all'
                
            ),

            'smliser-json-editor'   => array(
                'src'   => SetUp::assets_url() . 'css/smliser-json-editor.css',
                'deps'  => array( 'smliser-styles', 'smliser-modal' ),
                'ver'   => SMLISER_VER,
                'media' => 'all',
            )
        );
    }

    /**
     * All JavaScript files and their dependencies.
     */
    private function allJS() {
        return array(
            'smliser-script'    => array(
                'src'       => SetUp::assets_url() . 'js/main-script.js',
                'deps'      => array( 'jquery' ),
                'ver'       => SMLISER_VER,
                'footer'    => true
            ),
            'smliser-nanojson'    => array(
                'src'       => SetUp::assets_url() . 'js/nanojson.min.js',
                'deps'      => array( 'jquery', 'smliser-script' ),
                'ver'       => SMLISER_VER,
                'footer'    => true
            ),
            'smliser-apps-uploader'    => array(
                'src'       => SetUp::assets_url() . 'js/apps-uploader.js',
                'deps'      => array( 'jquery', 'smliser-script' ),
                'ver'       => SMLISER_VER,
                'footer'    => true
            ),
            'select2'           => array(
                'src'       => SetUp::assets_url() . 'js/Select2/select2.min.js',
                'deps'      => array( 'jquery' ),
                'ver'       => SMLISER_VER,
                'footer'    => true
            ),
            'smliser-tinymce'   => array(
                'src'       => SetUp::assets_url() . 'js/tinymce/tinymce.min.js',
                'deps'      => array( 'jquery' ),
                'ver'       => SMLISER_VER,
                'footer'    => true
            ),
            'smliser-admin-repository'    => array(
                'src'       => SetUp::assets_url() . 'js/admin-repository.js',
                'deps'      => array( 'jquery', 'smliser-script' ),
                'ver'       => SMLISER_VER,
                'footer'    => true
            ),
            
            'smliser-role-builder'    => array(
                'src'       => SetUp::assets_url() . 'js/role-builder.js',
                'deps'      => array('jquery' ),
                'ver'       => SMLISER_VER,
                'footer'    => true
            ),

            'smliser-chart'    => array(
                'src'       => SetUp::assets_url() . 'js/Chartjs/chart.min.js',
                'deps'      => array('jquery' ),
                'ver'       => SMLISER_VER,
                'footer'    => true
            ),

            'smliser-modal'    => array(
                'src'       => SetUp::assets_url() . 'js/smliser-modal.js',
                'deps'      => array('jquery' ),
                'ver'       => SMLISER_VER,
                'footer'    => true
            ),

            'smliser-json-editor'    => array(
                'src'       => SetUp::assets_url() . 'js/smliser-json-editor.js',
                'deps'      => array( 'jquery', 'smliser-script', 'smliser-modal' ),
                'ver'       => SMLISER_VER,
                'footer'    => true
            )
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
        wp_enqueue_script( 'smliser-script' );
        
        if ( is_admin() ) {
            wp_enqueue_script( 'smliser-modal' );
        }
        if ( is_admin() && 'toplevel_page_smliser-admin' === $s || 'smart-license-server_page_repository' === $s ) {
            wp_enqueue_script( 'smliser-chart' );
        }

        if ( 'smart-license-server_page_smliser-bulk-message' === $s 
            || 'smart-license-server_page_licenses' === $s 
            || 'smart-license-server_page_smliser-access-control' === $s 
            
            ) {
            wp_enqueue_script( 'select2' );
        }

        if ( 'smart-license-server_page_repository' === $s ) {
            wp_enqueue_media();
            wp_enqueue_script( 'smliser-apps-uploader' );
            wp_enqueue_script( 'smliser-json-editor' );
            wp_enqueue_script( 'smliser-admin-repository' );
        }

        if ( 'smart-license-server_page_smliser-access-control' === $s ) {
            wp_enqueue_script( 'smliser-role-builder' );
        }

        $vars   = array(
            'ajaxURL'  => admin_url( 'admin-ajax.php' ),
            'nonce'             => wp_create_nonce( 'smliser_nonce' ),
            'admin_url'         => admin_url(),
            'wp_spinner_gif'    => admin_url( 'images/spinner.gif' ),
            'wp_spinner_gif_2x' => admin_url( 'images/spinner-2x.gif' ),
            'app_search_api'    => rest_url( SetUp::instance()->namespace() . '/repository/' ),
            'default_roles'     => [
                'roles'         => Role::all( true ),
                'capabilities'  => Capability::get_caps()
            ]
        );

        // Script localizer.
        wp_localize_script( 'smliser-script', 'smliser_var', $vars );
    }

    /**
     * Load styles
     */
    public function enqueue_styles( $s ) {
        wp_enqueue_style( 'smliser-styles' );
        wp_enqueue_style( 'smliser-form-styles' );
    
        if ( is_admin() ) {
            wp_enqueue_style( 'smliser-modal' );
        }

        if ( 'smart-license-server_page_smliser-bulk-message' === $s 
            || 'smart-license-server_page_licenses' === $s
            || 'smart-license-server_page_smliser-access-control' === $s 
            
        ) {
            wp_enqueue_style( 'select2' );
        }

        if ( 'smart-license-server_page_repository' === $s ) {
            wp_enqueue_style( 'smliser-apps-uploader' );
            wp_enqueue_style( 'smliser-json-editor' );
        }

        if ( is_admin() ) {
            wp_enqueue_style( 'smliser-tabler-icons' );
        }

        if ( 'smart-license-server_page_smliser-access-control' === $s ) {
            wp_enqueue_style( 'smliser-role-builder' );
        }
    
    }
}