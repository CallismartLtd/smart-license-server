<?php
/**
 * The admin options page handler class
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Admin
 * @since   1.0.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Admin;

use SmartLicenseServer\Email\EmailProviderCollection;
use SmartLicenseServer\Email\Providers\EmailProviderInterface;
use SmartLicenseServer\Monetization\ProviderCollection;

use function sprintf, smliser_settings_adapter;

defined( 'SMLISER_ABSPATH' ) || exit;

class OptionsPage {

    /**
     * Page router.
     */
    public static function router(): void {
        $tab = smliser_get_query_param( 'tab' );

        switch ( $tab ) {
            case 'routes':
                self::routes_setting();
                break;

            case 'monetization':
                self::monetization_options();
                break;

            case 'email':
                self::email_options();
                break;

            default:
                self::general_settings();
        }
    }

    /*
    |---------
    | ROUTING
    |---------
    */

    /**
     * General settings page.
     */
    private static function general_settings(): void {
        include_once SMLISER_PATH . 'templates/admin/options/general.php';
    }

    /**
     * Permalink/routes settings page.
     */
    private static function routes_setting(): void {
        include_once SMLISER_PATH . '/templates/admin/options/routing.php';
    }

    /**
     * Monetization providers settings page.
     */
    private static function monetization_options(): void {
        if ( smliser_has_query_param( 'provider' ) ) {
            self::monetization_provider_settings();
        } else {
            $providers = ProviderCollection::instance()->get_providers();
            include_once SMLISER_PATH . 'templates/admin/options/monetization-providers.php';
        }
    }

    /**
     * Settings page for an individual monetization provider.
     */
    private static function monetization_provider_settings(): void {
        $provider_key   = smliser_get_query_param( 'provider' );
        $provider       = ProviderCollection::instance()->get_provider( $provider_key );
        $name           = $provider?->get_name() ?? '';
        $id             = $provider?->get_id() ?? '';
        $settings       = $provider?->get_settings() ?? [];
        

        include_once SMLISER_PATH . 'templates/admin/options/monetizations.php';
    }

    /**
     * Email settings page dispatcher.
     *
     * Routes to individual provider settings when a provider query param
     * is present, otherwise renders the provider list with global email settings.
     */
    private static function email_options(): void {
        if ( smliser_has_query_param( 'provider' ) ) {
            self::email_provider_settings();
        } else {
            $collection       = EmailProviderCollection::instance();
            $providers        = $collection->get_providers();
            $default_provider = EmailProviderCollection::get_default_provider_id();
            $email_fields     = static::email_settings_fields();

            include_once SMLISER_PATH . 'templates/admin/options/email.php';
        }
    }

    /**
     * Settings page for an individual email provider.
     */
    private static function email_provider_settings(): void {
        $provider_key = smliser_get_query_param( 'provider' );
        $collection  = EmailProviderCollection::instance();
        $provider    = $collection->get_provider( $provider_key );

        $provider_name   = $provider?->get_name() ?? '';
        $provider_id     = $provider?->get_id() ?? '';
        $schema          = $provider?->get_settings_schema() ?? [];
        $is_default      = EmailProviderCollection::get_default_provider_id() === $provider_id;

        // Pre-populate each field with persisted value.
        $saved_settings = [];
        foreach ( $schema as $key => $_ ) {
            $saved_settings[ $key ] = EmailProviderCollection::get_option( $provider_id, $key );
        }

        include_once SMLISER_PATH . 'templates/admin/options/email-provider.php';
    }

    /*
    |--------
    | FIELDS
    |--------
    */

    /**
     * Global email settings fields.
     *
     * These are provider-agnostic settings that apply regardless of
     * which provider is active — default sender identity, fallback
     * behaviour, and provider selection.
     *
     * @return array<int, array<string, mixed>>
     */
    protected static function email_settings_fields(): array {
        $settings = smliser_settings_adapter();

        return [
            [
                'label' => 'Default Email Provider',
                'help'  => 'The email provider used for all outgoing system emails. Configure individual providers using the cards below.',
                'input' => [
                    'type'    => 'select',
                    'name'    => EmailProviderCollection::DEFAULT_PROVIDER_KEY,
                    'value'   => EmailProviderCollection::get_default_provider_id() ?? '',
                    'options' => array_map(
                        static fn( EmailProviderInterface $p ) => $p->get_name(),
                        EmailProviderCollection::instance()->get_providers()
                    ),
                ],
            ],

            [
                'label' => 'Default From Name',
                'help'  => 'Sender name used in outgoing emails when the active provider does not have a From Name configured.',
                'input' => [
                    'type'  => 'text',
                    'name'  => EmailProviderCollection::DEFAULT_SENDER_NAME_KEY,
                    'value' => EmailProviderCollection::instance()->get_default_sender_name(),
                    'attr'  => [
                        'autocomplete' => 'off',
                        'spellcheck'   => 'off',
                    ],
                ],
            ],

            [
                'label' => 'Default From Address',
                'help'  => 'Sender email address used in outgoing emails when the active provider does not have a From Email configured.',
                'input' => [
                    'type'  => 'text',
                    'name'  => EmailProviderCollection::DEFAULT_SENDER_EMAIL_KEY,
                    'value' => EmailProviderCollection::instance()->get_default_sender_email(),
                    'attr'  => [
                        'autocomplete' => 'off',
                        'spellcheck'   => 'off',
                    ],
                ],
            ],
        ];
    }

    /**
     * System settings form fields.
     *
     * Email-specific fields (from_name, from_address) have been moved
     * to email_settings_fields() and are no longer included here.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function system_settings_fields(): array {
        $settings = smliser_settings_adapter();

        return [
            [
                'label' => 'Repository Name',
                'help'  => 'Public name of this license repository. This may appear in system emails, API responses, and administrative interfaces.',
                'input' => [
                    'type'  => 'text',
                    'name'  => 'repository_name',
                    'value' => $settings->get( 'repository_name', SMLISER_APP_NAME, true ),
                    'attr'  => [
                        'autocomplete' => 'off',
                        'spellcheck'   => 'off',
                    ],
                ],
            ],

            [
                'label' => 'Administration Email',
                'help'  => 'Primary email address for receiving system notifications, error reports, and administrative alerts.',
                'input' => [
                    'type'  => 'text',
                    'name'  => 'admin_email',
                    'value' => $settings->get( 'admin_email', '', true ),
                    'attr'  => [
                        'autocomplete' => 'off',
                        'spellcheck'   => 'off',
                    ],
                ],
            ],

            [
                'label' => 'Hosting Email',
                'help'  => 'Designated contact email for server, infrastructure, or application hosting-related issues and notifications.',
                'input' => [
                    'type'  => 'text',
                    'name'  => 'hosting_email',
                    'value' => $settings->get( 'hosting_email', '', true ),
                    'attr'  => [
                        'autocomplete' => 'off',
                        'spellcheck'   => 'off',
                    ],
                ],
            ],

            [
                'label' => 'Support Email',
                'help'  => 'Customer-facing support email address used in license communications and support responses.',
                'input' => [
                    'type'  => 'text',
                    'name'  => 'support_email',
                    'value' => $settings->get( 'support_email', '', true ),
                    'attr'  => [
                        'autocomplete' => 'off',
                        'spellcheck'   => 'off',
                    ],
                ],
            ],

            [
                'label' => 'License Key Prefix',
                'help'  => 'Prefix automatically added to generated license keys (e.g., SMLISER-XXXX-XXXX). Helps identify the issuing system.',
                'input' => [
                    'type'  => 'text',
                    'name'  => 'license_prefix',
                    'value' => $settings->get( 'license_prefix', 'SMLISER', true ),
                    'attr'  => [
                        'autocomplete' => 'off',
                        'spellcheck'   => 'off',
                    ],
                ],
            ],

            [
                'label' => 'Default License Duration (Days)',
                'help'  => 'Default number of days a newly generated license remains valid when no expiration date is specified.',
                'input' => [
                    'type'  => 'number',
                    'name'  => 'default_license_duration',
                    'value' => $settings->get( 'default_license_duration', 365, true ),
                    'attr'  => [ 'min' => 1 ],
                ],
            ],

            [
                'label' => 'Default Activation Limit',
                'help'  => 'Default number of activations allowed per license key.',
                'input' => [
                    'type'  => 'number',
                    'name'  => 'default_activation_limit',
                    'value' => $settings->get( 'default_activation_limit', 1, true ),
                    'attr'  => [ 'min' => 1 ],
                ],
            ],

            [
                'label' => 'API Rate Limit (Per Minute)',
                'help'  => 'Maximum number of API requests allowed per client within a one-minute window.',
                'input' => [
                    'type'  => 'number',
                    'name'  => 'api_rate_limit',
                    'value' => $settings->get( 'api_rate_limit', 60, true ),
                    'attr'  => [ 'min' => 1 ],
                ],
            ],

            [
                'label' => 'Log Retention (Days)',
                'help'  => 'Number of days system logs are retained before automatic cleanup.',
                'input' => [
                    'type'  => 'number',
                    'name'  => 'log_retention_days',
                    'value' => $settings->get( 'log_retention_days', 30, true ),
                    'attr'  => [ 'min' => 1 ],
                ],
            ],

            [
                'label' => 'Environment Mode',
                'help'  => 'Defines whether this repository operates in production, staging, or development mode.',
                'input' => [
                    'type'    => 'select',
                    'name'    => 'environment_mode',
                    'value'   => $settings->get( 'environment_mode', 'production', true ),
                    'options' => [
                        'production'  => 'Production',
                        'staging'     => 'Staging',
                        'development' => 'Development',
                    ],
                ],
            ],

            [
                'label' => 'Terms URL',
                'help'  => 'Full URL to your Terms of Service or license agreement page.',
                'input' => [
                    'type'  => 'text',
                    'name'  => 'terms_url',
                    'value' => $settings->get( 'terms_url', '', true ),
                    'attr'  => [
                        'autocomplete' => 'off',
                        'spellcheck'   => 'off',
                    ],
                ],
            ],
        ];
    }

    /*
    |------
    | MENU
    |------
    */

    /**
     * Get menu args.
     *
     * @return array<string, mixed>
     */
    protected static function get_menu_args(): array {
        $tab         = smliser_get_query_param( 'tab' );
        $current_url = smliser_get_current_url()->remove_query_param( 'message', 'tab', 'section', 'provider' );

        $title = match ( $tab ) {
            'routes'        => 'Page Routing',
            'monetization'  => 'Monetization Providers',
            'email'         => 'Email Providers',
            default         => 'General Settings',
        };

        return [
            'breadcrumbs' => [
                [
                    'label' => 'General Settings',
                    'url'   => $current_url,
                    'icon'  => 'dashicons dashicons-admin-home',
                ],
                [
                    'label' => $title,
                ],
            ],
            'actions' => [
                [
                    'title'  => 'Monetizations',
                    'label'  => 'Monetizations',
                    'url'    => $current_url->add_query_param( 'tab', 'monetization' ),
                    'icon'   => 'ti ti-cash-register',
                    'active' => 'monetization' === $tab,
                ],
                [
                    'title'  => 'Email Providers Settings',
                    'label'  => 'Emails',
                    'url'    => $current_url->add_query_param( 'tab', 'email' ),
                    'icon'   => 'ti ti-mail',
                    'active' => 'email' === $tab,
                ],
                [
                    'title'  => 'Routes Settings',
                    'label'  => 'Routes',
                    'url'    => $current_url->add_query_param( 'tab', 'routes' ),
                    'icon'   => 'ti ti-globe',
                    'active' => 'routes' === $tab,
                ],
            ],
        ];
    }
}