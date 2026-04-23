<?php
/**
 * The admin options page handler class
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Admin
 * @since   0.2.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Admin;

use SmartLicenseServer\Cache\CacheAdapterRegistry;
use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Email\EmailProvidersRegistry;
use SmartLicenseServer\Email\Providers\EmailProviderInterface;
use SmartLicenseServer\Email\Templates\EmailTemplateRegistry;
use SmartLicenseServer\Monetization\MonetizationRegistry;

use function sprintf, smliser_settings, smliser_render_template, compact;

defined( 'SMLISER_ABSPATH' ) || exit;

class OptionsPage {

    /**
     * Page router.
     * 
     * @param Request $request
     */
    public static function router( Request $request ): void {
        $tab = $request->get( 'tab' );

        switch ( $tab ) {
            case 'routes':
                self::routes_setting( $request );
                break;

            case 'monetization':
                self::monetization_options( $request );
                break;

            case 'email':
                self::email_options( $request );
                break;

            case 'cache':
                self::cache_options( $request );
                break;

            default:
                self::general_settings( $request );
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
    private static function general_settings( Request $request ): void {
        
        smliser_render_template( 'admin.options.index', compact( 'request' ) );
    }

    /**
     * Permalink/routes settings page.
     */
    private static function routes_setting( Request $request ): void {
        smliser_render_template( 'admin.options.routing', compact( 'request' ) );
    }

    /**
     * Monetization providers settings page.
     */
    private static function monetization_options( Request $request ): void {
        if ( $request->has( 'provider' ) ) {
            self::monetization_provider_settings( $request );
            return;
        } 
        
        $providers = smliser_monetization_registry()->all();
        smliser_render_template( 'admin.options.monetization-providers', compact( 'request', 'providers' ) );   
    }

    /**
     * Settings page for an individual monetization provider.
     */
    private static function monetization_provider_settings( Request $request ): void {
        $provider_key   = $request->get( 'provider' );
        $provider       = smliser_monetization_registry()->get_provider( $provider_key );
        $name           = $provider?->get_name() ?? '';
        $id             = $provider?->get_id() ?? '';
        $schema         = $provider?->get_settings_schema();
        $settings       = [];

        foreach ( $schema as $key => $data ) {
            $settings[] = array(
                'label' => $data['label'] ?? '',
                'help'  => $data['description'] ?? '',
                'input' => array(
                    'type'  => $data['type'] ?? 'text',
                    'name'  => $key,
                    'value' => MonetizationRegistry::get_option( $id, $key ) ?? $data['default'] ?? '',
                )
            );
        }

        $vars   = compact( 'request', 'provider', 'name', 'settings', 'id' );
        smliser_render_template( 'admin.options.monetizations', $vars );
    }

    /**
     * Email settings page dispatcher.
     *
     * Routes to individual provider settings when a provider query param
     * is present, to the template list when section=templates, to an
     * individual template when section=templates&template=key,
     * otherwise renders the provider list with global email settings.
     */
    private static function email_options( Request $request ): void {
        if ( $request->has( 'provider' ) ) {
            self::email_provider_settings( $request );
            return;
        }

        if ( $request->get( 'section' ) === 'templates' ) {
            self::email_template_options( $request );
            return;
        }

        $registry           = smliser_emailProvidersRegistry();
        $providers          = $registry->all( true, true );
        $default_provider   = EmailProvidersRegistry::get_default_provider_id();
        $email_fields       = static::email_settings_fields();

        $vars   = compact( 'registry', 'request', 'email_fields', 'providers', 'default_provider' );
        smliser_render_template( 'admin.options.email.index', $vars );
    }

    /**
     * Email template list or individual template view.
     *
     * Dispatches to the single template view when a template key
     * is present, otherwise renders the full template list.
     */
    private static function email_template_options( Request $request ): void {
        if ( smliser_has_query_param( 'template' ) ) {
            self::email_template_editor( $request );
            return;
        }

        $templates = EmailTemplateRegistry::all();

        $vars       = compact( 'request', 'templates' );
        smliser_render_template( 'admin.options.email.templates', $vars );
    }

    /**
     * Individual email template view.
     *
     * Provides the preview render and enable/disable/reset controls
     * for a single template type.
     */
    private static function email_template_editor( Request $request ): void {
        $key   = $request->get( 'template' );
        $entry = EmailTemplateRegistry::entry( $key );

        if ( ! $entry ) {
            wp_safe_redirect( remove_query_arg( 'template' ) );
            exit;
        }

        $preview      = EmailTemplateRegistry::preview( $key );
        $preview_html = $preview?->render();
        $current_url  = smliser_get_current_url()->remove_query_param( 'message' );

        $vars   = compact( 'entry', 'current_url', 'preview', 'preview_html' );

        smliser_render_template( 'admin.options.email.editor', $vars );
    }

    /**
     * Settings page for an individual email provider.
     */
    private static function email_provider_settings( Request $request ): void {
        $provider_key = $request->get( 'provider' );
        $registry   = smliser_emailProvidersRegistry();
        $provider   = $registry->get_provider( $provider_key );

        $provider_name  = $provider?->get_name() ?? '';
        $provider_id    = $provider?->get_id() ?? '';
        $schema         = $provider?->get_settings_schema() ?? [];
        $is_default     = EmailProvidersRegistry::get_default_provider_id() === $provider_id;

        // Pre-populate each field with persisted value.
        $saved_settings = [];
        foreach ( $schema as $key => $_ ) {
            $saved_settings[ $key ] = EmailProvidersRegistry::get_option( $provider_id, $key );
        }

        $vars   = compact( 'request', 'saved_settings', 'provider', 'provider_id', 'provider_key',
        'provider_name', 'schema', 'is_default' );
        smliser_render_template( 'admin.options.email.form', $vars );
    }

    /**
     * Cache options page dispatcher.
     *
     * Routes to:
     *   - section=stats  → live cache statistics dashboard
     *   - adapter=<id>   → individual adapter settings
     *   - (default)      → adapter selection grid
     */
    private static function cache_options( Request $request ): void {
        if ( $request->get( 'section' ) === 'stats' ) {
            self::cache_stats( $request );
            return;
        }

        if ( smliser_has_query_param( 'adapter' ) ) {
            self::cache_adapter_settings( $request );
            return;
        }

        $cache_registry     = CacheAdapterRegistry::instance();
        $providers          = $cache_registry->all( true, true );
        $default_provider   = CacheAdapterRegistry::get_default_adapter_id();

        $vars   = compact( 'request', 'cache_registry', 'providers', 'default_provider' );
        smliser_render_template( 'admin.options.cache.index', $vars );
    }

    /**
     * Live cache statistics dashboard.
     *
     * Pulls stats from the active adapter via the smliser_cache() singleton
     * so no second adapter instance is created (which would open a second
     * connection on network-backed adapters such as Memcached or Redis).
     */
    private static function cache_stats( Request $request ): void {
        $cache        = smliser_cache();
        $stats        = $cache->get_stats();
        $adapter_id   = $cache->get_id();
        $adapter_name = $cache->get_name();
        $is_supported = $cache->is_supported();

        $vars   = compact( 'request', 'cache', 'stats', 'adapter_id', 'adapter_name', 'is_supported' );
        smliser_render_template( 'admin.options.cache.stats', $vars );
    }

    /**
     * Settings page for an individual cache adapter.
     */
    private static function cache_adapter_settings( Request $request ): void {
        $adapter_key  = $request->get( 'adapter' );
        $collection   = CacheAdapterRegistry::instance();
        $adapter      = $collection->get_adapter( $adapter_key );

        $adapter_name   = $adapter?->get_name() ?? '';
        $adapter_id     = $adapter?->get_id() ?? '';
        $schema         = $adapter?->get_settings_schema() ?? [];
        $is_default     = CacheAdapterRegistry::get_default_adapter_id() === $adapter_id;

        // Pre-populate each field with persisted value.
        $saved_settings = [];
        foreach ( $schema as $key => $_ ) {
            $saved_settings[ $key ] = CacheAdapterRegistry::get_option( $adapter_id, $key );
        }

        $vars   = compact( 'request', 'adapter', 'adapter_name', 'adapter_key', 'schema', 
        'is_default', 'adapter_id', 'saved_settings' );
        smliser_render_template( 'admin.options.cache.form', $vars );
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
        $settings = smliser_settings();

        return [
            [
                'label' => 'Default Email Provider',
                'help'  => 'The email provider used for all outgoing system emails. Configure individual providers using the cards below.',
                'input' => [
                    'type'  => 'select',
                    'name'  => EmailProvidersRegistry::DEFAULT_PROVIDER_KEY,
                    'value' => EmailProvidersRegistry::get_default_provider_id() ?? '',
                    'class' => 'smliser-form-label-row smliser-auto-select2',
                    'options' => array_map(
                        static fn( EmailProviderInterface $p ) => $p->get_name(),
                        smliser_emailProvidersRegistry()->all( true, true )
                    ),
                ],
            ],

            [
                'label' => 'Default From Name',
                'help'  => 'Sender name used in outgoing emails when the active provider does not have a From Name configured.',
                'input' => [
                    'type'  => 'text',
                    'name'  => EmailProvidersRegistry::DEFAULT_SENDER_NAME_KEY,
                    'value' => smliser_emailProvidersRegistry()->get_default_sender_name(),
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
                    'name'  => EmailProvidersRegistry::DEFAULT_SENDER_EMAIL_KEY,
                    'value' => smliser_emailProvidersRegistry()->get_default_sender_email(),
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
     * @return array<int, array<string, mixed>>
     */
    public static function system_settings_fields(): array {
        $settings = smliser_settings();

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
            [
                'label' => 'Privacy Policy URL',
                'help'  => 'Full URL to your privacy policy page.',
                'input' => [
                    'type'  => 'text',
                    'name'  => 'privacy_policy_url',
                    'value' => $settings->get( 'privacy_policy_url', '', true ),
                    'attr'  => [
                        'autocomplete' => 'off',
                        'spellcheck'   => 'off',
                    ],
                ],
            ],
        ];
    }

    /**
     * Get routing settings form fields.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function get_routing_fields(): array {
        return [
            [
                'label' => 'Client Dashboard URL Prefix',
                'help'  => 'The URL segment that appears before the client dashboard page. For example: https://example.com/dashboard/',
                'input' => [
                    'type'  => 'text',
                    'name'  => 'client_dashboard_url_prefix',
                    'value' => \smliser_get_client_dashboard_url_prefix(),
                    'attr'  => [
                        'autocomplete' => 'off',
                        'spellcheck'   => 'off',
                    ],
                ],
            ],
            [
                'label' => 'Repository URL Prefix',
                'help'  => 'The URL segment that appears before repository pages. For example: https://example.com/repository/',
                'input' => [
                    'type'  => 'text',
                    'name'  => 'repository_url_prefix',
                    'value' => \smliser_get_repository_url_prefix(),
                    'attr'  => [
                        'autocomplete' => 'off',
                        'spellcheck'   => 'off',
                    ],
                ],
            ],

            [
                'label' => 'Downloads URL Prefix',
                'help'  => 'The URL segment that appears before download pages. For example: https://example.com/downloads/',
                'input' => [
                    'type'  => 'text',
                    'name'  => 'download_url_prefix',
                    'value' => \smliser_get_download_url_prefix(),
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
    public static function get_menu_args( Request $request ): array {
        $tab         = $request->get( 'tab' );
        $section     = $request->get( 'section' );
        $current_url = smliser_get_current_url()->remove_query_param( 'message', 'tab', 'section', 'provider', 'adapter', 'template' );

        $title = match ( true ) {
            'routes'       === $tab                              => 'Page Routing',
            'monetization' === $tab                              => 'Monetization Providers',
            'email'        === $tab && 'templates' === $section  => 'Email Templates',
            'email'        === $tab                              => 'Email Providers',
            'cache'        === $tab && 'stats'     === $section  => 'Cache Statistics',
            'cache'        === $tab                              => 'Cache Adapters',
            default                                              => 'General Settings',
        };

        $email_url = $current_url->add_query_param( 'tab', 'email' );
        $cache_url = $current_url->add_query_param( 'tab', 'cache' );

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
                    'title'  => 'Monetization Provider Settings',
                    'label'  => 'Monetizations',
                    'url'    => $current_url->add_query_param( 'tab', 'monetization' ),
                    'icon'   => 'ti ti-cash-register',
                    'active' => 'monetization' === $tab,
                ],
                [
                    'title'  => 'Email Provider Settings',
                    'label'  => 'Email Providers',
                    'url'    => $email_url,
                    'icon'   => 'ti ti-mail',
                    'active' => 'email' === $tab && 'templates' !== $section,
                ],
                [
                    'title'  => 'Email Templates',
                    'label'  => 'Email Templates',
                    'url'    => $email_url->add_query_param( 'section', 'templates' ),
                    'icon'   => 'ti ti-template',
                    'active' => 'email' === $tab && 'templates' === $section,
                ],
                [
                    'title'  => 'Cache Adapters',
                    'label'  => 'Cache',
                    'url'    => $cache_url,
                    'icon'   => 'ti ti-database-search',
                    'active' => 'cache' === $tab && 'stats' !== $section,
                ],
                [
                    'title'  => 'Cache Statistics',
                    'label'  => 'Cache Stats',
                    'url'    => $cache_url->add_query_param( 'section', 'stats' ),
                    'icon'   => 'ti ti-chart-bar',
                    'active' => 'cache' === $tab && 'stats' === $section,
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