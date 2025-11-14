<?php
/**
 * file name class-smliser-config.php
 * License Server environment configuration file
 * 
 * @author Callistus
 * @package SmartLicenseServer
 * @since 1.0.0
 */

use SmartLicenseServer\Core\URL;

defined( 'ABSPATH' ) || exit;

class SmartLicense_config {

    /** 
     * REST API Route namespace.
     * 
     * @var string
     */
    private $namespace  = 'smliser/v1';

    /** 
     * Plugin info REST API route.
     * 
     * @var string
     */
    private $plugin_info   = '/plugin-info/';

    /** 
     * License activation REST API route.
     * 
     * @var string
     */
    private $activation_route = '/license-activation/(?P<app_type>[a-zA-Z0-9_-]+)/(?P<app_slug>[a-zA-Z0-9_-]+)';

    /** 
     * License deactivation REST API route
     * 
     * @var string
     */
    private $deactivation_route = '/license-deactivation/';

    /**
     * License uninstallation route.
     * 
     * @var string
     */
    private $license_uninstallation_route = '/license-uninstallation/';

    /**
     * License validity route.
     * 
     * @var string
     */
    private $license_validity_route = '/license-validity-test/';

    /** 
     * Repository REST API route.
     * 
     * @var string
     */
    private $repository_route = '/repository/';

    /** 
     * Repository REST API route for specific plugin.
     * 
     * @var string
     */
    private $repository_plugin_route = '/repository/(?P<slug>[^/]+)/(?P<scope>read|write|read-write)';
    
    /** 
     * Client authentication REST API route, essentially for token regeneration.
     * 
     * @var string
     */
    private $app_reauth = '/client-auth/';

    /**
     * Download token regeneration REST API route
     * 
     * @var string
     */
    private $download_reauth = '/download-token-reauthentication/';

    /**
     * RESE endoint for bulk bulk messages fetching.
     * 
     * @var string $bulk_messages_route
     */
    private $bulk_messages_route = '/bulk-messages/';

    /** 
     * Instance of current class.
     * 
     * @var SmartLicense_config
     */
    private static $instance = null;

    /**
     * Class constructor.
     */
    public function __construct() {
        global $wpdb;

        define( 'SMLISER_REPOSITORY_ROUTE', $this->repository_route );
        define( 'SMLISER_LICENSE_TABLE', $wpdb->prefix.'smliser_licenses' );
        define( 'SMLISER_PLUGIN_META_TABLE', $wpdb->prefix . 'smliser_plugin_meta' );
        define( 'SMLISER_THEME_META_TABLE', $wpdb->prefix . 'smliser_theme_meta' );
        define( 'SMLISER_LICENSE_META_TABLE', $wpdb->prefix . 'smliser_license_meta' );
        define( 'SMLISER_PLUGIN_ITEM_TABLE', $wpdb->prefix . 'smliser_plugins' );
        define( 'SMLISER_THEME_ITEM_TABLE', $wpdb->prefix . 'smliser_themes' );
        define( 'SMLISER_APPS_ITEM_TABLE', $wpdb->prefix . 'smliser_applications' );
        define( 'SMLISER_APPS_META_TABLE', $wpdb->prefix . 'smliser_applications_meta' );
        define( 'SMLISER_API_ACCESS_LOG_TABLE', $wpdb->prefix . 'smliser_api_access_logs' );
        define( 'SMLISER_API_CRED_TABLE', $wpdb->prefix . 'smliser_api_creds' );
        define( 'SMLISER_DOWNLOAD_TOKEN_TABLE', $wpdb->prefix . 'smliser_item_download_token' );
        define( 'SMLISER_APP_DOWNLOAD_TOKEN_TABLE', $wpdb->prefix . 'smliser_app_download_tokens' );
        define( 'SMLISER_MONETIZATION_TABLE', $wpdb->prefix . 'smliser_monetization' );
        define( 'SMLISER_PRICING_TIER_TABLE', $wpdb->prefix . 'smliser_pricing_tiers' );
        define( 'SMLISER_BULK_MESSAGES_TABLE', $wpdb->prefix . 'smliser_bulk_messages' );
        define( 'SMLISER_BULK_MESSAGES_APPS_TABLE', $wpdb->prefix . 'smliser_bulk_messages_apps' );

        /**
         * Absolute path to the root Smart License Server repository directory.
         *
         * This is the base directory where all hosted application files are stored.
         */
        define( 'SMLISER_NEW_REPO_DIR', WP_CONTENT_DIR . '/smliser-repo' );

        /**
         * Alias for the Smart License Server repository directory.
         *
         * Used as the root path for all application repositories.
         */
        define( 'SMLISER_REPO_DIR', SMLISER_NEW_REPO_DIR );

        /**
         * Absolute path to the plugin repository directory.
         *
         * Stores all plugin packages and related assets hosted in the repository.
         */
        define( 'SMLISER_PLUGINS_REPO_DIR', SMLISER_REPO_DIR . '/plugins' );

        /**
         * Absolute path to the theme repository directory.
         *
         * Stores all theme packages and related assets hosted in the repository.
         */
        define( 'SMLISER_THEMES_REPO_DIR', SMLISER_REPO_DIR . '/themes' );

        /**
         * Absolute path to the software repository directory.
         *
         * Stores all software packages and related assets hosted in the repository.
         */
        define( 'SMLISER_SOFTWARE_REPO_DIR', SMLISER_REPO_DIR . '/software' );
        
        register_activation_hook( SMLISER_FILE, array( 'Smliser_install', 'install' ) );

        // Register REST endpoints.
        add_action( 'rest_api_init', array( $this, 'rest_load' ) );
        add_filter( 'rest_pre_dispatch', array( $this, 'enforce_https_for_rest_api' ), 10, 3 );
        add_filter( 'rest_post_dispatch', array( $this, 'filter_rest_response' ), 10, 3 );
        add_filter( 'rest_request_before_callbacks', array( __CLASS__, 'rest_request_before_callbacks' ), -1, 3 );

        add_filter( 'redirect_canonical', array( $this, 'disable_redirect_on_downloads' ), 10, 2 );
        add_filter( 'query_vars', array( $this, 'query_vars') );
        add_filter( 'cron_schedules', array( $this, 'register_cron' ) );

        add_action( 'plugins_loaded', array( $this, 'include' ) );
        add_action( 'init', array( $this, 'init_hooks' ) );
        add_action( 'admin_notices', array( __CLASS__, 'print_notice' ) );
        add_action( 'wp_ajax_smliser_plugin_action', array( 'Smliser_Plugin', 'action_handler' ) );
        add_action( 'wp_ajax_smliser_upgrade', array( 'Smliser_Install', 'ajax_update' ) );
        add_action( 'admin_post_nopriv_smliser_oauth_login', array( 'Smliser_API_Cred', 'oauth_login_form_handler' ) );
        add_action( 'smliser_stats', array( 'Smliser_Stats', 'action_handler' ), 10, 4 );
        add_action( 'wp_ajax_smliser_key_generate', array( 'Smliser_API_Cred', 'admin_create_cred_form' ) );
        add_action( 'wp_ajax_smliser_revoke_key', array( 'Smliser_API_Cred', 'revoke' ) );
        add_action( 'wp_ajax_smliser_token_gen_form', array( 'Smliser_Plugin_Download_Token', 'ajax_token_form' ) );
        add_action( 'wp_ajax_smliser_generate_item_token', array( 'Smliser_Plugin_Download_Token', 'get_new_token' ) );
        add_action( 'smliser_auth_page_header', 'smliser_load_auth_header' );
        add_action( 'smliser_auth_page_footer', 'smliser_load_auth_footer' );
        add_action( 'smliser_clean', array( 'Smliser_Plugin_Download_Token', 'clean_expired_tokens' ) );

        add_action( 'wp_enqueue_scripts', array( $this, 'load_styles' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'load_scripts' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'load_scripts' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'load_styles' ) );

        add_action( 'admin_notices', function() { self::check_filesystem_errors(); } );
    }

    /** Load or Register our Rest route */
    public function rest_load() {
        
        /** 
         * Register the license activation route. 
         */
        register_rest_route( $this->namespace, $this->activation_route, 
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            =>  array( 'Smliser_License_Rest_API', 'license_activation_response' ),
                'permission_callback' => array( 'Smliser_License_Rest_API', 'license_activation_permission_callback'),
                'args'  => array(
                    // 'item_id'   => array(
                    //     'required'          => true,
                    //     'type'              => 'integer',
                    //     'description'       => 'The ID of the item associated with the license.',
                    //     'sanitize_callback' => array( __CLASS__, 'sanitize' ),
                    //     'validate_callback' => array( __CLASS__, 'is_int' ),
                    // ),

                    'service_id'    => array(
                        'required'          => true,
                        'type'              => 'string',
                        'description'       => 'The service id associated with the license key',
                        'sanitize_callback' => array( __CLASS__, 'sanitize' ),
                        'validate_callback' => array( __CLASS__, 'not_empty' ),
                    ),

                    'license_key'   => array(
                        'required'          => true,
                        'type'              => 'string',
                        'description'       => 'The license key to verify',
                        'sanitize_callback' => array( __CLASS__, 'sanitize' ),
                        'validate_callback' => array( __CLASS__, 'not_empty' ),
                    ),

                    'domain'  => array(
                        'required'          => true,
                        'type'              => 'string',
                        'description'       => 'The ID of the item associated with the license.',
                        'sanitize_callback' => array( __CLASS__, 'sanitize_url' ),
                        'validate_callback' => array( __CLASS__, 'is_url' ),
                    )
                ),
            )
        );

        /**
         * Register the license deactivation route
         */
        register_rest_route( $this->namespace, $this->deactivation_route, 
            array(
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => array( 'Smliser_License_Rest_API', 'license_deactivation_response' ),
                'permission_callback' => array( 'Smliser_License_Rest_API', 'license_deactivation_permission' ),
                'args'  => array(
                    'license_key'   => array(
                        'required'          => true,
                        'type'              => 'string',
                        'description'       => 'The license key to deactivate.',
                        'sanitize_callback' => array( __CLASS__, 'sanitize' ),
                        'validate_callback' => array( __CLASS__, 'not_empty' ),
                    ),

                    'service_id'    => array(
                        'required'          => true,
                        'type'              => 'string',
                        'description'       => 'The service ID associated with the license.',
                        'sanitize_callback' => array( __CLASS__, 'sanitize' ),
                        'validate_callback' => array( __CLASS__, 'not_empty' ),
                    ),
                    'domain'  => array(
                        'required'          => true,
                        'type'              => 'string',
                        'description'       => 'The URL of the website where the license is currently activated.',
                        'sanitize_callback' => array( __CLASS__, 'sanitize_url' ),
                        'validate_callback' => array( __CLASS__, 'is_url' ),
                    )
                ),
            )
        );

        /**
         * Register license unstallation route.
         */
        register_rest_route( $this->namespace, $this->license_uninstallation_route, 
            array(
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => array( 'Smliser_License_Rest_API', 'license_uninstallation_response' ),
                'permission_callback' => array( 'Smliser_License_Rest_API', 'license_uninstallation_permission' ),
                'args'  => array(
                    'license_key'   => array(
                        'required'          => true,
                        'type'              => 'string',
                        'description'       => 'The license key to uninstall.',
                        'sanitize_callback' => array( __CLASS__, 'sanitize' ),
                        'validate_callback' => array( __CLASS__, 'not_empty' ),
                    ),

                    'service_id'    => array(
                        'required'          => true,
                        'type'              => 'string',
                        'description'       => 'The service ID associated with the license.',
                        'sanitize_callback' => array( __CLASS__, 'sanitize' ),
                        'validate_callback' => array( __CLASS__, 'not_empty' ),
                    ),
                    'domain'  => array(
                        'required'          => true,
                        'type'              => 'string',
                        'description'       => 'The URL of the website where the license is currently activated.',
                        'sanitize_callback' => array( __CLASS__, 'sanitize_url' ),
                        'validate_callback' => array( __CLASS__, 'is_url' ),
                    )
                ),
            )
        );

        /**
         * Register license validity route.
         */
        register_rest_route( $this->namespace, $this->license_validity_route, 
            array(
                'methods'             => 'POST',
                'callback'            => array( 'Smliser_License_Rest_API', 'license_validity_test' ),
                'permission_callback' => array( 'Smliser_License_Rest_API', 'license_validity_test_permission' ),
                'args'  => array(
                    'license_key'   => array(
                        'required'          => true,
                        'type'              => 'string',
                        'description'       => 'The license key to validate.',
                        'sanitize_callback' => array( __CLASS__, 'sanitize' ),
                        'validate_callback' => array( __CLASS__, 'not_empty' ),
                    ),

                    'service_id'    => array(
                        'required'          => true,
                        'type'              => 'string',
                        'description'       => 'The service ID associated with the license.',
                        'sanitize_callback' => array( __CLASS__, 'sanitize' ),
                        'validate_callback' => array( __CLASS__, 'not_empty' ),
                    ),
                    'domain'  => array(
                        'required'          => true,
                        'type'              => 'string',
                        'description'       => 'The URL of the website where the license is currently activated.',
                        'sanitize_callback' => array( __CLASS__, 'sanitize_url' ),
                        'validate_callback' => array( __CLASS__, 'is_url' ),
                    ),
                    'item_id'       => array(
                        'required'          => true,
                        'type'              => 'integer',
                        'description'       => 'The ID of the software this license is associated with.',
                        'sanitize_callback' => array( __CLASS__, 'sanitize' ),
                        'validate_callback' => array( __CLASS__, 'is_int' ),
                    )
                ),
            )
        );

        /** 
         * Register plugin info route.
         * 
         */
        register_rest_route( $this->namespace, $this->plugin_info, array(
            'methods'             => WP_REST_Server::READABLE,
            'permission_callback' => array( 'Smliser_Plugin_REST_API', 'plugin_info_permission_callback' ),
            'callback'            => array( 'Smliser_Plugin_REST_API', 'plugin_info' ),
            'args'  => array(
                'item_id'   => array(
                    'required'          => false,
                    'type'              => 'integer',
                    'description'       => 'The plugin ID',
                    'sanitize_callback' => 'absint',
                ),
                'slug'      => array(
                    'required'          => false,
                    'type'              => 'string',
                    'description'       => 'The plugin slug eg. plugin-slug/plugin-slug',
                    'sanitize_callback' => array( __CLASS__, 'sanitize' )
                ),
            ),
            

        ) );

        /** 
         * Register REST API route for querying entire repository
         */
        register_rest_route( $this->namespace, $this->repository_route, 
            array(
                'methods'               => WP_REST_Server::READABLE,
                'callback'              => array( 'Smliser_Repository_Rest_API', 'repository_response' ),
                'permission_callback'   => array( 'Smliser_Repository_Rest_API', 'repository_access_permission' ),
                'args'  => array(
                    'search'   => array(
                        'required'          => false,
                        'type'              => 'string',
                        'description'       => 'The search term',
                        'sanitize_callback' => array( __CLASS__, 'sanitize' ),
                        'validate_callback' => '__return_true'
                    )
                ),
            )
        );

        /** Register Oauth client authentication route */
        register_rest_route( $this->namespace, $this->app_reauth, array(
            'methods'               => 'GET',
            'callback'              => array( 'Smliser_REST_Authentication', 'client_authentication_response' ),
            'permission_callback'   => array( 'Smliser_REST_Authentication', 'auth_permission' ),
        ) );

        /** 
         * Register download token reauthenticatiion route.
        */
        register_rest_route( $this->namespace, $this->download_reauth, 
            array(
                'methods'               => 'POST',
                'callback'              => array( 'Smliser_License_Rest_API', 'item_download_reauth' ),
                'permission_callback'   => array( 'Smliser_License_Rest_API', 'item_download_reauth_permission' ),
                'args'  => array(
                    'domain'    => array(
                        'required'  => true,
                        'type'      => 'string',
                        'description'   => 'The domain where the plugin is installed.',
                        'sanitize_callback' => array( __CLASS__, 'sanitize_url' ),
                        'validate_callback' => array( __CLASS__, 'is_url' )
                    ),
                    'license_key'           => array(
                        'required'          => true,
                        'type'              => 'string',
                        'description'       => 'The license key to reauthenticate.',
                        'sanitize_callback' => array( __CLASS__, 'sanitize' ),
                        'validate_callback' => array( __CLASS__, 'not_empty' )
                    ),
                    'item_id'   => array(
                        'required'          => true,
                        'type'              => 'integer',
                        'description'       => 'The ID of the item associated with the license.',
                        'sanitize_callback' => array( __CLASS__, 'sanitize' ),
                        'validate_callback' => array( __CLASS__, 'is_int' ),
                    ),
                    'download_token'    => array(
                        'required'         => true,
                        'type'              => 'string',
                        'description'       => 'The base64 encoded download token issued during license activation.',
                        'sanitize_callback' => array( __CLASS__, 'sanitize' ),
                        'validate_callback' => array( __CLASS__, 'not_empty' ),
                    ),
                    'service_id'    => array(
                        'required'          => true,
                        'type'              => 'string',
                        'description'       => 'The service ID associated with the license.',
                        'sanitize_callback' => array( __CLASS__, 'sanitize' ),
                        'validate_callback' => array( __CLASS__, 'not_empty' )
                    )
                )
            )
        );

        /**
         * Register mock inbox route for testing SmartWoo_Inbox_Manager
         *
         * This route simulates inbox message fetch response and can be
         * used for local development or testing plugin communications.
         *
         * Example: GET /wp-json/smliser/v1/mock-inbox
         *
         * @since 1.0.0
         */
        register_rest_route( $this->namespace, '/mock-inbox', array(
            'methods'               => WP_REST_Server::READABLE,
            'callback'              => function( WP_REST_Request $request ) {
                $messages = array(
                    array(
                        'id'         => 'msg_20251013_001',
                        'subject'    => 'Welcome to Smart Woo!',
                        'body'       => '<p>We’re thrilled to have you onboard 🎉. Smart Woo simplifies your invoicing, payments, and client management. </p>
                                        <p>Start by exploring our <a href="https://smartwoo.com/docs" target="_blank">documentation</a> and check your <strong>Service Dashboard</strong> for quick actions.</p>
                                        <p>Need help? Contact <a href="mailto:support@smartwoo.com">support@smartwoo.com</a>.</p>',
                        'created_at' => '2025-10-13 09:15:00',
                        'updated_at' => '2025-10-13 09:15:00',
                        'read'       => false,
                    ),
                    array(
                        'id'         => 'msg_20251013_002',
                        'subject'    => 'New Feature: Automated Invoicing',
                        'body'       => '<p>Introducing <strong>Smart Auto Billing</strong> — automatically generate and send invoices for recurring services.</p>
                                        <p>You can configure this under <em>Smart Woo → Settings → Automation</em>. The feature supports both manual and scheduled triggers.</p>
                                        <p><a href="https://smartwoo.com/blog/auto-billing" target="_blank">Learn more →</a></p>',
                        'created_at' => '2025-10-13 09:45:00',
                        'updated_at' => '2025-10-13 09:45:00',
                        'read'       => false,
                    ),
                    array(
                        'id'         => 'msg_20251013_003',
                        'subject'    => 'Smart Woo Pro 1.5 Released 🚀',
                        'body'       => '<p>We’re excited to announce <strong>Smart Woo Pro v1.5</strong> with new features like:</p>
                                        <ul>
                                            <li>Improved checkout analytics dashboard</li>
                                            <li>Custom hooks for subscription renewals</li>
                                            <li>Performance enhancements</li>
                                        </ul>
                                        <p>Update your plugin from the dashboard or visit <a href="https://smartwoo.com/changelog">our changelog</a> for details.</p>',
                        'created_at' => '2025-10-12 14:30:00',
                        'updated_at' => '2025-10-12 14:30:00',
                        'read'       => false,
                    ),
                    array(
                        'id'         => 'msg_20251013_004',
                        'subject'    => 'Tips: Speed Up Your Billing Workflow',
                        'body'       => '<p>Did you know you can create <strong>service templates</strong> to reuse invoice structures? This saves time and reduces errors.</p>
                                        <p>Navigate to <em>Smart Woo → Templates</em> and create your first one.</p>',
                        'created_at' => '2025-10-11 08:00:00',
                        'updated_at' => '2025-10-11 08:00:00',
                        'read'       => false,
                    ),
                    array(
                        'id'         => 'msg_20251013_005',
                        'subject'    => 'Long Text',
                        'body'       => '<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Curabitur eu mauris nec velit fermentum facilisis et eget mauris. Quisque eu nulla nec tortor vehicula finibus. Fusce quis laoreet lacus. Vivamus sagittis nisi vel velit tincidunt, nec tempus tortor hendrerit. Quisque varius pretium orci, in dignissim velit iaculis quis. Vestibulum vitae nisl quis turpis ultricies sagittis pellentesque a neque. Etiam tempus tellus vel velit varius porttitor. Donec id eros aliquet, malesuada justo nec, luctus ex. Pellentesque consequat nisl at mi suscipit feugiat.

                            Nulla eu fringilla lacus, eu porta lacus. Donec interdum ultricies metus id fringilla. Nam volutpat faucibus odio a blandit. Ut pretium, erat vitae porttitor dictum, purus odio scelerisque lacus, in venenatis felis eros nec nunc. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Curabitur facilisis dignissim ligula eget auctor. Phasellus volutpat maximus nisi sed fringilla. Integer ut posuere orci. Mauris ex metus, dignissim sit amet risus non, gravida suscipit felis. Donec dapibus varius bibendum. Cras a augue dapibus, ornare felis ac, interdum velit. Ut vitae tortor tempus, venenatis mauris quis, efficitur dui. Pellentesque in posuere arcu. Duis ut pulvinar erat.

                            Morbi et porta diam, at semper nisl. Nulla dapibus viverra pellentesque. Pellentesque habitant morbi tristique senectus et netus et malesuada fames ac turpis egestas. Nullam eros nisi, euismod vitae lectus porta, dignissim rutrum lorem. Nulla massa libero, egestas ut dapibus posuere, blandit in libero. Curabitur euismod quam sit amet rutrum dapibus. Sed at eros diam. Pellentesque habitant morbi tristique senectus et netus et malesuada fames ac turpis egestas. Fusce tempus arcu vel dolor pellentesque, eget bibendum lorem tristique.

                            Proin a purus leo. Curabitur dictum faucibus purus, in elementum eros pellentesque vel. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia curae; Mauris in justo enim. Pellentesque sed turpis euismod, rhoncus augue pharetra, bibendum elit. Ut lectus dolor, porttitor nec elit euismod, dignissim ultrices ante. Donec fringilla lacinia mauris vitae hendrerit. Integer in ligula eget erat pretium hendrerit vel id dolor. In lobortis purus mauris, sit amet bibendum ex rhoncus a. Curabitur porttitor vulputate lectus in scelerisque. Nullam dolor turpis, venenatis sed sem at, tempor dignissim felis. Donec dictum et diam eu imperdiet. Duis efficitur, orci ut fringilla tincidunt, ligula ex sagittis nibh, at aliquam ante ante a ligula. Phasellus mollis augue quis pellentesque maximus.

                            Nullam diam diam, lobortis ut tincidunt id, consequat quis eros. Suspendisse tincidunt vestibulum magna non lacinia. Curabitur semper enim sem, condimentum tempor orci placerat at. Proin faucibus sapien eu lorem sollicitudin, et ornare tellus cursus. Phasellus eleifend tortor non mi condimentum posuere. Proin pellentesque placerat nulla ut blandit. Morbi vitae ligula ultrices, posuere leo vel, malesuada lectus. Nulla ornare ipsum ac molestie pretium. Duis nec libero ut felis sagittis dignissim. Aliquam erat volutpat. Ut sit amet bibendum sapien. Aliquam erat volutpat. Mauris enim sem, ultricies nec ex nec, volutpat molestie orci. Sed posuere ac lorem in dictum. Donec ornare nisi nulla, eu posuere enim placerat et. Curabitur rutrum hendrerit lobortis.

                            Sed luctus fringilla dui quis euismod. Sed nec nisl vitae neque porttitor eleifend. Vestibulum tempor ligula nisl, eget suscipit nunc elementum a. Integer euismod congue metus et placerat. Etiam eleifend interdum leo quis interdum. Nam iaculis, turpis vel rhoncus consectetur, tortor mi viverra nisl, quis dictum lacus sapien sit amet quam. Sed sit amet ipsum vulputate, ultricies nunc in, mattis quam.

                            Nullam ligula orci, malesuada et fermentum imperdiet, laoreet nec dolor. Donec ornare gravida sodales. Etiam convallis molestie nulla in posuere. Aliquam vitae scelerisque augue. Donec facilisis mauris laoreet varius pulvinar. Mauris pretium orci vel enim finibus auctor. Cras efficitur non enim quis dignissim. Donec finibus tortor id est finibus fermentum. Pellentesque vulputate vestibulum lacus id elementum. Nulla ullamcorper odio at augue finibus pellentesque. Etiam a interdum turpis, venenatis volutpat sapien. Pellentesque magna eros, convallis at ex ac, blandit semper odio. Cras eu mauris eu est aliquet rutrum. Donec malesuada non enim at vehicula.</p>
                                        <p>Please ensure your plugin is updated to <strong>v1.5.1</strong>.</p>',
                        'created_at' => '2025-10-10 13:00:00',
                        'updated_at' => '2025-10-10 13:00:00',
                        'read'       => false,
                    ),
                    array(
                        'id'         => 'msg_20251013_006',
                        'subject'    => 'Upcoming Webinar: Advanced Smart Woo Techniques 🎥',
                        'body'       => '<p>Join our next live session where we’ll cover:</p>
                                        <ul>
                                            <li>Creating automated renewals</li>
                                            <li>Integrating external APIs</li>
                                            <li>Optimizing performance for large sites</li>
                                        </ul>
                                        <p>Register now on <a href="https://smartwoo.com/webinars">our webinar page</a>.</p>',
                        'created_at' => '2025-10-09 15:45:00',
                        'updated_at' => '2025-10-09 15:45:00',
                        'read'       => false,
                    ),
                    array(
                        'id'         => 'msg_20251013_007',
                        'subject'    => 'Smart Woo API Enhancements for Developers 🧠',
                        'body'       => '<p>Developers can now access extended API endpoints for license verification and customer lookup.</p>
                                        <p>Check the <a href="https://smartwoo.com/docs/api" target="_blank">API Reference</a> for the full list of methods.</p>',
                        'created_at' => '2025-10-08 10:25:00',
                        'updated_at' => '2025-10-08 10:25:00',
                        'read'       => false,
                    ),
                    array(
                        'id'         => 'msg_20251013_008',
                        'subject'    => 'We’d Love Your Feedback 💬',
                        'body'       => '<p>Your feedback helps us improve Smart Woo. Please take a moment to complete our quick survey.</p>
                                        <p><a href="https://smartwoo.com/feedback" target="_blank">Take the survey →</a></p>',
                        'created_at' => '2025-10-07 17:20:00',
                        'updated_at' => '2025-10-07 17:20:00',
                        'read'       => false,
                    ),
                    array(
                        'id'         => 'msg_20251013_009',
                        'subject'    => 'Maintenance Scheduled 🕐',
                        'body'       => '<p>We’ll be performing scheduled maintenance on <strong>October 20, 2025</strong>.</p>
                                        <p>Service interruptions may occur between 1:00 AM and 2:00 AM UTC.</p>',
                        'created_at' => '2025-10-07 08:10:00',
                        'updated_at' => '2025-10-07 08:10:00',
                        'read'       => false,
                    ),
                    array(
                        'id'         => 'msg_20251013_010',
                        'subject'    => 'Thank You for Being Part of Smart Woo 💙',
                        'body'       => '<p>We’ve grown thanks to your support and feedback. Stay tuned for exciting updates and community features!</p>
                                        <p>Visit our <a href="https://community.smartwoo.com" target="_blank">Community Portal</a> to connect with other users.</p>',
                        'created_at' => '2025-10-06 11:55:00',
                        'updated_at' => '2025-10-06 11:55:00',
                        'read'       => false,
                    ),
                );

                return rest_ensure_response( $messages );
            },
            'permission_callback'   => '__return_true',
            'args'                  => array(
                'since' => array(
                    'required'          => false,
                    'type'              => 'string',
                    'description'       => 'Optional timestamp to filter messages created after a specific date.',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ));

        /**
         * Register Bulk Messages route.
         *
         * Example: GET /wp-json/smliser/v1/bulk-messages?app_types[]=plugin&app_slugs[]=smart-woo-pro
         */
        register_rest_route( $this->namespace, $this->bulk_messages_route, array(
            'methods'               => WP_REST_Server::READABLE,
            'callback'              => array( \SmartLicenseServer\REST_API\Bulk_Messages_API::class, 'dispatch_response' ),
            'permission_callback'   => array( \SmartLicenseServer\REST_API\Bulk_Messages_API::class, 'permission_callback' ),
            'args'                  => array(
                'page' => array(
                    'required'          => false,
                    'type'              => 'integer',
                    'default'           => 1,
                    'description'       => 'Page number for pagination.',
                    'sanitize_callback' => array( __CLASS__, 'sanitize' ),
                ),
                'limit' => array(
                    'required'          => false,
                    'type'              => 'integer',
                    'default'           => 10,
                    'description'       => 'Number of messages per page.',
                    'sanitize_callback' => array( __CLASS__, 'sanitize' ),
                ),
                'app_slugs' => array(
                    'required'          => false,
                    'type'              => 'array',
                    'description'       => 'An array of app slugs to filter by.',
                    'sanitize_callback' => array( __CLASS__, 'sanitize' ),
                ),
                'app_types' => array(
                    'required'          => false,
                    'type'              => 'array',
                    'description'       => 'An array of app types to filter by (e.g., plugin, theme).',
                    'sanitize_callback' => array( __CLASS__, 'sanitize' ),
                ),
            ),
        ));

    }

    /**
     * Ensures HTTPS/TLS for REST API endpoints within the plugin's namespace.
     *
     * Checks if the current REST API request belongs to the plugin's namespace
     * and enforces HTTPS/TLS requirements if the environment is production.
     *
     * @return WP_Error|null WP_Error object if HTTPS/TLS requirement is not met, null otherwise.
     */
    public function enforce_https_for_rest_api( $result, $server, $request ) {
        // Check if current request belongs to the plugin's namespace.
        if ( ! str_contains( $request->get_route(), $this->namespace ) ) {
            return;
        }

        // Check if environment is production and request is not over HTTPS.
        if ( 'production' === wp_get_environment_type() && ! is_ssl() ) {
            // Create WP_Error object to indicate insecure SSL.
            $error = new WP_Error( 'connection_not_secure', 'HTTPS/TLS is required for secure communication.', array( 'status' => 400, ) );
            
            // Return the WP_Error object.
            return $error;
        }
    }

    /**
     * Filter the REST API response.
     *
     * @param WP_REST_Response $response The REST API response object.
     * @param WP_REST_Server   $server   The REST server object.
     * @param WP_REST_Request  $request  The REST request object.
     * @return WP_REST_Response Modified REST API response object.
     */
    public function filter_rest_response( WP_REST_Response $response, WP_REST_Server $server, WP_REST_Request $request ) {

        if ( false !== strpos( $request->get_route(), $this->namespace ) ) {

            $response->header( 'X-Plugin-Name', 'Smart License Server' );
            $response->header( 'X-API', 'Smart License Server API' );
            $response->header( 'X-Plugin-Version', SMLISER_VER );
            $response->header( 'X-API-Version', 'v1' );

            $data = $response->get_data();

            if ( is_array( $data ) ) {
                $data = array( 'success' => ! $response->is_error() ) + $data;
                $response->set_data( $data );
            }
        }

        return $response;
    }

    /**
     * Preempt REST API request callbacks.
     * 
     * @param WP_REST_Response|WP_HTTP_Response|WP_Error|mixed $response Result to send to the client.
     *                                                                   Usually a WP_REST_Response or WP_Error.
     * @param array                                            $handler  Route handler used for the request.
     * @param WP_REST_Request                                  $request  Request used to generate the response.
     */
    public static function rest_request_before_callbacks( $response, $handler, $request ) {
        
        $route     = ltrim( $request->get_route(), '/' );
        $namespace = trim( self::namespace(), '/' );

        // Match if route starts with namespace
        if ( ! preg_match( '#^' . preg_quote( $namespace, '#' ) . '(/|$)#', $route ) ) {
            return $response;
        }

        if ( is_smliser_error( $response ) ) {
            remove_filter( 'rest_post_dispatch', 'rest_send_allow_header' ); // Prevents calling the permission callback again.
        }

        return $response;
    }



    /**
     * Instanciate the current class.
     * @return self
     */
    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Get the namespace
     */
    public static function namespace() {
        return self::instance()->namespace;
    }

    /**
     * Include files
     */
    public function include() {
        require_once SMLISER_PATH . 'includes/database/DatabaseAdapterInterface.php';
        require_once SMLISER_PATH . 'includes/database/class-Database.php';
        require_once SMLISER_PATH . 'includes/database/class-WPAdapter.php';

        require_once SMLISER_PATH . 'includes/utils/conditional-functions.php';
        require_once SMLISER_PATH . 'includes/utils/functions.php';
        require_once SMLISER_PATH . 'includes/utils/sanitization-functions.php';
        require_once SMLISER_PATH . 'includes/utils/formating-functions.php';
        require_once SMLISER_PATH . 'includes/utils/class-callismart-encryption.php';
        require_once SMLISER_PATH . 'includes/utils/class-callismart-markdown-parser.php';
        
        require_once SMLISER_PATH . 'includes/exceptions/exception.php';
        require_once SMLISER_PATH . 'includes/exceptions/RequestException.php';
        require_once SMLISER_PATH . 'includes/exceptions/class-FileRequestException.php';
        require_once SMLISER_PATH . 'includes/core/class-request.php';
        require_once SMLISER_PATH . 'includes/core/class-response.php';
        require_once SMLISER_PATH . 'includes/core/class-URL.php';

        require_once SMLISER_PATH . 'includes/filesystem/class-filesystem.php';
        require_once SMLISER_PATH . 'includes/filesystem/class-filesystem-helper.php';
        require_once SMLISER_PATH . 'includes/filesystem/class-repository.php';
        require_once SMLISER_PATH . 'includes/filesystem/class-plugin-repository.php';
        require_once SMLISER_PATH . 'includes/filesystem/downloads-api/class-FileRequest.php';
        require_once SMLISER_PATH . 'includes/filesystem/downloads-api/class-FileResponse.php';
        require_once SMLISER_PATH . 'includes/filesystem/downloads-api/class-FileRequestController.php';
    
        require_once SMLISER_PATH . 'includes/hosted-apps/hosted-apps-interface.php';
        require_once SMLISER_PATH . 'includes/hosted-apps/class-software-collection.php';
        require_once SMLISER_PATH . 'includes/hosted-apps/class-smliser-plugin.php';
        require_once SMLISER_PATH . 'includes/hosted-apps/class-smliser-theme.php';
        require_once SMLISER_PATH . 'includes/hosted-apps/class-smliser-software.php';

        require_once SMLISER_PATH . 'includes/class-WPAdapter.php';

        require_once SMLISER_PATH . 'includes/class-smlicense.php';
        require_once SMLISER_PATH . 'includes/class-smliser-stats.php';
        require_once SMLISER_PATH . 'includes/class-smliser-api-cred.php';
        require_once SMLISER_PATH . 'includes/class-smliser-plugin-download-token.php';
        require_once SMLISER_PATH . 'includes/class-bulk-messages.php';
        require_once SMLISER_PATH . 'includes/smliser-rest-api/class-rest-auth.php';
        require_once SMLISER_PATH . 'includes/smliser-rest-api/class-smliser-license-rest-api.php';
        require_once SMLISER_PATH . 'includes/smliser-rest-api/class-smliser-plugin-rest-api.php';
        require_once SMLISER_PATH . 'includes/smliser-rest-api/class-smliser-repository-rest-api.php';
        require_once SMLISER_PATH . 'includes/smliser-rest-api/bulk-messages.php';

        require_once SMLISER_PATH . 'includes/monetization/provider-interface.php';
        require_once SMLISER_PATH . 'includes/monetization/class-license.php';
        require_once SMLISER_PATH . 'includes/monetization/class-monetization.php';
        require_once SMLISER_PATH . 'includes/monetization/class-DownloadToken.php';
        require_once SMLISER_PATH . 'includes/monetization/class-pricing-tier.php';
        require_once SMLISER_PATH . 'includes/monetization/provider-collection.php';
        require_once SMLISER_PATH . 'includes/monetization/class-woocomerce-provider.php';
        require_once SMLISER_PATH . 'includes/monetization/class-controller.php';

        require_once SMLISER_PATH . 'includes/admin/class-menu.php';
        require_once SMLISER_PATH . 'includes/admin/class-dashboard-page.php';
        require_once SMLISER_PATH . 'includes/admin/class-bulk-messages-page.php';
        require_once SMLISER_PATH . 'includes/admin/class-repository-page.php';
        require_once SMLISER_PATH . 'includes/admin/class-license-page.php';
        require_once SMLISER_PATH . 'includes/admin/class-options-page.php';
        
        do_action( 'smliser_loaded' );
        
    }

    /**
     * Load Scripts
     */
    public function load_scripts( $s ) {
        wp_enqueue_script( 'smliser-script', SMLISER_URL . 'assets/js/main-script.js', array( 'jquery' ), SMLISER_VER, true );
        wp_register_script( 'smliser-apps-uploader', SMLISER_URL . 'assets/js/apps-uploader.js', array( 'jquery' ), SMLISER_VER, true );
        wp_register_script( 'select2', SMLISER_URL . 'assets/js/select2.min.js', array( 'jquery' ), SMLISER_VER, true );
        wp_register_script( 'smliser-tinymce', SMLISER_URL . 'assets/js/tinymce/tinymce.min.js', array( 'jquery' ), SMLISER_VER, true );

        if ( is_admin() ) {
            wp_enqueue_script( 'smliser-chart', SMLISER_URL . 'assets/js/chart.js', array(), SMLISER_VER, true );
        }

        if ( 'smart-license-server_page_smliser-bulk-message' === $s || 'smart-license-server_page_licenses' === $s ) {
            wp_enqueue_script( 'select2' );
        }

        // Script localizer.
        wp_localize_script(
            'smliser-script',
            'smliser_var',
            array(
                'smliser_ajax_url'  => admin_url( 'admin-ajax.php' ),
                'nonce'             => wp_create_nonce( 'smliser_nonce' ),
                'admin_url'         => admin_url(),
                'wp_spinner_gif'    => admin_url('images/spinner.gif'),
                'wp_spinner_gif_2x' => admin_url('images/spinner-2x.gif'),
                'app_search_api'    => rest_url( $this->namespace . $this->repository_route )
            )
        );

    }

    /**
     * Load styles
     */
    public function load_styles( $s ) {
        wp_enqueue_style( 'smliser-styles', SMLISER_URL . 'assets/css/smliser-styles.css', array(), SMLISER_VER, 'all' );
        wp_enqueue_style( 'smliser-form-styles', SMLISER_URL . 'assets/css/smliser-forms.css', array(), SMLISER_VER, 'all' );
        wp_register_style( 'select2', SMLISER_URL . 'assets/css/select2.min.css', array(), SMLISER_VER, 'all' );
    
        
        if ( 'smart-license-server_page_smliser-bulk-message' === $s || 'smart-license-server_page_licenses' === $s ) {
            wp_enqueue_style( 'select2' );
        }
    
    }

    /**
     * Init hooks
     */
    public function init_hooks() {
        $this->run_automation();
        $repo_base_url = get_option( 'smliser_repo_base_perma', 'repository' );
    
        add_rewrite_rule(
            '^' . $repo_base_url . '$',
            'index.php?pagename=smliser-repository',
            'top'
        );
    
        /**
         * Repository app type page matches siteurl/repository/{app_type}/ where app type can be (themes, plugins, softwares)
         */
        add_rewrite_rule(
            '^' . $repo_base_url . '/([^/]+)$',
            'index.php?pagename=smliser-repository&smliser_app_type=$matches[1]',
            'top'
        );

        /**
         * Repository app type page matches siteurl/repository/{app_type}/{app_slug}/
         */
        add_rewrite_rule(
            '^' . $repo_base_url . '/([^/]+)/([^/]+)/?$',
            'index.php?pagename=smliser-repository&smliser_app_type=$matches[1]&smliser_app_slug=$matches[2]',
            'top'
        );


        /**
         * Asset serving url matchs siteurl/repository/{app_type}/{app_slug}/assets/{filename}
         */
        add_rewrite_rule(
            '^' . $repo_base_url . '/([^/]+)/([^/]+)/assets/(.+)$',
            'index.php?pagename=smliser-repository-assets&smliser_app_type=$matches[1]&smliser_app_slug=$matches[2]&smliser_asset_name=$matches[3]',
            'top'
        );

        /*
        |------------------------
        | Software download rules
        |------------------------
        */

        $download_slug = smliser_get_download_slug();

        /**
         * The base downloads page 
         */
        add_rewrite_rule(
            '^' . $download_slug . '/?$',
            'index.php?pagename=smliser-downloads',
            'top'
        );

        /**
         * Downloads category page
         */
        add_rewrite_rule(
            '^' . $download_slug . '/([^/]+)/?$',
            'index.php?pagename=smliser-downloads&smliser_app_type=$matches[1]',
            'top'
        );
        
        /** 
         * License document download rule (specific)
         */
        add_rewrite_rule(
            '^' . $download_slug . '/([^/]+)/([0-9]+)/?$',
            'index.php?pagename=smliser-downloads&smliser_app_type=$matches[1]&license_id=$matches[2]',
            'top'
        );

        /** 
         * File Download URI Rule
         */
        add_rewrite_rule(
            '^' . $download_slug . '/([^/]+)/((?![0-9]+$)[^/]+)(?:\.zip)?/?$',
            'index.php?pagename=smliser-downloads&smliser_app_type=$matches[1]&smliser_app_slug=$matches[2]',
            'top'
        );

        
        /**OAUTH authorization endpoint */
        add_rewrite_rule(
            '^smliser-auth/v1/authorize$',
            'index.php?smliser_auth=$matches[1]',
            'top'
        );
    }

    /**
     * Plugin Query Variables
     *
     * Adds custom query variables to WordPress recognized query variables.
     *
     * @param array $vars The existing array of query variables.
     * @return array Modified array of query variables.
     */
    public function query_vars( $vars ) {
        
        $vars[] = 'smliser_repository';
        $vars[] = 'smliser_repository_plugin_slug';
        $vars[] = 'license_id';
        $vars[] = 'smliser_app_type';
        $vars[] = 'smliser_auth';
        
        $vars[] = 'smliser_app_slug';
        $vars[] = 'smliser_asset_name';
        
        return $vars;
    }    

    /**
     * Register cron.
     */
    public function register_cron( $schedules ) {
        $schedules = array();
        /** Add a new cron schedule interval for every 3 minutes. */
        $schedules['smliser_three_minutely'] = array(
            'interval' => 3 * MINUTE_IN_SECONDS,
            'display'  => 'Smliser Three Minutely',
        );

        /** Add a new cron schedule interval for every 4 hours. */
        $schedules['smliser_4_hourly'] = array(
            'interval' => 4 * HOUR_IN_SECONDS,
            'display'  => 'Smliser Four Hourly',
        );
        return $schedules;
    }

    /**
     * Schedule event.
     */
    public function run_automation() {
        if ( ! wp_next_scheduled( 'smliser_validate_license' ) ) {
			wp_schedule_event( time(), 'smliser_three_minutely', 'smliser_validate_license' );
		}

        if ( ! wp_next_scheduled( 'smliser_clean' ) ) {
            wp_schedule_event( time(), 'smliser_4_hourly', 'smliser_clean' );
        }

    }
    
    /**
     * Disable unintended http 301 redirect code during file downloads, this ensures
     * we handle the responses to the download url the proper way instead of the default 301 returned initially
     * by WordPress.
     * 
     * @param string $redirect_url The redirected url.
     * @param string $requested_url The client requested url.
     * @return false|string False when accessing downloads page, perform redirect when not.
     * @since 1.0.0
     */
    public function disable_redirect_on_downloads( $redirect_url, $requested_url ) {
        $download_slug = site_url( smliser_get_download_slug() );
        if ( strpos( $requested_url, $download_slug ) !== false ) {
            return false;
        }

        return $redirect_url;
    }

    /**
     * Encapsulted sanitization function for REST param.
     * 
     * @param mixed $value The value to sanitize.
     */
    public static function sanitize( $value ) {
        if ( is_string( $value ) ) {
            $value = sanitize_text_field( unslash( $value ) );
        } elseif ( is_array( $value ) ) {
            $value = array_map( 'sanitize_text_field', unslash( $value ) );
        } elseif ( is_int( $value ) ) {
            $value = absint( $value );
        } elseif ( is_float( $value ) ) {
            $value = floatval( $value );
        }

        return $value;
    }

    /**
     * Sanitize a URL for REST API validation.
     * 
     * @param string $url The URL to sanitize.
     * @return string Sanitized URL.
     */
    public static function sanitize_url( $url ) {
        $url = esc_url_raw( $url );
        if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
            return new WP_Error( 'rest_invalid_param', __( 'Invalid URL format.', 'smliser' ), array( 'status' => 400 ) );
        }
        return $url;
    }

    /**
     * Check whether a value is empty for REST API validation.
     * @param string $value The value to check.
     * @return bool true if not empty, false otherwise.
     */
    public static function not_empty( $value ) {
        if ( empty( $value ) ) {
            return new WP_Error( 'rest_invalid_param', __( 'The value cannot be empty.', 'smliser' ), array( 'status' => 400 ) );
        }
        return true;
    }

    /**
     * Validate if the given URL is an HTTP or HTTPS URL.
     *
     * @param string $url The URL to validate.
     * @param WP_REST_Request $request The request object.
     * @param string $param The parameter name.
     * @return true|WP_Error
     */
    public static function is_url( $url, $request, $param ) {
        if ( empty( $url ) ) {
            return new WP_Error( 'rest_invalid_param', __( 'The domain parameter is required.', 'smliser' ), array( 'status' => 400 ) );
        }

        $url        = new URL( $url );

        if ( ! $url->has_scheme() ) {
            return new WP_Error( 'rest_invalid_param', __( 'Invalid URL format.', 'smliser' ), array( 'status' => 400 ) );
        }

        if ( ! $url->is_ssl() ) {
            return new WP_Error( 'rest_invalid_param', __( 'Only HTTPS URLs are allowed.', 'smliser' ), array( 'status' => 400 ) );
        }

        if ( ! $url->validate( true ) ) {
            return new WP_Error( 'rest_invalid_param', __( 'The URL does not resolve to a valid host.', 'smliser' ), array( 'status' => 400 ) );
        }

        return true;
    }

    /**
     * Validate if a given REST API parameter is an integer.
     * 
     * @param mixed $value The value to validate.
     * @param WP_REST_Request $request The request object.
     * @param string $key The parameter name.
     * @return true|WP_Error Returns true if the value is an integer, otherwise returns a WP_Error object.
     */
    public static function is_int( $value, $request, $key ) {
        if ( ! is_numeric( $value ) || intval( $value ) != $value ) {
            return new WP_Error( 'rest_invalid_param', __( 'The value must be an integer.', 'smliser' ), array( 'status' => 400 ) );
        }
        return true;
    }

    /**
     * Print admin notices
     */
    public static function print_notice() {
        $repo_version = get_option( 'smliser_repo_version', 0 );
        if ( SMLISER_VER === $repo_version ) {
            return;
        }
        ?>
        <div class="notice notice-info">
            <p>Smart License Server requires an update, click <a id="smliser-update-btn" style="cursor: pointer;">HERE</a> to update now.</p>
            <p id="smliser-click-notice" style="display: none">Update started in the backgroud <span class="dashicons dashicons-yes-alt" style="color: blue"></span></p>
        </div>
        <?php
    }

    /**
     * Check filesystem permissions and print admin notice if not writable.
     * 
     * @return void
     */
    private static function check_filesystem_errors() {
        $fs_instance    = SmartLicenseServer\FileSystem::instance();
        $wp_error       = $fs_instance->get_fs()->errors;

        if ( $wp_error->has_errors() ) {
            $error_messages = $wp_error->get_error_messages();
            $messages_html = '';
            foreach ( $error_messages as $message ) {
                $messages_html .= '<code>' . esc_html( $message ) . '</code><br />';
            }

            wp_admin_notice( 
                sprintf(
                    __( 'Smart License Server Filesystem Error: <br/> %s Please ensure the WordPress filesystem is properly configured and writable.', 'smliser' ),
                    $messages_html
                ),
                'error'
            );

        }
    }
}

