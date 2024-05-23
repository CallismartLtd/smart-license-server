<?php
/**
 * Admin menu class file.
 */

defined( 'ABSPATH' ) || exit;

class Smliser_admin_menu {

    private static $instance = null;


    public function __construct() {
        add_action( 'admin_menu', array( $this, 'menus') );

    }

    public function menus() {
        global $menu;
	
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
        
        // Add submenu "Tasks".
        $tasks = add_submenu_page(
            'smliser-admin',
            'Tasks',
            'Tasks',
            'manage_options',
            'tasks',
            array( $this, 'task_page' )
        );

        // Add submenu "Settings".
        $options = add_submenu_page(
            'smliser-admin',
            'Settings',
            'Settings',
            'manage_options',
            'smliser-options',
            array( $this, 'options_page' )
        );

        foreach ( $menu as $index => $data ) {
            if ( $data[2] === 'smliser-admin' ) {
                $menu[$index][0] = 'Smart Licenses';
                break;
            }
        }
    }

    /**
     * Dashboard Callback method
     */
    public function admin_menu() {
        $page_html = '<h2>Smart License Dashboard</h2>';
        $obj = new Smliser_license();
        $license_key ='SMALISER-AAE1659B-D9ED2B79-1B703CA1-EF721477-4D5EBF4E-E02A3A7A-C5CDFD1D-B2203EAA-99E884B1';
         $result = $obj->get_license_data( 'fgfgfhsxbdbd', $license_key );
        if ( ! empty( $result ) ) {
            $page_html .=  $result->get_copyable_Lkey();
        } else {
            $page_html .= 'Nothing was fetched';
        }
        echo $page_html;
    }

    /**
     * License page controller
     */
    public function license_page_controller() {
        $license_id = isset( $_GET['edit-license'] ) ? absint( $_GET['edit-license'] ) : 0;
        
        if ( $license_id ) {
            $this->edit_license_page();
            return;
        } else {
            $this->license_page();
        }
    }
    /**
     * License management page
     */
    public function license_page() {
        $obj = new Smliser_license();
        $licenses = $obj->get_licenses();
        $table_html   = '<div class="smliser-table-wrapper">';
        $table_html  .= '<h1>Licenses</h1>';
    
        if ( empty( $licenses ) ) {
            $table_html .= smliser_not_found_container( 'All Licences will appear here' );
            $table_html .= '</div>';
    
            echo wp_kses_post( $table_html );
            return;
        }
    
        $table_html .= '<form id="smliser-bulk-action-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        
        $table_html .= '<div class="smliser-actions-wrapper">';
        $table_html .= '<div class="smliser-bulk-actions">';
        $table_html .= '<select name="bulk_action" id="smliser-bulk-action" class="smliser-bulk-action-select">';
        $table_html .= '<option value="">' . esc_html__( 'Bulk Actions', 'smliser' ) . '</option>';
        $table_html .= '<option value="deactivate">' . esc_html__( 'Deactivate', 'smliser' ) . '</option>';
        $table_html .= '<option value="delete">' . esc_html__( 'Delete', 'smliser' ) . '</option>';
        $table_html .= '</select>';
        $table_html .= '<button type="submit" class="button action smliser-bulk-action-button">' . esc_html__( 'Apply', 'smliser' ) . '</button>';
        $table_html .= '</div>';
        $table_html .= '<div class="smliser-search-box">';
        $table_html .= '<input type="text" id="smliser-search" class="smliser-search-input" placeholder="' . esc_attr__( 'Search Licenses', 'smliser' ) . '">';
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
            $license_url        = esc_url( add_query_arg( 
                array( 
                    'page' => 'licenses',
                    'edit-license' => $license->get_id() 
                ), admin_url( 'admin.php' ) ) );
    
            $table_html .= '<tr>';
            $table_html .= '<td><input type="checkbox" class="smliser-license-checkbox" name="licenses[]" value="' . esc_attr( $license->get_id() ) . '"></td>';
            $table_html .= '<td class="smliser-edit-row">';
            $table_html .= esc_html( $license->get_id() );
            $table_html .= '<div class="smliser-edit-link"><p><a href="' . esc_url( $license_url ) . '">edit</a></p></div>';
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
        $table_html .= '<p class="sw-table-count">' . count( $licenses ) . ' items</p>';
        $table_html .= '</div>';
    
        echo $table_html;
    }
    

    /**
     * Instanciate class.
     */
    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
    }
}

Smliser_admin_menu::instance();