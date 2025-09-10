<?php
/**
 * The admin options page handler class
 * 
 * @author Callistus Nwachukwu
 * @package Smliser\classes
 */
namespace SmartLicenseServer\admin;
use SmartLicenseServer\Monetization\Provider_Collection;

defined( 'ABSPATH' ) || exit;

class Options_Page {
    /**
     * Run action hooks.
     */
    public static function actions() {
        add_action( 'admin_post_smliser_options', array( __CLASS__, 'options_form_handler' ) );
    }

    /**
     * Page router
     */
    public static function router() {
        $tab = smliser_get_query_param( 'tab' );
        $tabs = array(
            ''              => 'General',
            'monetization'  => 'Monetization Providers',
            'pages'         => 'Page Setup',
            'api-keys'      => 'REST API',

        );
        
        echo wp_kses_post( smliser_sub_menu_nav( $tabs, 'Settings', 'smliser-options', $tab, 'tab' ) );
        switch( $tab ) {
            case 'api-keys': 
                self::api_keys_option();
                break;
            case 'pages':
                self::pages_options();
                break;

            case 'monetization':
                self::monetization_options();
                break;

            default:
            self::general_settings();
        }
    }

    /**
     * The general settings page
     */
    private static function general_settings() {
        include_once SMLISER_PATH . 'templates/admin/options/options.php';
    }

    /**
     * API Keys options page
     */
    private static function api_keys_option() {
        $all_api_data   = \Smliser_API_Cred::get_all();
        include_once SMLISER_PATH . 'templates/admin/options/api-keys.php';
    }
    /**
     * Permalink settings
     */
    private static function pages_options() {
        
        include_once SMLISER_PATH . '/templates/admin/options/pages-setup.php';
    }

    /**
     * Monetization providers settings page
     */
    private static function monetization_options() {
        if ( smliser_has_query_param( 'provider' ) ) {
            self::provider_settings();

        } else {
            $providers = Provider_Collection::instance()->get_providers();

            include_once SMLISER_PATH . 'templates/admin/options/all-providers.php';
        }
    }

    /**
     * Settings page for individual monetization provider
     */
    private static function provider_settings() {
        $provider = Provider_Collection::instance()->get_provider( smliser_get_query_param( 'provider' ) );

        if ( $provider ) {
            $name       = $provider->get_name();
            $id         = $provider->get_id();
            $settings   = $provider->get_settings();
        }

        include_once SMLISER_PATH . 'templates/admin/options/monetizations.php';
    }

    /**
     * Options form handler
     */
    public static function options_form_handler() {

        if ( isset( $_POST['smliser_page_setup'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['smliser_options_form'] ) ), 'smliser_options_form' ) ) {
            
            if ( isset( $_POST['smliser_permalink'] ) ) {
                $permalink = preg_replace( '~(^\/|\/$)~', '', sanitize_text_field( wp_unslash( $_POST['smliser_permalink'] ) ) );
                update_option( 'smliser_repo_base_perma', ! empty( $permalink ) ? strtolower( $permalink ) : 'plugins'  );
                
            }

            set_transient( 'smliser_form_success', true, 30 );
        }

        wp_safe_redirect( admin_url( 'admin.php?page=smliser-options&path=pages' ) );
        exit;
    }
}