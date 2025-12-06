<?php
/**
 * License Server environment configuration file
 * 
 * @author Callistus
 * @package SmartLicenseServer
 * @since 1.0.0
 */

namespace SmartLicenseServer;


defined( 'ABSPATH' ) || exit;

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
     */
    public function __construct() {
        global $wpdb;

        define( 'SMLISER_LICENSE_TABLE', $wpdb->prefix.'smliser_licenses' );
        define( 'SMLISER_LICENSE_META_TABLE', $wpdb->prefix . 'smliser_license_meta' );

        /**
         * The plugin database table name
         */
        define( 'SMLISER_PLUGIN_ITEM_TABLE', $wpdb->prefix . 'smliser_plugins' );
        /**
         * The plugin database metadata table name.
         */
        define( 'SMLISER_PLUGIN_META_TABLE', $wpdb->prefix . 'smliser_plugin_meta' );

        /**
         * The theme database table name.
         */
        define( 'SMLISER_THEME_ITEM_TABLE', $wpdb->prefix . 'smliser_themes' );
        /**
         * The themes database metadata table name.
         */
        define( 'SMLISER_THEME_META_TABLE', $wpdb->prefix . 'smliser_theme_meta' );

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
        
        register_activation_hook( SMLISER_FILE, array( Installer::class, 'install' ) );

        // Register REST endpoints.
        add_filter( 'rest_pre_dispatch', array( $this, 'enforce_https_for_rest_api' ), 10, 3 );
        add_filter( 'rest_post_dispatch', array( $this, 'filter_rest_response' ), 10, 3 );
        add_filter( 'rest_request_before_callbacks', array( __CLASS__, 'rest_request_before_callbacks' ), -1, 3 );

        add_filter( 'redirect_canonical', array( $this, 'disable_redirect_on_downloads' ), 10, 2 );
        add_filter( 'query_vars', array( $this, 'query_vars') );
        add_filter( 'cron_schedules', array( $this, 'register_cron' ) );

        add_action( 'plugins_loaded', array( $this, 'include' ) );
        add_action( 'init', array( $this, 'init_hooks' ) );
        add_action( 'admin_notices', array( __CLASS__, 'print_notice' ) );
        add_action( 'wp_ajax_smliser_upgrade', array( Installer::class, 'ajax_update' ) );

        add_action( 'smliser_auth_page_header', 'smliser_load_auth_header' );
        add_action( 'smliser_auth_page_footer', 'smliser_load_auth_footer' );

        add_action( 'wp_enqueue_scripts', array( $this, 'load_styles' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'load_scripts' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'load_scripts' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'load_styles' ) );

        add_action( 'admin_notices', function() { self::check_filesystem_errors(); } );
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
        return self::instance()->rest_api_namespace;
    }

    /**
     * Include files
     */
    public function include() {
        require_once SMLISER_PATH . 'vendor/autoload.php';
        require_once SMLISER_PATH . 'includes/Database/DatabaseAdapterInterface.php';
        require_once SMLISER_PATH . 'includes/Database/class-Database.php';

        require_once SMLISER_PATH . 'includes/Utils/conditional-functions.php';
        require_once SMLISER_PATH . 'includes/Utils/functions.php';
        require_once SMLISER_PATH . 'includes/Utils/sanitization-functions.php';
        require_once SMLISER_PATH . 'includes/Utils/formating-functions.php';
        require_once SMLISER_PATH . 'includes/Utils/class-WPReadmeParser.php';
        require_once SMLISER_PATH . 'includes/Utils/class-MDParser.php';
        
        require_once SMLISER_PATH . 'includes/Exceptions/class-Exception.php';
        require_once SMLISER_PATH . 'includes/Exceptions/class-RequestException.php';
        require_once SMLISER_PATH . 'includes/Exceptions/class-FileRequestException.php';

        require_once SMLISER_PATH . 'includes/Core/class-Request.php';
        require_once SMLISER_PATH . 'includes/Core/class-Response.php';
        require_once SMLISER_PATH . 'includes/Core/class-URL.php';

        require_once SMLISER_PATH . 'includes/Filesystem/class-FileSystem.php';
        require_once SMLISER_PATH . 'includes/Filesystem/class-FileSystemHelper.php';
        require_once SMLISER_PATH . 'includes/Filesystem/class-Repository.php';
        require_once SMLISER_PATH . 'includes/Filesystem/class-PluginRepository.php';
        require_once SMLISER_PATH . 'includes/Filesystem/class-ThemeRepository.php';
        require_once SMLISER_PATH . 'includes/Filesystem/DownloadsApi/class-FileRequest.php';
        require_once SMLISER_PATH . 'includes/Filesystem/DownloadsApi/class-FileResponse.php';
        require_once SMLISER_PATH . 'includes/Filesystem/DownloadsApi/class-FileRequestController.php';
    
        require_once SMLISER_PATH . 'includes/hosted-apps/hosted-apps-interface.php';
        require_once SMLISER_PATH . 'includes/hosted-apps/class-AbstractHostedApp.php';
        require_once SMLISER_PATH . 'includes/hosted-apps/class-software-collection.php';
        require_once SMLISER_PATH . 'includes/hosted-apps/class-smliser-plugin.php';
        require_once SMLISER_PATH . 'includes/hosted-apps/class-smliser-theme.php';
        require_once SMLISER_PATH . 'includes/hosted-apps/class-smliser-software.php';

        require_once SMLISER_PATH . 'includes/class-WPAdapter.php';

        require_once SMLISER_PATH . 'includes/class-SmliserStats.php';
        require_once SMLISER_PATH . 'includes/class-SmliserAPICred.php';
        require_once SMLISER_PATH . 'includes/class-BulkMessages.php';

        require_once SMLISER_PATH . 'includes/RESTAPI/class-RESTAuthentication.php';
        require_once SMLISER_PATH . 'includes/RESTAPI/Versions/class-V1.php';
        require_once SMLISER_PATH . 'includes/RESTAPI/class-Licenses.php';
        require_once SMLISER_PATH . 'includes/RESTAPI/class-Plugins.php';
        require_once SMLISER_PATH . 'includes/RESTAPI/class-Themes.php';
        require_once SMLISER_PATH . 'includes/RESTAPI/class-AppCollection.php';
        require_once SMLISER_PATH . 'includes/RESTAPI/class-BulkMessages.php';

        require_once SMLISER_PATH . 'includes/Monetization/provider-interface.php';
        require_once SMLISER_PATH . 'includes/Monetization/class-License.php';
        require_once SMLISER_PATH . 'includes/Monetization/class-monetization.php';
        require_once SMLISER_PATH . 'includes/Monetization/class-DownloadToken.php';
        require_once SMLISER_PATH . 'includes/Monetization/class-pricing-tier.php';
        require_once SMLISER_PATH . 'includes/Monetization/provider-collection.php';
        require_once SMLISER_PATH . 'includes/Monetization/class-woocomerce-provider.php';
        require_once SMLISER_PATH . 'includes/Monetization/class-controller.php';

        require_once SMLISER_PATH . 'includes/Admin/class-Menu.php';
        require_once SMLISER_PATH . 'includes/Admin/class-DashboardPage.php';
        require_once SMLISER_PATH . 'includes/Admin/class-BulkMessagePage.php';
        require_once SMLISER_PATH . 'includes/Admin/class-RepositoryPage.php';
        require_once SMLISER_PATH . 'includes/Admin/class-LicensePage.php';
        require_once SMLISER_PATH . 'includes/Admin/class-OptionsPage.php';
        
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
                'app_search_api'    => rest_url( self::namespace() . $this->repository_route )
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
        $fs_instance    = FileSystem::instance();
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

