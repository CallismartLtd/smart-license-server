<?php
/**
 * The admin options page handler class
 * 
 * @author Callistus Nwachukwu
 * @package Smliser\classes
 */
namespace SmartLicenseServer\Admin;
use SmartLicenseServer\Monetization\ProviderCollection;

defined( 'SMLISER_ABSPATH' ) || exit;

class OptionsPage {
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
            $providers = ProviderCollection::instance()->get_providers();

            include_once SMLISER_PATH . 'templates/admin/options/all-providers.php';
        }
    }

    /**
     * Settings page for individual monetization provider
     */
    private static function provider_settings() {
        $provider = ProviderCollection::instance()->get_provider( smliser_get_query_param( 'provider' ) );

        if ( $provider ) {
            $name       = $provider->get_name();
            $id         = $provider->get_id();
            $settings   = $provider->get_settings();
        }

        include_once SMLISER_PATH . 'templates/admin/options/monetizations.php';
    }
}