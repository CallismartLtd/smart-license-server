<?php
/**
 * Admin menu class file.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class representation of the admin menu.
 * 
 * @author Callistus
 * @since 1.0.0
 * @package Smliser\classes
 */
class Smliser_admin_menu {

    /**
     * @var Smliser_admin_menu
     */
    private static $instance = null;

    /**
     * Class constructor.
     */
    public function __construct() {
        
        // add_action( 'admin_menu', array( $this, 'menus') );  
    }

    /**
     * Instanciate class.
     */
    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Admin menus
     */
    public function menus() {	
        $dashboard = add_menu_page(
            'Smart License Server',
            'Dashboard',
            'manage_options',
            'smliser-admin',
            array( $this, 'admin_menu' ),
            'dashicons-database-view',
            3.1
        );

        // Add submenu "Licenses".
        $license_page = add_submenu_page(
            'smliser-admin',
            'Licenses',
            'Licenses',
            'manage_options',
            'licenses',
            array( $this, 'license_page_controller' )
        );

         // Add submenu "Repository".
         $license_page = add_submenu_page(
            'smliser-admin',
            'Repository',
            'Repository',
            'manage_options',
            'repository',
            array( $this, 'repo_page_controller' )
        );
        
        // Add submenu "Tasks".
        $tasks = add_submenu_page(
            'smliser-admin',
            'Tasks',
            'Tasks',
            'manage_options',
            'tasks',
            array( $this, 'task_page_controller' )
        );

        // Add submenu "Settings".
        $options = add_submenu_page(
            'smliser-admin',
            'Settings',
            'Settings',
            'manage_options',
            'smliser-options',
            array( $this, 'options_page_controller' )
        );

    }

    /**
     * Dashboard Callback method
     */
    public function admin_menu() {
        
        $stats          = new Smliser_Stats();
        $status_codes   = $stats->get_status_codes_distribution();
        $error_codes    = $stats->get_top_errors( wp_rand( 50, 100 ) );
        // Prepare data for Chart.js
        $plugin_update_hits         = $stats->get_total_hits( $stats::$plugin_update );
        $license_activation_hits    = $stats->get_total_hits( $stats::$license_activation );
        $license_deactivation_hits  = $stats->get_total_hits( $stats::$license_deactivation );

        $plugin_update_visits       = $stats->total_visits_today( $stats::$plugin_update );
        $license_activation_visits  = $stats->total_visits_today( $stats::$license_activation );
        $license_deactivation_visits = $stats->total_visits_today( $stats::$license_deactivation );

        $plugin_unique_visitors                 = $stats->get_unique_ips( $stats::$plugin_update );
        $license_activation_unique_visitors     = $stats->get_unique_ips( $stats::$license_activation );
        $license_deactivation_unique_visitors   = $stats->get_unique_ips( $stats::$license_deactivation );

        include_once SMLISER_PATH . 'templates/admin-dashboard.php';
    }

    /**
     * License page controller
     */
    public function license_page_controller() {
        $action = isset( $_GET['action'] ) ? $_GET['action'] : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $page = '';

        switch ( $action ) {
            case 'add-new':
                $this->add_license_page();
                break;
            case 'edit':
                $this->edit_license_page();
                break;
            case 'view':
                $this->license_view();
                break;
            default:
                $page = $this->license_page();
        }

        add_filter( 'wp_kses_allowed_html', 'smliser_allowed_html', 10, 2 );
        echo wp_kses_post( $page );
    }

    /**
     * License management page
     */
    public function license_page() {
        $obj = new Smliser_license();
        $licenses    = $obj->get_licenses();
        $table_html  = '<div class="smliser-table-wrapper">';
        $table_html .= '<h1>Licenses</h1>';
        $add_url     = smliser_license_admin_action_page( 'add-new' );
        $table_html .= '<a href="'. esc_url( $add_url ) . '" class="button action smliser-nav-btn">Add New License</a>';
    
        if ( empty( $licenses ) ) {
            $table_html .= smliser_not_found_container( 'All licenses will appear here' );
            $table_html .= '</div>';
    
            return $table_html;
            
        }
    
        $table_html .= '<form id="smliser-bulk-action-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        
        $table_html .= '<div class="smliser-actions-wrapper">';
        $table_html .= '<div class="smliser-bulk-actions">';
        $table_html .= '<select name="bulk_action" id="smliser-bulk-action" class="smliser-bulk-action-select" required>';
        $table_html .= '<option value="">' . esc_html__( 'Bulk Actions', 'smliser' ) . '</option>';
        $table_html .= '<option value="deactivate">' . esc_html__( 'Deactivate', 'smliser' ) . '</option>';
        $table_html .= '<option value="suspend">' . esc_html__( 'Suspend', 'smliser' ) . '</option>';
        $table_html .= '<option value="revoke">' . esc_html__( 'Revoke', 'smliser' ) . '</option>';
        $table_html .= '<option value="delete">' . esc_html__( 'Delete', 'smliser' ) . '</option>';
        $table_html .= '</select>';
        $table_html .= '<button type="submit" class="button action smliser-bulk-action-button">' . esc_html__( 'Apply', 'smliser' ) . '</button>';
        $table_html .= '</div>';
        $table_html .= '<div class="smliser-search-box">';
        $table_html .= '<input type="search" id="smliser-search" class="smliser-search-input" placeholder="' . esc_attr__( 'Search Licenses', 'smliser' ) . '">';
        $table_html .= '</div>';
        $table_html .= '</div>';
    
        $table_html .= '<input type="hidden" name="action" value="smliser_bulk_action">';
        $table_html .= wp_nonce_field( 'smliser_table_nonce', 'smliser_table_nonce');
        $table_html .= '<table class="smliser-table">';
        $table_html .= '<thead>';
        $table_html .= '<tr>';
        $table_html .= '<th><input type="checkbox" id="smliser-select-all"></th>';
        $table_html .= '<th>' . esc_html__( 'License ID', 'smliser' ) . '</th>';
        $table_html .= '<th>' . esc_html__( 'Client Name', 'smliser' ) . '</th>';
        $table_html .= '<th>' . esc_html__( 'License Key', 'smliser' ) . '</th>';
        $table_html .= '<th>' . esc_html__( 'Service ID', 'smliser' ) . '</th>';
        $table_html .= '<th>' . esc_html__( 'Item ID', 'smliser' ) . '</th>';
        $table_html .= '<th>' . esc_html__( 'Status', 'smliser' ) . '</th>';
        $table_html .= '</tr>';
        $table_html .= '</thead>';
        $table_html .= '<tbody>';
    
        foreach ( $licenses as $license ) {
            $user               = get_userdata( $license->get_user_id() );
            $client_full_name   = $user ? $user->first_name . ' ' . $user->last_name : 'Guest';
            if ( -1 === intval( $license->get_user_id() ) ) {
                $client_full_name = 'N/L';
            }
            $license_edit_url   = smliser_license_admin_action_page( 'edit', $license->get_id() );
            $license_view_url   = smliser_license_admin_action_page( 'view', $license->get_id() );
    
            $table_html .= '<tr>';
            $table_html .= '<td><input type="checkbox" class="smliser-license-checkbox" name="license_ids[]" value="' . esc_attr( $license->get_id() ) . '"> </td>';
            $table_html .= '<td class="smliser-edit-row">';
            $table_html .= esc_html( $license->get_id() );
            $table_html .= '<div class="smliser-edit-link"><p><a href="' . esc_url( $license_edit_url ) . '">edit</a> | <a href="' . esc_url( $license_view_url ) . '">view</a> </p></div>';
            $table_html .= '</td>';
    
            $table_html .= '<td>' . esc_html( $client_full_name ) . '</td>';
            $table_html .= '<td>' . $license->get_copyable_Lkey() . '</td>';
            $table_html .= '<td>' . esc_html( $license->get_service_id() ) . '</td>';
            $table_html .= '<td>' . esc_html( $license->get_item_id() ) . '</td>';
            $table_html .= '<td>' . esc_html( $license->get_status() ) . '</td>';
            $table_html .= '</tr>';
        }
    
        $table_html .= '</tbody>';
        $table_html .= '</table>';
    
        $table_html .= '</form>';
        $table_html .= '<p class="smliser-table-count">' . count( $licenses ) . ' item'. ( count( $licenses ) > 1 ? 's': '' ) . '</p>';
        $table_html .= '</div>';
        return $table_html;
    }
    
    /**
     * Add new License page
     */
    private function add_license_page() {
        include_once SMLISER_PATH . 'templates/license/license-add.php';
    }

    /**
     * Edit license page
     */
    private function edit_license_page() {
        $license_id = isset( $_GET['license_id'] ) ? absint( $_GET['license_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        
        if ( empty( $license_id ) ) {
            return wp_kses_post( smliser_not_found_container( 'Invalid or deleted license' ) );
        }
        $license    = Smliser_license::get_by_id( $license_id );
        if ( empty( $license ) ) {
            return wp_kses_post( smliser_not_found_container( 'Invalid or deleted license' ) );
        }

        $user_id    = ! empty( $license->get_user_id() ) ? $license->get_user_id() : 0;
        include_once SMLISER_PATH . 'templates/license/license-edit.php';
    }

    /**
     * License details page
     */
    private function license_view() {
        $license_id = isset( $_GET['license_id'] ) ? absint( $_GET['license_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        
        if ( empty( $license_id ) ) {
            return wp_kses_post( smliser_not_found_container( 'Invalid or deleted license' ) );
        }
        $license    = Smliser_license::get_by_id( $license_id );
        if ( empty( $license ) ) {
            return wp_kses_post( smliser_not_found_container( 'Invalid or deleted license' ) );
        }

        $user               = get_userdata( $license->get_user_id() );
        $client_full_name   = $user ? $user->first_name . ' ' . $user->last_name : 'N/L';
        $plugin_obj         = new Smliser_Plugin();
        $licensed_plugin    = $license->has_item() ? $plugin_obj->get_plugin( $license->get_item_id() ) : false;
        $delete_link        = wp_nonce_url( add_query_arg( array( 'action' => 'smliser_all_actions', 'real_action' => 'delete', 'license_id' => $license_id ), admin_url( 'admin-post.php' ) ), -1, 'smliser_nonce' );
        $plugin_name        = $licensed_plugin ? $licensed_plugin->get_name() : 'N/A';
        include_once SMLISER_PATH . 'templates/license/license-admin-view.php';
    }

    /**
     * Task page controller
     */
    public function task_page_controller() {
        $path = isset( $_GET['path'] ) ? sanitize_key( $_GET['path'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        
        if ( 'task-logs' === $path ) {
            $this->task_log_page();
            return;
        }
        $this->task_page();
        return;
    }


    /**
     * Task page.
     */
    public function task_page( $page = '') {
        $obj            = new Smliser_Server();
        $all_tasks      = $obj->scheduled_tasks();
        $cron_handle    = wp_get_scheduled_event( 'smliser_validate_license' );
        $cron_timestamp = $cron_handle ? $cron_handle->timestamp : 0;
        $next_date      = smliser_tstmp_to_date( $cron_timestamp );

        include_once SMLISER_PATH . 'templates/tasks/tasks.php';
        return;
    }

    /**
     * Repository page controller.
     */
    public function repo_page_controller() {
        $action = isset( $_GET['action'] ) ? sanitize_text_field( unslash( $_GET['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $page = '';
        switch( $action ) {
            case 'add-new':
                $this->upload_plugin_page();
                break;
            
            case 'edit':
                $this->edit_plugin_page();
                break;

            case 'view':
                $page = $this->view_plugin_page();
                break;
    
            default:
            if ( empty( $action ) ) {
                $page = $this->repositor_dashboard();
            } else {
                do_action( 'smliser_repository_page_' . $action .'_content' );
            }
        }

        add_filter( 'wp_kses_allowed_html', 'smliser_allowed_html', 10, 2 );
        echo wp_kses_post( $page );
    }

    /**
     * Repository dashboard.
     */
    private function repositor_dashboard() {

        $plugins     = Smliser_Plugin::get_plugins();
        $table_html  = '<div class="smliser-table-wrapper">';
        $table_html .= '<h1>Plugin Repository</h1>';
        $add_url     = smliser_admin_repo_tab( 'add-new' );
        $table_html .= '<a href="'. esc_url( $add_url ) . '" class="button action smliser-nav-btn">Upload New Plugin</a>';
    
        if ( empty( $plugins ) ) {
            $table_html .= smliser_not_found_container( 'All uploaded plugins will appear here.' );
            $table_html .= '</div>';
    
            return $table_html;
            
        }
    
        $table_html .= '<form id="smliser-bulk-action-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        
        $table_html .= '<div class="smliser-actions-wrapper">';
        
        $table_html .= '<div class="smliser-search-box">';
        $table_html .= '<input type="search" id="smliser-search" class="smliser-search-input" placeholder="' . esc_attr__( 'Search Plugins', 'smliser' ) . '">';
        $table_html .= '</div>';
        $table_html .= '</div>';
    
        $table_html .= '<table class="smliser-table">';
        $table_html .= '<thead>';
        $table_html .= '<tr>';
        $table_html .= '<th>' . esc_html__( 'Item ID', 'smliser' ) . '</th>';
        $table_html .= '<th>' . esc_html__( 'Plugin Name', 'smliser' ) . '</th>';
        $table_html .= '<th>' . esc_html__( 'Plugin Author', 'smliser' ) . '</th>';
        $table_html .= '<th>' . esc_html__( 'Version', 'smliser' ) . '</th>';
        $table_html .= '<th>' . esc_html__( 'Slug', 'smliser' ) . '</th>';
        $table_html .= '<th>' . esc_html__( 'Created at', 'smliser' ) . '</th>';
        $table_html .= '<th>' . esc_html__( 'Last Updated', 'smliser' ) . '</th>';
        $table_html .= '</tr>';
        $table_html .= '</thead>';
        $table_html .= '<tbody>';
    
        foreach ( $plugins as $plugin ) {
            $plugin_edit_url    = smliser_admin_repo_tab( 'edit', $plugin->get_item_id() );
            $plugin_view_url    = smliser_admin_repo_tab( 'view', $plugin->get_item_id() );
    
            $table_html .= '<tr>';
            $table_html .= '<td class="smliser-edit-row">';
            $table_html .= esc_html( $plugin->get_item_id() );
            $table_html .= '<div class="smliser-edit-link"><p><a href="' . esc_url( $plugin_edit_url ) . '">edit</a> | <a href="' . esc_url( $plugin_view_url ) . '">view</a> </p></div>';
            $table_html .= '</td>';
    
            $table_html .= '<td>' . esc_html( $plugin->get_name() ) . '</td>';
            $table_html .= '<td>' . $plugin->get_author() . '</td>';
            $table_html .= '<td>' . esc_html( $plugin->get_version() ) . '</td>';
            $table_html .= '<td>' . esc_html( $plugin->get_slug() ) . '</td>';
            $table_html .= '<td>' . esc_html( smliser_check_and_format( $plugin->get_date_created(), true ) ) . '</td>';
            $table_html .= '<td>' . esc_html( smliser_check_and_format( $plugin->get_last_updated(), true ) ) . '</td>';
            $table_html .= '</tr>';
        }
    
        $table_html .= '</tbody>';
        $table_html .= '</table>';
    
        $table_html .= '</form>';
        $table_html .= '<p class="smliser-table-count">' . count( $plugins ) . ' item'. ( count( $plugins ) > 1 ? 's': '' ) . '</p>';
        $table_html .= '</div>';
        return $table_html;
    }

    /**
     * upload new plugin page template
     */
    private function upload_plugin_page() {
        include_once SMLISER_PATH . 'templates/repository/repo-add.php';
    }

    /**
     * Plugin edit page
     */
    public function edit_plugin_page() {
        $id     = isset( $_GET['item_id'] ) ? absint( $_GET['item_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( empty( $id ) ) {
            return smliser_not_found_container( 'Item ID parameter should not be manipulated' );
        }
        $obj    = new Smliser_Plugin();
        $plugin = $obj->get_plugin( $id );
        if ( empty( $plugin ) ) {
            return smliser_not_found_container( 'Invalid or deleted plugin' );
        }

        include_once SMLISER_PATH . 'templates/repository/plugin-edit.php';
    }

    /**
     * Plugin view
     */
    public function view_plugin_page() {
        $id = isset( $_GET['item_id'] ) ? absint( $_GET['item_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( empty( $id ) ) {
            return smliser_not_found_container( 'Item ID parameter should not be manipulated' );
        }

        $obj    = new Smliser_Plugin();
        $plugin = $obj->get_plugin( $id );

        if ( ! empty( $plugin ) ) {
            $delete_link    = wp_nonce_url( add_query_arg( array( 'action' => 'smliser_plugin_action', 'real_action' => 'delete', 'item_id' => $id ), admin_url( 'admin-post.php' ) ), -1, 'smliser_nonce' );
        }
        
        $stats = new Smliser_Stats();
        include_once SMLISER_PATH . 'templates/repository/plugin-view.php';
    }

    /**
     *  Settings page contrlloer.
     */
    public function options_page_controller() {
        $path = isset( $_GET['path'] ) ? sanitize_key( $_GET['path'] ) : '';
        $tabs = array(
            ''          => 'General',
            'pages'     => 'Page Setup',
            'api-keys'  => 'REST API',

        );
        
        echo wp_kses_post( smliser_sub_menu_nav( $tabs, 'Settings', 'smliser-options', $path, 'path' ) );
        
        switch ( $path ) {
            case 'api-keys': 
                $this->api_keys_option();
                break;
            case 'pages':
                $this->pages_options();
                break;
            default :
                
                if ( has_action( 'smlise_options_' . $path . '_content'  ) ) {
                    do_action( 'smlise_options_' . $path . '_content' );
                }else {
                    $this->options_general_page();
                }
        }

    }

    /**
     * Settings page
     */
    public function options_general_page() {
        include_once SMLISER_PATH . 'templates/options/options.php';
    }

    /**
     * API Keys options page
     */
    public function api_keys_option() {
        $all_api_data   = Smliser_API_Cred::get_all();
        include_once SMLISER_PATH . 'templates/options/api-keys.php';
        return;
    }



}

Smliser_admin_menu::instance();