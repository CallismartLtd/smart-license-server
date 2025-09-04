<?php
/**
 * The admin repository page handler class.
 * 
 * @author Callistus Nwachukwu
 * @package Smliser\class
 */

namespace SmartLicenseServer\admin;
use \Smliser_Plugin, \Smliser_Stats;

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

        $upload_dash    = SMLISER_PATH . 'templates/admin/repository/upload.php';
        $uploader_temp  = SMLISER_PATH . 'templates/admin/repository/uploader.php';
        $file   = $type ? $uploader_temp : $upload_dash;
        $max_upload_size_bytes = wp_max_upload_size();
        $max_upload_size_mb = $max_upload_size_bytes / 1024 / 1024;

        $title = $type ? ucfirst( $type ) : '';

        $essential_fields = array(
            array(
                'label' => __( 'Name', 'smliser' ),
                'input' => array(
                    'type'  => 'text',
                    'name'  => 'app_name',
                    'value' => '',
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
                    'value' => '',
                    'attr'  => array(
                        'autocomplete'  => 'off',
                        'spellcheck'    => 'off'
                    )
                )
            ),
            array(
                'label' => __( 'Author Name'),
                'input' => array(
                    'type'  => 'text',
                    'name'  => 'app_author',
                    'value' => '',
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
                    'value' => '',
                    'attr'  => array(
                        'autocomplete'  => 'off',
                        'spellcheck'    => 'off'
                    )
                )
            ),
        );
        
        include_once $file;
    }

    /**
     * The edit page
     */
    private static function edit_page() {
        $id     = smliser_get_query_param( 'item_id' );
        $plugin = Smliser_Plugin::get_plugin( $id );

        include_once SMLISER_PATH . 'templates/admin/repository/plugin-edit.php';
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
}