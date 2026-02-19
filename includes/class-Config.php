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

defined( 'SMLISER_ABSPATH' ) || exit;

class Config {

    /** 
     * REST API Route namespace.
     * 
     * @var string
     */
    protected $rest_api_namespace  = 'smliser/v1';

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

        /**
         * Temporary file prefix
         */
        define( 'SMLISER_UPLOAD_TMP_PREFIX', 'smliser_mp_' );

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
    public function namespace() {
        return static::instance()->rest_api_namespace;
    }

    /**
     * Include files
     */
    public function bootstrap_files() {
        require_once SMLISER_PATH . 'vendor/autoload.php';

        require_once SMLISER_PATH . 'includes/Utils/conditional-functions.php';
        require_once SMLISER_PATH . 'includes/Utils/functions.php';
        require_once SMLISER_PATH . 'includes/Utils/sanitization-functions.php';
        require_once SMLISER_PATH . 'includes/Utils/formating-functions.php';
              
    }
}

