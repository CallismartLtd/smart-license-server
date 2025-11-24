<?php
/**
 * The admin repository page handler class.
 * 
 * @author Callistus Nwachukwu
 * @package Smliser\class
 */

namespace SmartLicenseServer\admin;
use \Smliser_Plugin, \Smliser_Stats, SmartLicenseServer\HostedApps\Hosted_Apps_Interface;

/**
 * The Admin repository page handler
 */
class Repository_Page {
    /**
     * Page router
     */
    public static function router() {
        $tab    = smliser_get_query_param( 'tab' );
        
        switch( $tab ) {
            case 'add-new':
                self::upload_page();
                break;
            case 'edit':
                self::edit_page();
                break;
            case 'view':
                self::view_page();
                break;
            case 'monetization':
                self::monetization_page();
                break;
            default:
            self::dashboard();
        }
    }

    /**
     * The repository dashboard page
     */
    private static function dashboard() {
        $args = array(
            'page'  => smliser_get_query_param( 'paged', 1 ),
            'limit' => smliser_get_query_param( 'limit', 25 )
        );

        $type   = smliser_get_query_param( 'type', null );
        if ( $type ) {
            $args['types']   = $type;
        }

        $result     = \Smliser_Software_Collection::get_apps( $args );
        $apps       = $result['items'];
        $pagination = $result['pagination'];
        
        $add_url    = smliser_admin_repo_tab( 'add-new' );
        include SMLISER_PATH . 'templates/admin/repository/dashboard.php';

    }

    /**
     * The upload page
     */
    private static function upload_page() {
        $type = smliser_get_query_param( 'type', null );

        $app_upload_dashboard   = SMLISER_PATH . 'templates/admin/repository/upload.php';
        $app_upload_template    = SMLISER_PATH . 'templates/admin/repository/uploader.php';
        $app_upload_page        = $type ? $app_upload_template : $app_upload_dashboard;

        $title      = $type ? ucfirst( $type ) : '';
        $type_title = $type ? ucfirst( $type ) : '';

        $essential_fields = self::prepare_essential_app_fields();
        
        include_once $app_upload_page;
    }

    /**
     * The edit page
     */
    private static function edit_page() {
        $id     = smliser_get_query_param( 'item_id' );
        $type   = smliser_get_query_param( 'type' );
        $class  = \Smliser_Software_Collection::get_app_class( $type );
        $method = "get_{$type}";

        if ( ! class_exists( $class ) || ! method_exists( $class, $method ) ) {
            smliser_abort_request( smliser_not_found_container( sprintf( 'This application type "%s" is not supportd! <a href="%s">Go Back</a>', esc_html( $type ), esc_url( smliser_repo_page() ) ) ), 'Invalid App Type' );
        }

        $app = $class::$method( $id );
        
        if ( ! $app ) {
            smliser_abort_request( smliser_not_found_container( sprintf( 'Invalid or deleted application! <a href="%s">Go Back</a>', esc_url( smliser_repo_page() ) ) ), 'Invalid App Type' );
        }
        $essential_fields   = self::prepare_essential_app_fields( $app );
        $type_title         = ucfirst( $type );
        $file               = sprintf( SMLISER_PATH . 'templates/admin/repository/edit-%s.php', $type );
        include_once $file;
    }

    /**
     * View items page
     */
    private static function view_page() {
        $id     = smliser_get_query_param( 'item_id' );

        $plugin = Smliser_Plugin::get_plugin( $id );

        if ( ! empty( $plugin ) ) {
            $delete_link    = wp_nonce_url( add_query_arg( array( 'action' => 'smliser_plugin_action', 'real_action' => 'delete', 'item_id' => $id ), admin_url( 'admin-post.php' ) ), -1, 'smliser_nonce' );
        }

        $stats = new Smliser_Stats();
        include_once SMLISER_PATH . 'templates/admin/repository/plugin-view.php';
    }

    /**
     * Manage plugin monetization page
     */
    private static function monetization_page() {

        include_once SMLISER_PATH . 'templates/admin/monetization.php';
        
    }

    /**
     * Prepare essential application fields
     * 
     * @param Hosted_Apps_Interface|null $app
     */
    private static function prepare_essential_app_fields( ?Hosted_Apps_Interface $app = null ) {
        return array(
            array(
                'label' => __( 'Name', 'smliser' ),
                'input' => array(
                    'type'  => 'text',
                    'name'  => 'app_name',
                    'value' => $app ? $app->get_name() : '',
                    'attr'  => array(
                        'autocomplete'  => 'off',
                        'spellcheck'    => 'off'
                    )
                )
            ),
            array(
                'label' => __( 'Version', 'smliser' ),
                'input' => array(
                    'type'  => 'text',
                    'name'  => 'app_version',
                    'value' => $app ? $app->get_version() : '',
                    'attr'  => array(
                        'autocomplete'  => 'off',
                        'spellcheck'    => 'off',
                        'readonly'      => true,
                        'title'         => 'Use app manifest file to edit version'
                    )
                )
            ),
            array(
                'label' => __( 'Author Name'),
                'input' => array(
                    'type'  => 'text',
                    'name'  => 'app_author',
                    'value' => $app ? $app->get_author() : '',
                    'attr'  => array(
                        'autocomplete'  => 'off',
                        'spellcheck'    => 'off'
                    )
                )
            ),
            array(
                'label' => __( 'Author Profile URL', 'smliser' ),
                'input' => array(
                    'type'  => 'text',
                    'name'  => 'app_author_url',
                    'value' => $app ? $app->get_author_profile() : '',
                    'attr'  => array(
                        'autocomplete'  => 'off',
                        'spellcheck'    => 'off'
                    )
                )
            ),
        );
    }
}