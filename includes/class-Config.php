<?php
/**
 * License Server environment configuration file
 * 
 * @author Callistus
 * @package SmartLicenseServer
 * @since 1.0.0
 */

namespace SmartLicenseServer;

use RuntimeException;
use SmartLicenseServer\Security\Permission\Capability;
use SmartLicenseServer\Security\Permission\Role;

defined( 'SMLISER_ABSPATH' ) || exit;

class Config {

    /** 
     * REST API Route namespace.
     * 
     * @var string
     */
    protected $rest_api_namespace  = 'smliser/v1';

    /** 
     * Repository REST API route.
     * 
     * @var string
     */
    private $repository_route = '/repository/';


    /** 
     * Instance of current class.
     * 
     * @var self
     */
    private static $instance = null;

    /**
     * Class constructor.
     * 
     * @param array $config Array of configuration options
     */
    private function __construct( array $config ) {
        $parsed_config  = self::parse_config( $config );

        if ( ! $parsed_config ) {
            throw new RuntimeException( \sprintf( '%s Configuration is invalid', SMLISER_APP_NAME ) );
        }

        /**
         * Licenses database table name.
         *
         * Dynamically generated using the configured database prefix.
         *
         * @var string
         */
        define( 'SMLISER_LICENSE_TABLE', $parsed_config['db_prefix'] . 'smliser_licenses' );

        /**
         * License metadata database table name.
         *
         * @var string
         */
        define( 'SMLISER_LICENSE_META_TABLE', $parsed_config['db_prefix'] . 'smliser_license_meta' );

        /**
         * Plugins database table name.
         *
         * @var string
         */
        define( 'SMLISER_PLUGINS_TABLE', $parsed_config['db_prefix'] . 'smliser_plugins' );

        /**
         * Plugin metadata database table name.
         *
         * @var string
         */
        define( 'SMLISER_PLUGINS_META_TABLE', $parsed_config['db_prefix'] . 'smliser_plugin_meta' );

        /**
         * Themes database table name.
         *
         * @var string
         */
        define( 'SMLISER_THEMES_TABLE', $parsed_config['db_prefix'] . 'smliser_themes' );

        /**
         * Theme metadata database table name.
         *
         * @var string
         */
        define( 'SMLISER_THEMES_META_TABLE', $parsed_config['db_prefix'] . 'smliser_theme_meta' );

        /**
         * Software database table name.
         *
         * @var string
         */
        define( 'SMLISER_SOFTWARE_TABLE', $parsed_config['db_prefix'] . 'smliser_software' );

        /**
         * Software metadata database table name.
         *
         * @var string
         */
        define( 'SMLISER_SOFTWARE_META_TABLE', $parsed_config['db_prefix'] . 'smliser_software_meta' );

        /**
         * API credentials database table name.
         * 
         * @deprecated 0.2.0
         * @var string
         */
        define( 'SMLISER_API_CRED_TABLE', $parsed_config['db_prefix'] . 'smliser_api_creds' );

        /**
         * Item download token database table name.
         *
         * @var string
         */
        define( 'SMLISER_DOWNLOAD_TOKEN_TABLE', $parsed_config['db_prefix'] . 'smliser_item_download_token' );

        /**
         * Application download token database table name.
         *
         * @var string
         */
        define( 'SMLISER_APP_DOWNLOAD_TOKEN_TABLE', $parsed_config['db_prefix'] . 'smliser_app_download_tokens' );

        /**
         * Monetization records database table name.
         *
         * @var string
         */
        define( 'SMLISER_MONETIZATION_TABLE', $parsed_config['db_prefix'] . 'smliser_monetization' );

        /**
         * Pricing tiers database table name.
         *
         * @var string
         */
        define( 'SMLISER_PRICING_TIER_TABLE', $parsed_config['db_prefix'] . 'smliser_pricing_tiers' );

        /**
         * Bulk messages database table name.
         *
         * @var string
         */
        define( 'SMLISER_BULK_MESSAGES_TABLE', $parsed_config['db_prefix'] . 'smliser_bulk_messages' );

        /**
         * Bulk message to application mapping database table name.
         *
         * @var string
         */
        define( 'SMLISER_BULK_MESSAGES_APPS_TABLE', $parsed_config['db_prefix'] . 'smliser_bulk_messages_apps' );

        /**
         * Plugin options database table name.
         *
         * @var string
         */
        define( 'SMLISER_OPTIONS_TABLE', $parsed_config['db_prefix'] . 'smliser_options' );

        /**
         * Analytics event logs database table name.
         *
         * @var string
         */
        define( 'SMLISER_ANALYTICS_LOGS_TABLE', $parsed_config['db_prefix'] . 'smliser_analytics_log' );

        /**
         * Daily analytics aggregation database table name.
         *
         * @var string
         */
        define( 'SMLISER_ANALYTICS_DAILY_TABLE', $parsed_config['db_prefix'] . 'smliser_analytics_daily' );

        /**
         * Resource owners database table name.
         *
         * @var string
         */
        define( 'SMLISER_OWNERS_TABLE', $parsed_config['db_prefix'] . 'smliser_resource_owners' );

        /**
         * Internal users database table name.
         *
         * @var string
         */
        define( 'SMLISER_USERS_TABLE', $parsed_config['db_prefix'] . 'smliser_users' );

        /**
         * Service accounts database table name.
         *
         * @var string
         */
        define( 'SMLISER_SERVICE_ACCOUNTS_TABLE', $parsed_config['db_prefix'] . 'smliser_service_accounts' );

        /**
         * Roles database table name.
         *
         * @var string
         */
        define( 'SMLISER_ROLES_TABLE', $parsed_config['db_prefix'] . 'smliser_roles' );

        /**
         * Roles database table name.
         *
         * @var string
         */
        define( 'SMLISER_ROLE_CAPABILITIES_TABLE', $parsed_config['db_prefix'] . 'smliser_role_caps' );

        /**
         * Roles to principals database table name.
         *
         * @var string
         */
        define( 'SMLISER_ROLE_ASSIGNMENT_TABLE', $parsed_config['db_prefix'] . 'smliser_principal_roles' );

        /**
         * Organizations database table name.
         *
         * @var string
         */
        define( 'SMLISER_ORGANIZATIONS_TABLE', $parsed_config['db_prefix'] . 'smliser_organizations' );

        /**
         * Organization members database table name.
         *
         * @var string
         */
        define( 'SMLISER_ORGANIZATION_MEMBERS_TABLE', $parsed_config['db_prefix'] . 'smliser_organization_members' );

        /**
         * Absolute path to the Smart License Server repository root directory.
         *
         * This is the base directory where all hosted application files are stored.
         *
         * @var string
         */
        define( 'SMLISER_NEW_REPO_DIR', $parsed_config['repo_path'] . '/smliser-repo' );

        /**
         * Alias for the Smart License Server repository root directory.
         *
         * @var string
         */
        define( 'SMLISER_REPO_DIR', SMLISER_NEW_REPO_DIR );

        /**
         * Absolute path to the plugin repository directory.
         *
         * @var string
         */
        define( 'SMLISER_PLUGINS_REPO_DIR', SMLISER_REPO_DIR . '/plugins' );

        /**
         * Absolute path to the theme repository directory.
         *
         * @var string
         */
        define( 'SMLISER_THEMES_REPO_DIR', SMLISER_REPO_DIR . '/themes' );

        /**
         * Absolute path to the software repository directory.
         *
         * @var string
         */
        define( 'SMLISER_SOFTWARE_REPO_DIR', SMLISER_REPO_DIR . '/software' );

        /**
         * Absolute path to the uploads directory.
         * 
         * @var string
         */
        define( 'SMLISER_UPLOADS_DIR', $parsed_config['uploads_dir'] . '/smliser-uploads' );
        
        register_activation_hook( SMLISER_FILE, array( Installer::class, 'install' ) );

        add_filter( 'redirect_canonical', array( $this, 'disable_redirect_on_downloads' ), 10, 2 );
        add_filter( 'query_vars', array( $this, 'query_vars') );
        add_filter( 'cron_schedules', array( $this, 'register_cron' ) );

        add_action( 'plugins_loaded', array( $this, 'include' ) );
        add_action( 'init', array( $this, 'init_hooks' ) );
        add_action( 'admin_notices', array( __CLASS__, 'print_notice' ) );

        add_action( 'wp_enqueue_scripts', array( $this, 'load_styles' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'load_scripts' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'load_scripts' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'load_styles' ) );
    }

    /**
     * Instanciate the current class.
     * @return self
     */
    public static function instance( array $config = [] ) {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self( $config );
        }

        return self::$instance;
    }

    /**
     * Parse environment configuration file to ensure that required variables are set.
     * 
     * @param array $env_config The configuration options from the environment adapter.
     */
    private static function parse_config( $env_config ) {
        $default_config = array(
            'db_prefix'     => '',
            'absolute_path' => '',
            'repo_path'     => '',
            'uploads_dir'   => ''

        );

        $parsed_config  = array_intersect_key( array_merge( $default_config, $env_config ), $default_config );

        foreach ( $parsed_config as $value ) {
            if ( empty( $value ) ) {
                return false;
            }

            return $parsed_config;
        }
    }

    /**
     * Get the namespace
     */
    public static function namespace() {
        return self::instance()->rest_api_namespace;
    }

    /**
     * Include files
     */
    public function include() {
        require_once SMLISER_PATH . 'vendor/autoload.php';

        require_once SMLISER_PATH . 'includes/Utils/conditional-functions.php';
        require_once SMLISER_PATH . 'includes/Utils/functions.php';
        require_once SMLISER_PATH . 'includes/Utils/sanitization-functions.php';
        require_once SMLISER_PATH . 'includes/Utils/formating-functions.php';
              
    }

    /**
     * Load Scripts
     */
    public function load_scripts( $s ) {
        wp_enqueue_script( 'smliser-script', SMLISER_URL . 'assets/js/main-script.js', array( 'jquery' ), SMLISER_VER, true );
        wp_enqueue_script( 'smliser-nanojson', SMLISER_URL . 'assets/js/nanojson.min.js', array( 'jquery' ), SMLISER_VER, true );
        wp_register_script( 'smliser-apps-uploader', SMLISER_URL . 'assets/js/apps-uploader.js', array( 'jquery', 'smliser-nanojson' ), SMLISER_VER, true );
        wp_register_script( 'select2', SMLISER_URL . 'assets/js/Select2/select2.min.js', array( 'jquery' ), SMLISER_VER, true );
        wp_register_script( 'smliser-tinymce', SMLISER_URL . 'assets/js/tinymce/tinymce.min.js', array( 'jquery' ), SMLISER_VER, true );
        wp_register_script( 'smliser-admin-repository', SMLISER_URL . 'assets/js/admin-repository.js', array( 'jquery' ), SMLISER_VER, true );
        wp_register_script( 'smliser-role-builder', SMLISER_URL . 'assets/js/role-builder.js', array(), SMLISER_VER, true );
        wp_register_script( 'smliser-chart', SMLISER_URL . 'assets/js/Chartjs/chart.min.js', array(), SMLISER_VER, true );
        wp_register_script( 'smliser-app-modal', SMLISER_URL . 'assets/js/app-modal.js', array(), SMLISER_VER, true );
        
        if ( is_admin() ) {
            wp_enqueue_script( 'smliser-app-modal' );
        }
        if ( is_admin() && 'toplevel_page_smliser-admin' === $s ) {
            wp_enqueue_script( 'smliser-chart' );
        }

        if ( 'smart-license-server_page_smliser-bulk-message' === $s 
            || 'smart-license-server_page_licenses' === $s 
            || 'smart-license-server_page_smliser-access-control' === $s 
            
            ) {
            wp_enqueue_script( 'select2' );
        }

        if ( 'smart-license-server_page_repository' === $s ) {
            wp_enqueue_script( 'smliser-admin-repository' );
        }

        if ( 'smart-license-server_page_smliser-access-control' === $s ) {
            wp_enqueue_script( 'smliser-role-builder' );
        }

        $vars   = array(
            'smliser_ajax_url'  => admin_url( 'admin-ajax.php' ),
            'nonce'             => wp_create_nonce( 'smliser_nonce' ),
            'admin_url'         => admin_url(),
            'wp_spinner_gif'    => admin_url('images/spinner.gif'),
            'wp_spinner_gif_2x' => admin_url('images/spinner-2x.gif'),
            'app_search_api'    => rest_url( self::namespace() . $this->repository_route ),
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
    public function load_styles( $s ) {
        wp_enqueue_style( 'smliser-styles', SMLISER_URL . 'assets/css/smliser-styles.css', array(), SMLISER_VER, 'all' );
        wp_enqueue_style( 'smliser-form-styles', SMLISER_URL . 'assets/css/smliser-forms.css', array(), SMLISER_VER, 'all' );
        wp_register_style( 'select2', SMLISER_URL . 'assets/css/select2.min.css', array(), SMLISER_VER, 'all' );
        wp_register_style( 'smliser-nanojson', SMLISER_URL . 'assets/css/nanojson.min.css', array(), SMLISER_VER, 'all' );
        wp_register_style( 'smliser-tabler-icons', SMLISER_URL . 'assets/icons/tabler-icons.min.css', array(), SMLISER_VER, 'all' );
        wp_register_style( 'smliser-role-builder', SMLISER_URL . 'assets/css/role-builder.css', array(), SMLISER_VER, 'all' );
        wp_register_style( 'smliser-app-modal', SMLISER_URL . 'assets/css/app-modal.css', array(), SMLISER_VER, 'all' );
    
        if ( is_admin() ) {
            wp_enqueue_style( 'smliser-app-modal' );
        }
        if ( 'smart-license-server_page_smliser-bulk-message' === $s 
            || 'smart-license-server_page_licenses' === $s
            || 'smart-license-server_page_smliser-access-control' === $s 
            
        ) {
            wp_enqueue_style( 'select2' );
        }

        if ( 'smart-license-server_page_repository' === $s ) {
            wp_enqueue_style( 'smliser-nanojson' );
        }

        if ( \is_admin() ) {
            wp_enqueue_style( 'smliser-tabler-icons' );
        }

        if ( 'smart-license-server_page_smliser-access-control' === $s ) {
            wp_enqueue_style( 'smliser-role-builder' );
        }
    
    }

    /**
     * Init hooks
     */
    public function init_hooks() {
        $this->run_automation();
        $repo_base_url = \smliser_settings_adapter()->get( 'smliser_repo_base_perma', 'repository' );
    
        add_rewrite_rule(
            '^' . $repo_base_url . '$',
            'index.php?pagename=smliser-repository',
            'top'
        );
    
        /**
         * Repository app type page matches siteurl/repository/{app_type}/ where app type can be (themes, plugins, software)
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

        /**
         * Smliser uploads dir serving url matchs siteurl/smliser-uploads/{path_to_file}
         */
        add_rewrite_rule(
            '^smliser-uploads/(.+)$',
            'index.php?pagename=smliser-uploads&smliser_upload_path=$matches[1]',
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
        $vars[] = 'smliser_upload_path';
        
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
     * Print admin notices
     */
    public static function print_notice() {
        $repo_version = \smliser_settings_adapter()->get( 'smliser_repo_version', 0 );
        if ( SMLISER_VER === $repo_version ) {
            return;
        }
        ?>
        <div class="notice notice-info">
            <p><?php \printf( '%s requires an update, click <a id="smliser-update-btn" style="cursor: pointer;">HERE</a> to update now.', SMLISER_APP_NAME ) ?> </p>
            <p id="smliser-click-notice" style="display: none">Update started in the backgroud <span class="dashicons dashicons-yes-alt" style="color: blue"></span></p>
        </div>
        <?php
    }
}

