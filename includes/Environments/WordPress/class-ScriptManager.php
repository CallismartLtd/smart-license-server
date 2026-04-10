<?php
/**
 * WordPress script manager.
 * Handles enqueueing, registration, and localization.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Environments\WordPress
 */

namespace SmartLicenseServer\Environments\WordPress;

use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Security\Permission\Capability;
use SmartLicenseServer\Security\Permission\Role;
use SmartLicenseServer\Assets\CSS;
use SmartLicenseServer\Assets\JS;

use function wp_register_script, wp_register_style, is_admin, wp_enqueue_script, wp_enqueue_style, wp_localize_script;

final class ScriptManager {

    public function __construct( private Request $request ) {}

    /**
     * Get script suffix depending on debug.
     */
    private function script_suffix() : string {
        return defined( 'SCRIPT_DEBUG' ) && \SCRIPT_DEBUG ? '' : '.min';
    }

    /**
     * Registers CSS files.
     */
    public function register_styles() : void {
        foreach ( CSS::all( $this->script_suffix() ) as $handle => $style ) {
            wp_register_style( $handle, $style['src'], $style['deps'], $style['ver'], $style['media'] );
        }
    }

    /**
     * Registers JS files.
     */
    public function register_scripts() : void {
        foreach ( JS::all( $this->script_suffix() ) as $handle => $script ) {
            wp_register_script( $handle, $script['src'], $script['deps'], $script['ver'], $script['footer'] );
        }
    }

    /**
     * Enqueue scripts (WordPress specific).
     */
    public function enqueue_scripts() {
        wp_enqueue_script( 'smliser-datetime-picker' );
        wp_enqueue_script( 'select2' );
        wp_enqueue_script( 'smliser-script' );

        if ( is_admin() ) {
            wp_enqueue_script( 'smliser-modal' );
        }

        $enqueue_chart = is_admin() && in_array( $this->request->get( 'page' ), ['smliser-overview', 'smliser-repository'] );
        if ( $enqueue_chart ) {
            wp_enqueue_script( 'smliser-chart' );
        }

        if ( 'smliser-repository' === $this->request->get( 'page' ) ) {
            wp_enqueue_media();
            wp_enqueue_script( 'smliser-apps-uploader' );
            wp_enqueue_script( 'smliser-json-editor' );
            wp_enqueue_script( 'smliser-admin-repository' );
        }

        if ( 'smliser-accounts' === $this->request->get( 'page' ) ) {
            wp_enqueue_script( 'smliser-role-builder' );
        }

        if ( 'smliser-settings' === $this->request->get( 'page' ) ) {
            wp_enqueue_script( 'smliser-cache-stats' );
        }

        // Localize main script
        wp_localize_script( 'smliser-script', 'smliser_var', $this->allVars() );
    }

    /**
     * Enqueue CSS (WordPress specific)
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

        if ( 'smliser-accounts' === $this->request->get( 'page' ) ) {
            wp_enqueue_style( 'smliser-role-builder' );
        }

        if ( 'smliser-settings' === $this->request->get( 'page' ) ) {
            wp_enqueue_style( 'smliser-cache-stats' );
        }
    }

    /**
     * All variables to localize to JS.
     */
    private function allVars() : array {
        return [
            'ajaxURL'           => adminUrl( 'admin-ajax.php' )->get_href(),
            'nonce'             => wp_create_nonce( 'smliser_nonce' ),
            'admin_url'         => \adminUrl()->get_href(),
            'wp_spinner_gif'    => \adminUrl( 'images/spinner.gif' )->get_href(),
            'wp_spinner_gif_2x' => \adminUrl( 'images/spinner-2x.gif' )->get_href(),
            'app_search_api'    => \restAPIUrl( '/repository/' ),
            'default_roles'     => [
                'roles'         => Role::all( true ),
                'capabilities'  => Capability::get_caps()
            ]
        ];
    }
}