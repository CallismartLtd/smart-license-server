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
        
        switch( $tab ) {
            case 'routes':
                self::routes_setting();
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
        include_once SMLISER_PATH . 'templates/admin/options/general.php';
    }

    /**
     * Permalink settings
     */
    private static function routes_setting() {
        
        include_once SMLISER_PATH . '/templates/admin/options/routing.php';
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

    /**
     * Get menu args
     */
    protected static function get_menu_args() : array {
        $tab            = \smliser_get_query_param( 'tab' );
        $license_id     = \smliser_get_query_param( 'license_id' );
        $current_url    = \smliser_get_current_url()->remove_query_param( 'message', 'tab', 'section', 'provider' );
        $title  = match ( $tab ) {
            'logs'      => 'License Activity Logs',
            'add-new'   => 'Add new license',
            'edit'          => 'Edit license',
            'routes'         => 'Page Routing',
            'monetization'  => 'Monetization Providers Settings',
            default         => 'General Settings'
        };

        $args   = array(
            'breadcrumbs'   => array(
                array(
                    'label' => 'General Settings',
                    'url'   => $current_url,
                    'icon'  => 'dashicons dashicons-admin-home'
                ),
                array(
                    'label' => $title,
                )
            ),
            'actions'   => array(
                array(
                    'title'     => 'Monetizations',
                    'label'     => 'Monetizations',
                    'url'       => $current_url->add_query_param( 'tab', 'monetization' ),
                    'icon'      => 'ti ti-cash-register',
                    'active'    => 'monetization' === $tab
                ),

                array(
                    'title'     => 'Routes Settings',
                    'label'     => 'Routes',
                    'url'       => $current_url->add_query_param( 'tab', 'routes' ),
                    'icon'      => 'ti ti-globe',
                    'active'    => 'routes' === $tab
                ),
            )
        );

        return $args;
    }

    /**
     * System Settings form fields
     * 
     * @return array
     */
    public static function system_settings_field() : array {
        return array(
            'license_issuer',
            'terms_url',
            'smliser_repo_base_perma',
            'smliser_license_prefix',
        );
    }
}