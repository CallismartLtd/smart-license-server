<?php
/**
 * Admin license page router class file
 * 
 * @author Callistus Nwachukwu
 * @package Smliser\class
 */

namespace SmartLicenseServer\Admin;

use SmartLicenseServer\Analytics\RepositoryAnalytics;
use SmartLicenseServer\Core\URL;
use SmartLicenseServer\Monetization\License;
use SmartLicenseServer\RESTAPI\Versions\V1;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * The admin license page class
 */
class LicensePage {
    /**
     * Page router
     */
    public static function router() {
        $tab = smliser_get_query_param( 'tab' );
        switch ( $tab ) {
            case 'add-new':
                self::add_license_page();
                break;
            case 'edit':
                self::edit_license_page();
                break;
            case 'view':
                self::view_license_page();
                break;
            case 'logs':
                self::license_logs_page();
                break;
            default:
            self::dashboard();
        }
    
    }

    /**
     * The license page dashbard
     */
    private static function dashboard() : void {
        $licenses   = License::get_all();
        $add_url    = smliser_license_admin_action_page( 'add-new' );
    
        include_once SMLISER_PATH . 'templates/admin/license/dashboard.php';
    
    }

    /**
     * Add license page
     */
    private static function add_license_page() {
        include_once SMLISER_PATH . 'templates/admin/license/license-add.php';
    }

    /**
     * License edit page
     */
    private static function edit_license_page() : void {

        $license_id = smliser_get_query_param( 'license_id' );        
        $license    = License::get_by_id( $license_id );
        $user_id    = ! empty( $license ) ? $license->get_user_id() : 0;
        include_once SMLISER_PATH . 'templates/admin/license/license-edit.php';
    
    }

    /**
     * License view page
     */
    private static function view_license_page() : void {
        $license_id         = smliser_get_query_param( 'license_id' );
        $route_descriptions = V1::describe_routes('license');   

        $license    = License::get_by_id( $license_id );
        if ( ! empty( $license ) ) {
            $user               = get_userdata( $license->get_user_id() );
            $client_fullname    = $user ? $user->first_name . ' ' . $user->last_name : 'Guest';
            $licensed_app       = $license->get_app();
            $delete_url         = new URL( admin_url( 'admin-post.php' ) );

            $delete_url->add_query_params( ['action' => 'smliser_all_actions', 'real_action' => 'delete', 'context' => 'license', 'license_ids' => $license_id] );
            $delete_link    = wp_nonce_url( $delete_url->get_href(), 'smliser_nonce', 'smliser_nonce' );    
        }

        include_once SMLISER_PATH . 'templates/admin/license/license-admin-view.php';    
    }

    /**
     * License activation log page.
     */
    private static function license_logs_page() : void {
        $all_tasks  = RepositoryAnalytics::get_license_activity_logs();

        include_once SMLISER_PATH . 'templates/admin/license/logs.php';
    }

    /**
     * Get args for admin menu.
     * 
     * @return array
     */
    protected static function get_menu_args() : array {
        $tab    = \smliser_get_query_param( 'tab' );
        $title  = match ( $tab ) {
            'logs'      => 'License Activity Logs',
            'add-new'   => 'Add new license',
            'edit'      => 'Edit license',
            'view'      => 'View License',
            default     => 'No title'
        };

        return array(
            'breadcrumbs'   => array(
                array(
                    'label' => 'Licenses',
                    'url'   => admin_url( 'admin.php?page=licenses' ),
                    'icon'  => 'dashicons dashicons-admin-home'
                ),
                array(
                    'label' => $title,
                )
            ),
            'actions'   => array(
                array(
                    'title' => 'Settings',
                    'label' => 'Settings',
                    'url'   => admin_url( 'admin.php?page=smliser-options'),
                    'icon'  => 'dashicons dashicons-admin-generic'
                )
            )
        );
    }

    /**
     * Get license form fields.
     * 
     * @param License|null
     * @return array
     */
    protected static function get_form_fields( ?License $license = null ) : array {
        return array(
            array(
                'label' => 'Client Name',
                'input' => array(

                )
            )
        );
    }
    
}