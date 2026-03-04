<?php
/**
 * The admin options page handler class
 * 
 * @author Callistus Nwachukwu
 * @package Smliser\classes
 */
namespace SmartLicenseServer\Admin;
use SmartLicenseServer\Monetization\ProviderCollection;

use function sprintf, smliser_settings_adapter;

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
    protected static function system_settings_fields() : array {

        $settings = smliser_settings_adapter();

        return array(

            array(
                'label' => 'Repository Name',
                'help'  => 'Public name of this license repository. This may appear in system emails, API responses, and administrative interfaces.',
                'input' => array(
                    'type'  => 'text',
                    'name'  => 'repository_name',
                    'value' => $settings->get( 'repository_name', SMLISER_APP_NAME, true ),
                    'attr'  => array(
                        'autocomplete' => 'off',
                        'spellcheck'   => 'off',
                    ),
                ),
            ),

            array(
                'label' => 'Administration Email',
                'help'  => 'Primary email address for receiving system notifications, error reports, and administrative alerts.',
                'input' => array(
                    'type'  => 'text',
                    'name'  => 'admin_email',
                    'value' => $settings->get( 'admin_email', '', true ),
                    'attr'  => array(
                        'autocomplete' => 'off',
                        'spellcheck'   => 'off',
                    ),
                ),
            ),

            array(
                'label' => 'Hosting Email',
                'help'  => 'Designated contact email for server, infrastructure, or application hosting-related issues and notifications.',
                'input' => array(
                    'type'  => 'text',
                    'name'  => 'hosting_email',
                    'value' => $settings->get( 'hosting_email', '', true ),
                    'attr'  => array(
                        'autocomplete' => 'off',
                        'spellcheck'   => 'off',
                    ),
                ),
            ),

            array(
                'label' => 'Support Email',
                'help'  => 'Customer-facing support email address used in license communications and support responses.',
                'input' => array(
                    'type'  => 'text',
                    'name'  => 'support_email',
                    'value' => $settings->get( 'support_email', '', true ),
                    'attr'  => array(
                        'autocomplete' => 'off',
                        'spellcheck'   => 'off',
                    ),
                ),
            ),

            array(
                'label' => 'Email From Name',
                'help'  => 'Name displayed as the sender in outgoing system emails.',
                'input' => array(
                    'type'  => 'text',
                    'name'  => 'email_from_name',
                    'value' => $settings->get( 'email_from_name', get_bloginfo( 'name' ), true ),
                    'attr'  => array(
                        'autocomplete' => 'off',
                        'spellcheck'   => 'off',
                    ),
                ),
            ),

            array(
                'label' => 'Email From Address',
                'help'  => 'Email address used as the sender for outgoing system emails.',
                'input' => array(
                    'type'  => 'text',
                    'name'  => 'email_from_address',
                    'value' => $settings->get( 'email_from_address', '', true ),
                    'attr'  => array(
                        'autocomplete' => 'off',
                        'spellcheck'   => 'off',
                    ),
                ),
            ),

            array(
                'label' => 'License Key Prefix',
                'help'  => 'Prefix automatically added to generated license keys (e.g., SMLISER-XXXX-XXXX). Helps identify the issuing system.',
                'input' => array(
                    'type'  => 'text',
                    'name'  => 'license_prefix',
                    'value' => $settings->get( 'license_prefix', 'SMLISER', true ),
                    'attr'  => array(
                        'autocomplete' => 'off',
                        'spellcheck'   => 'off',
                    ),
                ),
            ),

            array(
                'label' => 'Default License Duration (Days)',
                'help'  => 'Default number of days a newly generated license remains valid when no expiration date is specified.',
                'input' => array(
                    'type'  => 'number',
                    'name'  => 'default_license_duration',
                    'value' => $settings->get( 'default_license_duration', 365, true ),
                    'attr'  => array(
                        'min' => 1,
                    ),
                ),
            ),

            array(
                'label' => 'Default Activation Limit',
                'help'  => 'Default number of activations allowed per license key.',
                'input' => array(
                    'type'  => 'number',
                    'name'  => 'default_activation_limit',
                    'value' => $settings->get( 'default_activation_limit', 1, true ),
                    'attr'  => array(
                        'min' => 1,
                    ),
                ),
            ),

            array(
                'label' => 'API Rate Limit (Per Minute)',
                'help'  => 'Maximum number of API requests allowed per client within a one-minute window.',
                'input' => array(
                    'type'  => 'number',
                    'name'  => 'api_rate_limit',
                    'value' => $settings->get( 'api_rate_limit', 60, true ),
                    'attr'  => array(
                        'min' => 1,
                    ),
                ),
            ),

            array(
                'label' => 'Log Retention (Days)',
                'help'  => 'Number of days system logs are retained before automatic cleanup.',
                'input' => array(
                    'type'  => 'number',
                    'name'  => 'log_retention_days',
                    'value' => $settings->get( 'log_retention_days', 30, true ),
                    'attr'  => array(
                        'min' => 1,
                    ),
                ),
            ),

            array(
                'label' => 'Environment Mode',
                'help'  => 'Defines whether this repository operates in production, staging, or development mode.',
                'input' => array(
                    'type'    => 'select',
                    'name'    => 'environment_mode',
                    'value'   => $settings->get( 'environment_mode', 'production', true ),
                    'options' => array(
                        'production'  => 'Production',
                        'staging'     => 'Staging',
                        'development' => 'Development',
                    ),
                ),
            ),

            array(
                'label' => 'Terms URL',
                'help'  => 'Full URL to your Terms of Service or license agreement page. May be included in customer communications and API responses.',
                'input' => array(
                    'type'  => 'text',
                    'name'  => 'terms_url',
                    'value' => $settings->get( 'terms_url', '', true ),
                    'attr'  => array(
                        'autocomplete' => 'off',
                        'spellcheck'   => 'off',
                    ),
                ),
            ),

        );
    }
}