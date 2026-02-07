<?php
/**
 * WordPress environment bootstrap file.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Environment
 */

namespace SmartLicenseServer\Environment\WordPress;

use SmartLicenseServer\Admin\Menu;
use SmartLicenseServer\Config;
use SmartLicenseServer\EnvironmentProviderInterface;
use SmartLicenseServer\FileSystem\FileSystem;
use SmartLicenseServer\Monetization\DownloadToken;
use SmartLicenseServer\Monetization\ProviderCollection;
use SmartLicenseServer\RESTAPI\Versions\V1;

/**
 * WordPress Environment setup class
 */
class SetUp extends Config implements EnvironmentProviderInterface {
    /**
     * The singleton instance of the environment.
     * 
     * @var self $instance
     */
    private static ?self $instance;

    /**
     * Class constructor
     */
    private function __construct() {
        $repo_path      = WP_CONTENT_DIR;
        $absolute_path  = ABSPATH;
        $uploads_dir    = wp_upload_dir()['basedir'];
        $db_prefix      = $GLOBALS['wpdb']?->prefix;
        parent::instance( compact( 'absolute_path', 'db_prefix', 'repo_path', 'uploads_dir' ) );

        new RESTAPI( new V1 );

        add_action( 'admin_menu', [Menu::class, 'register_menus'] );
        add_action( 'admin_menu', [Menu::class, 'modify_sw_menu'], 999 );

        add_action( 'admin_notices', [ __CLASS__, 'check_filesystem_errors'] );
        add_action( 'init', [$this, 'auto_register_monetization_providers'] );
        
        add_action( 'smliser_auth_page_header', 'smliser_load_auth_header' );
        add_action( 'smliser_auth_page_footer', 'smliser_load_auth_footer' );

        
        add_action( 'admin_init', [Router::class, 'init_request'] );
        add_action( 'template_redirect', array( Router::class, 'init_request' ) );
        add_filter( 'template_include', array( Router::class, 'load_auth_template' ) );
        
        add_action( 'smliser_clean', [DownloadToken::class, 'clean_expired_tokens'] );
    }

    /**
     * Initialize the environment.
     */
    public static function init() {
        if ( ! isset( self::$instance ) ) {
            self::$instance = new self();
        }

        return self::$instance;
    }


    /**
     * Auto register monetization providers
     */
    public function auto_register_monetization_providers() {
        ProviderCollection::auto_load();
    }

    /**
     * Check filesystem permissions and print admin notice if not writable.
     * 
     * @return void
     */
    public static function check_filesystem_errors() {
        $fs             = FileSystem::instance()->get_fs();

        if ( ! \property_exists( $fs, 'error' ) ) {
            return;
        }

        /** @var \WP_Error $wp_error */
        $wp_error       = $fs->errors;

        if ( $wp_error->has_errors() ) {
            $error_messages = $wp_error->get_error_messages();
            $messages_html = '';
            foreach ( $error_messages as $message ) {
                $messages_html .= '<code>' . esc_html( $message ) . '</code><br />';
            }

            wp_admin_notice( 
                sprintf(
                    __( '%s Filesystem Error: <br/> %s Please ensure the WordPress filesystem is properly configured and writable.', 'smliser' ),
                    SMLISER_APP_NAME,
                    $messages_html
                ),
                'error'
            );

        }
    }
}